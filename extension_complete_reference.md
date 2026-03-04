# `laravel-modules-arch` — Guia Completo de Referência

## Como Ler Este Documento

Cada seção segue o mesmo formato:

1. **📖 Conceito** — A base teórica dos livros de DDD
2. **❌ Problema** — O que o Laravel/nWidart não resolve
3. **✅ Nossa Solução** — O que a extensão implementa
4. **💻 Código** — Implementação interna da extensão
5. **🔧 Uso** — Como o consumidor final usa

### Livros Referenciados

| Abreviação | Livro | Autor |
|---|---|---|
| **🔵 Blue Book** | *Domain-Driven Design: Tackling Complexity in the Heart of Software* | Eric Evans (2003) |
| **🔴 Red Book** | *Implementing Domain-Driven Design* | Vaughn Vernon (2013) |
| **🟠 Beyond CRUD** | *Laravel Beyond CRUD* | Brent Roose / Spatie (2020, 2ª ed. 2022) |

---

---

# PARTE 1 — PRIMITIVAS DE MODULARIZAÇÃO

Estas features resolvem os problemas de **relacionamento entre módulos**. Sem elas, módulos que dependem de outros quebram quando o módulo opcional é desativado.

---

## 1. `NullRelation`

### 📖 Conceito

> *"When two Bounded Contexts have no significant relationship, they are said to be in separate **Separate Ways**. [...] There is no need to integrate."*
> — 🔵 Blue Book, Cap. 14 — *Context Map*

Quando um módulo é desativado, as relações que apontam para ele não devem quebrar — devem simplesmente **não existir**. Evans chama isso de Separate Ways: dois contextos que operam independentemente.

### ❌ Problema

O hack comum no Laravel é retornar uma relação "falsa":

```php
return $this->hasMany(self::class)->whereRaw('1 = 0');
```

Isso gera problemas:
- Retorna `HasMany<Lead>` em vez de `HasMany<SaleOrder>` — tipo errado
- Faz uma query real no banco (com `WHERE 1=0`) — desperdício
- Eager loading carrega a tabela errada
- PHPStan/Larastan não consegue analisar

### ✅ Nossa Solução

Uma relação Eloquent real que **nunca toca no banco**:

### 💻 Código Interno da Extensão

```php
namespace LaravelModulesArch\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Relação nula que retorna Collection vazia sem executar queries.
 * Usada quando o módulo-alvo da relação está desativado.
 *
 * Implementa todos os métodos abstratos de Relation para garantir
 * compatibilidade com eager loading, lazy loading e serialização.
 */
class NullRelation extends Relation
{
    public function __construct(Model $parent)
    {
        parent::__construct($parent->newQuery(), $parent);
    }

    // Não adiciona constraints — não há query a ser feita
    public function addConstraints(): void {}

    // Não adiciona constraints de eager loading
    public function addEagerConstraints(array $models): void {}

    // Inicializa a relação como Collection vazia em cada model
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection());
        }
        return $models;
    }

    // Match: retorna os models com relação vazia (eager loading)
    public function match(array $models, Collection $results, $relation): array
    {
        return $this->initRelation($models, $relation);
    }

    // Resultado direto: Collection vazia
    public function getResults(): Collection
    {
        return new Collection();
    }
}
```

### 🔧 Uso pelo Consumidor

```php
use LaravelModulesArch\Relations\NullRelation;

// No Model do módulo CRM:
public function quotations()
{
    if (! Module::isEnabled('Sale')) {
        return new NullRelation($this);
    }
    return $this->hasMany(SaleOrder::class, 'lead_id');
}
```

Resultado: `$lead->quotations` retorna `Collection` vazia sem tocar no banco. Eager loading (`Lead::with('quotations')`) funciona normalmente.

---

## 2. `ModuleAwareRelation`

### 📖 Conceito

> *"The purpose of abstraction is not to be vague, but to create a new semantic level in which one can be absolutely precise."*
> — Edsger Dijkstra (citado no 🔵 Blue Book)

A `NullRelation` resolve o problema, mas o `if/else` manual se repete em cada relação de cada model. `ModuleAwareRelation` encapsula o padrão inteiro num one-liner.

### ❌ Problema

Sem encapsulamento, cada relação condicional repete 5 linhas idênticas — em 50 models, são 250 linhas de boilerplate.

### 💻 Código Interno

```php
namespace LaravelModulesArch\Support;

use LaravelModulesArch\Relations\NullRelation;
use Nwidart\Modules\Facades\Module;

/**
 * Helper que encapsula o padrão:
 * "Se o módulo está ativo, cria relação real; senão, NullRelation."
 *
 * Suporta: hasMany, hasOne, belongsTo, morphMany, morphOne, morphTo.
 */
class ModuleAwareRelation
{
    public static function hasMany(
        $parent,
        string $moduleName,
        string $relatedClass,
        string $foreignKey = null,
        string $localKey = null,
    ) {
        if (! Module::isEnabled($moduleName)) {
            return new NullRelation($parent);
        }
        return $parent->hasMany($relatedClass, $foreignKey, $localKey);
    }

    public static function hasOne(
        $parent,
        string $moduleName,
        string $relatedClass,
        string $foreignKey = null,
        string $localKey = null,
    ) {
        if (! Module::isEnabled($moduleName)) {
            return new NullRelation($parent);
        }
        return $parent->hasOne($relatedClass, $foreignKey, $localKey);
    }

    public static function belongsTo(
        $parent,
        string $moduleName,
        string $relatedClass,
        string $foreignKey = null,
        string $ownerKey = null,
    ) {
        if (! Module::isEnabled($moduleName)) {
            return new NullRelation($parent);
        }
        return $parent->belongsTo($relatedClass, $foreignKey, $ownerKey);
    }

    public static function morphMany(
        $parent,
        string $moduleName,
        string $relatedClass,
        string $morphName,
    ) {
        if (! Module::isEnabled($moduleName)) {
            return new NullRelation($parent);
        }
        return $parent->morphMany($relatedClass, $morphName);
    }
}
```

### 🔧 Uso

```php
public function quotations()
{
    return ModuleAwareRelation::hasMany(
        $this, 'Sale', SaleOrder::class, 'lead_id'
    );
}

public function financialTransactions()
{
    return ModuleAwareRelation::morphMany(
        $this, 'Account', FinancialTransaction::class, 'transactionable'
    );
}
```

Uma linha em vez de cinco. Zero boilerplate.

---

## 3. `RegistersModuleMorphMap` (Trait)

### 📖 Conceito

> *"Name things in the model based on **Ubiquitous Language**. When teams use different terms for the same concept across Bounded Contexts, confusion follows."*
> — 🔵 Blue Book, Cap. 2 — *Communication and the Use of Language*

O `morphMap` do Laravel traduz nomes de classes (`Modules\Sale\Models\SaleOrder`) para aliases legíveis (`sale_order`). Isso é Ubiquitous Language aplicada ao banco: o campo `morphable_type` armazena `sale_order` em vez de um namespace PHP.

### ❌ Problema

O padrão comum é centralizar todos os morph maps num módulo "Base":

```php
// BaseServiceProvider — conhece TODOS os módulos
Relation::enforceMorphMap([
    'lead'       => Lead::class,
    'sale_order' => SaleOrder::class,  // ← Base importa Sale
    'product'    => Product::class,    // ← Base importa Stock
]);
```

Problemas:
- Base vira um **God Module** que conhece todos os outros
- `enforceMorphMap()` **sobrescreve** registros anteriores
- Adicionar módulo = editar Base

### 💻 Código Interno

```php
namespace LaravelModulesArch\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Trait para ServiceProviders de módulo.
 * Cada módulo registra apenas seus próprios morph aliases.
 *
 * Usa Relation::morphMap() (ADITIVO) em vez de
 * Relation::enforceMorphMap() (DESTRUTIVO).
 */
trait RegistersModuleMorphMap
{
    /**
     * Retorna o morph map deste módulo.
     * Exemplo: ['sale_order' => SaleOrder::class]
     */
    abstract protected function morphMap(): array;

    protected function bootModuleMorphMap(): void
    {
        Relation::morphMap($this->morphMap());
    }
}
```

### 🔧 Uso

```php
class SaleServiceProvider extends ServiceProvider
{
    use RegistersModuleMorphMap;

    protected function morphMap(): array
    {
        return [
            'sale_order'   => SaleOrder::class,
            'sale_invoice' => SaleInvoice::class,
        ];
    }

    public function boot(): void
    {
        $this->bootModuleMorphMap();
    }
}
```

Cada módulo é dono do seu morph map. Remover o módulo = remover o morph map automaticamente.

---

## 4. `CrossModuleAction`

### 📖 Conceito

> *"Where two Bounded Contexts share a subset of functionality, use an **Open Host Service** — a well-defined protocol (API) that other contexts can use."*
> — 🔵 Blue Book, Cap. 14 — *Open Host Service*

> *"An Application Service [...] coordinates the activity of domain objects and redirects to infrastructure services. It doesn't contain business rules."*
> — 🔴 Red Book, Cap. 14 — *Application Services*

Quando um módulo precisa **ler dados** de outro (sem efeito colateral), ele usa o CrossModuleAction como ponto de acesso controlado, respeitando o padrão Open Host Service.

### ❌ Problema

```php
// Chamada direta — acoplamento total
if (Module::isEnabled('Account')) {
    $action = new \Modules\Account\Actions\GetFinancialSummary;
    $result = $action->handle();
}
```

Problemas: import direto de classe interna, sem fallback, boilerplate `if/else`.

### 💻 Código Interno

```php
namespace LaravelModulesArch\Support;

use Nwidart\Modules\Facades\Module;

/**
 * Invocação segura de Actions de outros módulos.
 *
 * Padrão: Open Host Service (Evans, Blue Book)
 * - Verifica se módulo está ativo
 * - Verifica se classe existe
 * - Retorna default se indisponível
 * - Resolve via Service Container (testável/mockável)
 */
class CrossModuleAction
{
    /**
     * @param string $moduleName    Nome do módulo alvo
     * @param string $actionClass   FQCN da Action a executar
     * @param array  $params        Parâmetros para handle()
     * @param mixed  $default       Valor retornado se módulo inativo
     */
    public static function run(
        string $moduleName,
        string $actionClass,
        array $params = [],
        mixed $default = null,
    ): mixed {
        if (! Module::isEnabled($moduleName)) {
            return value($default);
        }

        if (! class_exists($actionClass)) {
            return value($default);
        }

        // Resolve via container para suportar injeção de dependências
        $action = app($actionClass);

        return $action->handle(...$params);
    }
}
```

### 🔧 Uso

```php
// Lê dados do módulo Account (se ativo), senão retorna array vazio
$summary = CrossModuleAction::run(
    'Account',
    GetFinancialSummary::class,
    ['tenantId' => $tenantId],
    default: []
);
```

---

---

# PARTE 2 — CAMADA DE DOMÍNIO (Domain Layer)

Estas features implementam o coração do DDD: o código de negócio puro, sem framework.

---

## 5. Entities (Entidades de Domínio)

### 📖 Conceito

> *"Many objects are not fundamentally defined by their attributes, but rather by a thread of continuity and **identity**."*
> — 🔵 Blue Book, Cap. 5 — *Entities*

> *"Keep model classes small and clean. Move properties to Value Objects when applicable."*
> — 🟠 Beyond CRUD, Cap. 3 — *Models*

Uma Entidade é definida pela sua **identidade** (`$id`), não pelos seus atributos. Duas pessoas com o mesmo nome são pessoas diferentes — porque têm identidades diferentes.

### ❌ Problema no Laravel

O Eloquent Model é Entity + Persistence + Query Builder + Event Dispatcher tudo num objeto só. Em domínios simples, isso é pragmático. Em domínios complexos, a Entidade precisa existir sem `extends Model`.

### ✅ Nossa Solução: Dual Mode

A extensão gera `Domain/Entities/` com `.gitkeep`. **Se o dev não precisa, fica vazio** — o Eloquent Model na Infrastructure serve como entidade rica (modo pragmático, como a Spatie recomenda).

Se o domínio for complexo, o dev cria a Entidade pura:

### 💻 Stub Gerado: `arch:make-entity Sale/SaleOrder`

```php
<?php

namespace Modules\Sale\Domain\Entities;

/**
 * Entidade de Domínio: SaleOrder
 *
 * Esta classe contém regras de negócio puras.
 * NÃO deve importar nada do Laravel (Illuminate\*).
 * Persistência é responsabilidade do Repository (Infrastructure).
 *
 * @see "Domain-Driven Design" (Evans), Cap. 5 — Entities
 */
final class SaleOrder
{
    private array $domainEvents = [];

    public function __construct(
        private readonly string $id,
        // Adicione propriedades do domínio aqui
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    // --- Domain Events ---

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
```

---

## 6. Value Objects (Objetos de Valor)

### 📖 Conceito

> *"When you care only about the **attributes** of an element of the model, classify it as a Value Object. Make it express the meaning of the attributes it conveys and give it related functionality. Treat the Value Object as **immutable**."*
> — 🔵 Blue Book, Cap. 6 — *Value Objects*

> *"Value Objects are one of the building blocks I see used far too little. They're extremely powerful, yet so simple."*
> — 🟠 Beyond CRUD, Cap. 3 — *Models*

### 💻 Stub: `arch:make-value-object Sale/OrderTotal`

```php
<?php

namespace Modules\Sale\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: OrderTotal
 *
 * Imutável — qualquer transformação retorna nova instância.
 * Sem identidade — dois OrderTotal com mesmo valor são iguais.
 *
 * @see "Domain-Driven Design" (Evans), Cap. 6 — Value Objects
 */
final readonly class OrderTotal
{
    public function __construct(
        private float $value,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "OrderTotal não pode ser negativo. Recebido: {$value}"
            );
        }
    }

    public function value(): float
    {
        return $this->value;
    }

    /** Imutável: retorna nova instância */
    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    /** Comparação por valor, não por referência */
    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < PHP_FLOAT_EPSILON;
    }
}
```

---

## 7. Aggregates (Agregados)

### 📖 Conceito

> *"Cluster the Entities and Value Objects into **Aggregates** and define boundaries around each. Choose one Entity to be the root of each Aggregate, and control all access to the objects inside the boundary through the root."*
> — 🔵 Blue Book, Cap. 6 — *Aggregates*

> *"An Aggregate should exhibit transactional consistency. [...] Only one Aggregate instance should be modified per transaction."*
> — 🔴 Red Book, Cap. 10 — *Aggregates*

No nosso pacote, o `SaleOrder` é a **raiz do Aggregate**. Os `SaleOrderItem`s são Entidades internas que só existem via raiz.

### ✅ Regra no Pacote

O comando `arch:check-boundaries` inclui:

> **Regra R6**: Repositories devem existir apenas para Aggregate Roots, não para Entities internas.

Se alguém cria `SaleOrderItemRepository`, o enforcement alerta:

```
⚠️ R6 WARNING: SaleOrderItem parece ser uma entidade interna do aggregate SaleOrder.
   Repositórios devem existir apenas para Aggregate Roots.
   💡 Acesse SaleOrderItem via SaleOrder::items(), não via repository direto.
```

---

## 8. Repository Pattern

### 📖 Conceito

> *"A Repository represents all objects of a certain type as a conceptual set. It acts like a **collection**, except with more elaborate querying capability."*
> — 🔵 Blue Book, Cap. 6 — *Repositories*

> *"Clients should always use the Repository interface from the Domain, while the implementation lives in Infrastructure."*
> — 🔴 Red Book, Cap. 12 — *Repositories*

### 💻 Stubs: `arch:make-repository Sale/SaleOrder`

Este comando gera **dois arquivos** de uma vez:

**1. Interface no Domain:**

```php
<?php

namespace Modules\Sale\Domain\Repositories;

use Modules\Sale\Domain\Entities\SaleOrder;

/**
 * Contrato de persistência para SaleOrder.
 *
 * Esta interface vive no Domain. A implementação concreta
 * (Eloquent, Doctrine, API, etc.) vive no Infrastructure.
 *
 * @see "Domain-Driven Design" (Evans), Cap. 6 — Repositories
 * @see "Implementing DDD" (Vernon), Cap. 12 — Repositories
 */
interface SaleOrderRepositoryInterface
{
    public function findById(string $id): ?SaleOrder;

    public function save(SaleOrder $entity): void;

    public function delete(string $id): void;
}
```

**2. Implementação Eloquent no Infrastructure:**

```php
<?php

namespace Modules\Sale\Infrastructure\Persistence\Repositories;

use Modules\Sale\Domain\Entities\SaleOrder as SaleOrderEntity;
use Modules\Sale\Domain\Repositories\SaleOrderRepositoryInterface;
use Modules\Sale\Infrastructure\Persistence\Models\SaleOrder as SaleOrderModel;

/**
 * Implementação do Repository usando Eloquent.
 *
 * Traduz Entidade de Domínio ↔ Eloquent Model.
 * Se trocar de ORM, apenas esta classe muda.
 */
class EloquentSaleOrderRepository implements SaleOrderRepositoryInterface
{
    public function findById(string $id): ?SaleOrderEntity
    {
        $model = SaleOrderModel::find($id);
        return $model ? $this->toDomainEntity($model) : null;
    }

    public function save(SaleOrderEntity $entity): void
    {
        SaleOrderModel::updateOrCreate(
            ['id' => $entity->id()],
            $this->toModelAttributes($entity),
        );
    }

    public function delete(string $id): void
    {
        SaleOrderModel::destroy($id);
    }

    // --- Métodos de tradução ---

    private function toDomainEntity(SaleOrderModel $model): SaleOrderEntity
    {
        // TODO: Implementar mapeamento Model → Entity
        return new SaleOrderEntity(id: $model->id);
    }

    private function toModelAttributes(SaleOrderEntity $entity): array
    {
        // TODO: Implementar mapeamento Entity → atributos do Model
        return ['id' => $entity->id()];
    }
}
```

**3. Auto-registro no Service Provider gerado:**

```php
// O ServiceProvider já vem com o binding:
$this->app->bind(
    SaleOrderRepositoryInterface::class,
    EloquentSaleOrderRepository::class,
);
```

---

## 9. Domain Events vs. Integration Events

### 📖 Conceito

> *"A Domain Event is something that happened in the domain that you want other parts of the same domain to be aware of."*
> — 🔴 Red Book, Cap. 8 — *Domain Events*

> *"Integration Events are designed to communicate state changes **across** Bounded Contexts. They are richer in information and published only after the transaction commits."*
> — 🔴 Red Book, Cap. 8 — *Domain Events* (seção Integration)

**Diferença fundamental:**

| | Domain Event | Integration Event |
|---|---|---|
| **Escopo** | Interno ao Bounded Context | Cross-module |
| **Quem ouve** | Outras partes do mesmo módulo | Outros módulos |
| **Onde vive** | `Domain/Events/` | `Contracts/` (público) |
| **Serialização** | Não precisa | Precisa (pode ir pra fila) |
| **Exemplo** | `OrderItemAdded` | `SaleOrderCompleted` |

### 💻 Stubs

**Domain Event:** `arch:make-event Sale/OrderItemAdded`

```php
<?php

namespace Modules\Sale\Domain\Events;

/**
 * Evento de Domínio — INTERNO ao módulo Sale.
 *
 * Não implementa Illuminate contracts propositalmente.
 * Outros módulos NÃO devem escutar este evento.
 *
 * @see "Implementing DDD" (Vernon), Cap. 8 — Domain Events
 */
final readonly class OrderItemAdded
{
    public function __construct(
        public string $orderId,
        public string $productId,
        public int $quantity,
    ) {}
}
```

**Integration Event:** `arch:make-integration-event Sale/SaleOrderCompleted`

```php
<?php

namespace Modules\Sale\Contracts\Events;

/**
 * Evento de Integração — API PÚBLICA do módulo Sale.
 *
 * Outros módulos PODEM escutar este evento.
 * Deve conter apenas dados serializáveis (primitivos).
 * Declarado em module.json → events.publishes.
 *
 * @see "Implementing DDD" (Vernon), Cap. 8 — Integration Events
 */
final readonly class SaleOrderCompleted
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        public float $total,
        public string $completedAt,
    ) {}
}
```

> [!IMPORTANT]
> A extensão valida com `arch:check-boundaries`: se um módulo externo escuta um **Domain Event** (em `Domain/Events/`), é violação. Só Integration Events (em `Contracts/Events/`) podem ser escutados cross-module.

---

## 10. Anti-Corruption Layer (ACL)

### 📖 Conceito

> *"Create an isolating layer to provide clients with functionality in terms of their own domain model. The layer talks to the other system through its existing interface, requiring little or no modification to the other system. Internally, the layer translates in both directions as necessary."*
> — 🔵 Blue Book, Cap. 14 — *Anti-Corruption Layer*

### 💻 Stub: `arch:make-acl-listener Sale/HandleLeadConverted`

```php
<?php

namespace Modules\Sale\Infrastructure\ACL;

use Modules\Crm\Contracts\Events\LeadConverted; // ← Import do CONTRATO público
use Modules\Sale\Application\Actions\CreateSaleOrder;
use Modules\Sale\Application\DTOs\CreateSaleOrderData;

/**
 * Anti-Corruption Layer: traduz evento externo (CRM) para
 * operação interna (Sale).
 *
 * O módulo Sale não conhece o conceito de "Lead". Este listener
 * traduz o evento externo para o vocabulário interno.
 *
 * @see "Domain-Driven Design" (Evans), Cap. 14 — Anti-Corruption Layer
 */
class HandleLeadConverted
{
    public function __construct(
        private CreateSaleOrder $createSaleOrder,
    ) {}

    public function handle(LeadConverted $event): void
    {
        // TRADUÇÃO: conceito externo → conceito interno
        // "Lead convertido" → "Criar pedido draft"
        $this->createSaleOrder->handle(
            CreateSaleOrderData::fromArray([
                'customer_id'  => $event->customerId,
                'total_amount' => $event->estimatedValue,
                'items'        => [],
            ]),
        );
    }
}
```

---

---

# PARTE 3 — CAMADA DE APLICAÇÃO (Application Layer)

---

## 11. Actions (Application Services / Use Cases)

### 📖 Conceito

> *"Application Services are the direct clients of the domain model. They are responsible for **task coordination** and are kept thin — they don't contain business rules."*
> — 🔴 Red Book, Cap. 14 — *Application*

> *"Actions are reusable, single-purpose classes. [...] They are the entry point of your domain."*
> — 🟠 Beyond CRUD, Cap. 4 — *Actions*

### 💻 Stub: `arch:make-action Sale/CreateSaleOrder`

```php
<?php

namespace Modules\Sale\Application\Actions;

/**
 * Application Service / Use Case: CreateSaleOrder
 *
 * Coordena a criação de um pedido:
 * 1. Recebe DTO (dados validados)
 * 2. Invoca lógica de domínio (Entity ou Eloquent Model)
 * 3. Persiste via Repository ou Eloquent
 * 4. Despacha eventos
 *
 * NÃO contém regras de negócio — delega para o Domain.
 *
 * @see "Implementing DDD" (Vernon), Cap. 14 — Application
 * @see "Laravel Beyond CRUD" (Spatie), Cap. 4 — Actions
 */
class CreateSaleOrder
{
    public function __construct(
        // Injete dependências via container aqui
    ) {}

    public function handle(CreateSaleOrderData $data): mixed
    {
        // TODO: Implementar use case
    }
}
```

---

## 12. DTOs (Data Transfer Objects)

### 📖 Conceito

> *"DTOs make data a first-class citizen. [...] They ensure a known structure, with type checking and autocompletion."*
> — 🟠 Beyond CRUD, Cap. 2 — *Data Transfer Objects*

> *"DTOs are designed to transport data between application layers. They are not domain objects."*
> — Martin Fowler, *Patterns of Enterprise Application Architecture*

### 💻 Stub: `arch:make-dto Sale/CreateSaleOrderData`

```php
<?php

namespace Modules\Sale\Application\DTOs;

use Illuminate\Http\Request;

/**
 * DTO: CreateSaleOrderData
 *
 * Estrutura tipada para transporte de dados entre camadas.
 * Pode ser criado de: Request, array, comando Artisan, outro módulo.
 *
 * @see "Laravel Beyond CRUD" (Spatie), Cap. 2 — DTOs
 */
final readonly class CreateSaleOrderData
{
    public function __construct(
        // Defina as propriedades aqui
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            // Mapeie $request->validated() para as propriedades
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            // Mapeie o array para as propriedades
        );
    }
}
```

---

## 13. ViewModels

### 📖 Conceito

> *"View models are responsible for providing data to a view. [...] They keep your controllers clean and prevent the model from bleeding presentation logic."*
> — 🟠 Beyond CRUD, Cap. 5 — *View Models*

### 💻 Stub: `arch:make-view-model Sale/SaleOrderIndexViewModel`

```php
<?php

namespace Modules\Sale\Application\ViewModels;

/**
 * ViewModel: SaleOrderIndexViewModel
 *
 * Prepara dados exclusivamente para apresentação.
 * Não contém lógica de domínio — apenas formatação.
 *
 * @see "Laravel Beyond CRUD" (Spatie), Cap. 5 — View Models
 */
class SaleOrderIndexViewModel
{
    public function __construct(
        // Dados brutos do domínio
    ) {}

    // Exponha métodos que a view consome:
    // public function items(): array { ... }
    // public function totalFormatted(): string { ... }
}
```

---

## 14. States & Transitions

### 📖 Conceito

> *"Each state is represented by a dedicated class. Transitions are first-class citizens."*
> — 🟠 Beyond CRUD, Cap. 6 — *The State Pattern*

> Usa internamente o pacote `spatie/laravel-model-states`.

### 💻 Stub: `arch:make-state Sale/OrderStatus`

```php
<?php

namespace Modules\Sale\Domain\Enums;

/**
 * Enum de Estados: OrderStatus
 *
 * Define estados válidos e transições permitidas.
 * Transições ilegais lançam exceção.
 *
 * @see "Laravel Beyond CRUD" (Spatie), Cap. 6 — The State Pattern
 * @see "Domain-Driven Design" (Evans), Cap. 5 — Entities (lifecycle)
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Define quais transições são válidas a partir de cada estado.
     * Implementa o conceito de "lifecycle" do Blue Book.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft     => in_array($target, [self::Pending, self::Cancelled]),
            self::Pending   => in_array($target, [self::Completed, self::Cancelled]),
            self::Completed => false, // estado final
            self::Cancelled => false, // estado final
        };
    }

    public function transitionTo(self $target): self
    {
        if (! $this->canTransitionTo($target)) {
            throw new \DomainException(
                "Transição inválida: {$this->value} → {$target->value}"
            );
        }
        return $target;
    }
}
```

---

---

# PARTE 4 — ENFORCEMENT E TESTES

---

## 15. Boundary Enforcement (`arch:check-boundaries`)

### 📖 Conceito

> *"Explicitly define the context within which a model applies. Keep the model strictly consistent within these bounds."*
> — 🔵 Blue Book, Cap. 14 — *Bounded Context*

> *"The dependency arrow should point inward — Infrastructure depends on Application, Application depends on Domain. Never the reverse."*
> — 🔴 Red Book, Cap. 4 — *Architecture*

### 💻 Regras Implementadas

```php
namespace LaravelModulesArch\Support;

/**
 * Cada regra recebe: o arquivo sendo analisado, seus imports (use statements),
 * o módulo ao qual pertence, e o module.json.
 */
class BoundaryRules
{
    /**
     * R1 — Cross-Module: imports devem usar apenas Contracts/
     *
     * ✅ use Modules\Crm\Contracts\LeadContract;
     * ❌ use Modules\Crm\Domain\Entities\Lead;
     * ❌ use Modules\Crm\Infrastructure\Persistence\Models\Lead;
     */
    public static function crossModuleOnlyViaContracts(
        string $currentModule, string $importedNamespace
    ): ?string;

    /**
     * R2 — Domain não pode importar Infrastructure
     *
     * ❌ use Illuminate\Database\Eloquent\Model; (dentro de Domain/)
     * ❌ use Modules\Sale\Infrastructure\...; (dentro de Domain/)
     */
    public static function domainCannotImportInfrastructure(
        string $filePath, string $importedNamespace
    ): ?string;

    /**
     * R3 — Domain não pode importar Application
     *
     * ❌ use Modules\Sale\Application\...; (dentro de Domain/)
     */
    public static function domainCannotImportApplication(
        string $filePath, string $importedNamespace
    ): ?string;

    /**
     * R4 — Application não pode importar Infrastructure
     *       (exceto quando usa Eloquent como entidade no modo pragmático)
     *
     * ❌ use Modules\Sale\Infrastructure\Http\...; (dentro de Application/)
     */
    public static function applicationCannotImportInfrastructure(
        string $filePath, string $importedNamespace
    ): ?string;

    /**
     * R5 — Eventos consumidos devem estar no module.json
     *
     * Se um Listener escuta um evento de outro módulo,
     * esse evento deve estar listado em events.subscribes.
     */
    public static function subscribedEventsMustBeDeclared(
        string $currentModule, string $eventClass, array $moduleJson
    ): ?string;
}
```

### 🔧 Uso

```bash
# Scan completo
php artisan arch:check-boundaries

# Apenas um módulo
php artisan arch:check-boundaries --module=Sale

# Strict mode (CI/CD — falha com exit code 1)
php artisan arch:check-boundaries --strict
```

---

## 16. `IsolatedModuleTest`

### 📖 Conceito

> *"Each Bounded Context should be capable of evolving independently."*
> — 🔵 Blue Book, Cap. 14

Se um módulo é verdadeiramente independente, ele deve ser testável **sozinho**, com todos os outros desativados.

### 💻 Código Interno

```php
namespace LaravelModulesArch\Testing;

use Tests\TestCase;
use Nwidart\Modules\Facades\Module;

/**
 * Base class para testes isolados de módulo.
 *
 * Desativa TODOS os módulos e ativa apenas os declarados
 * em $enabledModules. Garante que o módulo funciona sozinho.
 */
abstract class IsolatedModuleTest extends TestCase
{
    /** Módulos que devem estar ativos neste teste */
    protected array $enabledModules = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Module::all() as $module) {
            $module->disable();
        }

        foreach ($this->enabledModules as $name) {
            Module::findOrFail($name)->enable();
        }
    }

    protected function tearDown(): void
    {
        foreach (Module::all() as $module) {
            $module->enable();
        }
        parent::tearDown();
    }
}
```

### 🔧 Uso

```php
class SaleModuleTest extends IsolatedModuleTest
{
    protected array $enabledModules = ['Base', 'Sale'];

    public function test_sale_order_crud_works_without_crm(): void
    {
        // CRM está desativado — relações opcionais retornam NullRelation
        $order = SaleOrder::factory()->create();
        $this->assertDatabaseHas('sale_orders', ['id' => $order->id]);
    }
}
```

---

## 17. `module.json` — Context Map Formal

### 📖 Conceito

> *"A Context Map describes the existing relationships between Bounded Contexts and the integration patterns at play."*
> — 🔵 Blue Book, Cap. 14 — *Context Map*

> *"Make the map explicit. [...] A documented Context Map allows team members to understand the integration landscape at a glance."*
> — 🔴 Red Book, Cap. 3 — *Context Maps*

### 💻 Schema Completo

```json
{
    "name": "Sale",
    "alias": "sale",
    "description": "Módulo de vendas — gerencia pedidos e faturamento.",
    "priority": 0,
    "version": "1.0.0",

    "providers": [
        "Modules\\Sale\\Infrastructure\\Providers\\SaleServiceProvider"
    ],

    "contracts": {
        "publishes": [
            "Modules\\Sale\\Contracts\\SaleOrderContract",
            "Modules\\Sale\\Contracts\\SaleServiceContract"
        ]
    },

    "events": {
        "publishes": [
            "Modules\\Sale\\Contracts\\Events\\SaleOrderCompleted",
            "Modules\\Sale\\Contracts\\Events\\SaleOrderCancelled"
        ],
        "subscribes": [
            "Modules\\Crm\\Contracts\\Events\\LeadConverted"
        ]
    },

    "dependencies": {
        "required": ["Base"],
        "optional": ["Crm", "Stock"]
    }
}
```

O `arch:check-boundaries` valida:
- Todo evento em `subscribes` tem um Listener correspondente no módulo
- Todo evento em `publishes` tem uma classe correspondente em `Contracts/Events/`
- Módulos em `dependencies.required` devem estar ativos
- Módulos em `dependencies.optional` usam `ModuleAwareRelation` quando referenciados

---

---

# PARTE 5 — COMANDOS ARTISAN (Resumo)

| Comando | Gera | Camada | Referência |
|---------|------|--------|------------|
| `arch:make-module Sale` | Esqueleto completo | Todas | 🔵 Bounded Context |
| `arch:make-entity Sale/SaleOrder` | Entidade pura | Domain | 🔵 Cap. 5 — Entities |
| `arch:make-value-object Sale/OrderTotal` | Value Object imutável | Domain | 🔵 Cap. 6 — Value Objects |
| `arch:make-enum Sale/OrderStatus` | Enum com transições | Domain | 🟠 Cap. 6 — States |
| `arch:make-repository Sale/SaleOrder` | Interface + Eloquent impl | Domain + Infra | 🔵 Cap. 6 — Repositories |
| `arch:make-action Sale/CreateSaleOrder` | Application Service | Application | 🟠 Cap. 4 — Actions |
| `arch:make-dto Sale/CreateSaleOrderData` | Data Transfer Object | Application | 🟠 Cap. 2 — DTOs |
| `arch:make-view-model Sale/IndexViewModel` | ViewModel | Application | 🟠 Cap. 5 — View Models |
| `arch:make-event Sale/OrderItemAdded` | Evento de domínio | Domain | 🔴 Cap. 8 — Domain Events |
| `arch:make-integration-event Sale/OrderCompleted` | Evento público | Contracts | 🔴 Cap. 8 — Integration Events |
| `arch:make-acl-listener Sale/HandleLeadConverted` | Anti-Corruption Layer | Infrastructure | 🔵 Cap. 14 — ACL |
| `arch:make-contract Sale/SaleServiceContract` | Interface pública | Contracts | 🔵 Cap. 14 — Open Host Service |
| `arch:check-boundaries` | Análise estática | — | 🔵 Cap. 14 — Bounded Context |

---

# PARTE 6 — CONFIGURAÇÃO

### `config/modules-arch.php`

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Caminho dos Módulos
    |--------------------------------------------------------------------------
    | Diretório onde os módulos ficam. Compatível com nWidart.
    */
    'modules_path' => base_path('Modules'),

    /*
    |--------------------------------------------------------------------------
    | Modo Padrão
    |--------------------------------------------------------------------------
    | 'pragmatic' — Eloquent Model como entidade (Spatie style)
    | 'purist'    — Entity pura + Repository pattern
    |
    | Afeta quais pastas são geradas no arch:make-module.
    | No modo pragmático, Domain/Entities/ e Domain/Repositories/ são omitidos.
    */
    'default_mode' => 'pragmatic',

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    */
    'boundaries' => [
        'enabled' => true,

        // Regras ativas (desative individualmente se necessário)
        'rules' => [
            'cross_module_via_contracts' => true,      // R1
            'domain_no_infrastructure'   => true,      // R2
            'domain_no_application'      => true,      // R3
            'application_no_infrastructure' => false,   // R4 — off por padrão (pragmático)
            'declared_subscriptions'     => true,      // R5
        ],

        // Namespaces ignorados pelo enforcement
        'ignored_namespaces' => [
            'Illuminate\\Support\\',
            'Illuminate\\Contracts\\',
        ],
    ],
];
```

> [!TIP]
> No modo `pragmatic`, a regra R4 (`application_no_infrastructure`) fica **desligada** por padrão, porque a Application pode usar Eloquent diretamente. No modo `purist`, ela liga automaticamente.
