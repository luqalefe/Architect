<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaravelModulesArch\Concerns\RegistersModuleMorphMap;
use LaravelModulesArch\Tests\TestCase;

class RegistersModuleMorphMapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap([], false);
    }

    public function test_registers_aliases_declared_by_the_module(): void
    {
        $module = $this->makeModule(['sale_order' => MorphMapFakeSaleOrder::class]);

        $module->callBootModuleMorphMap();

        $this->assertSame(MorphMapFakeSaleOrder::class, Relation::morphMap()['sale_order']);
    }

    public function test_multiple_modules_coexist_without_overwriting_each_other(): void
    {
        $sale = $this->makeModule(['sale_order' => MorphMapFakeSaleOrder::class]);
        $stock = $this->makeModule(['product' => MorphMapFakeProduct::class]);

        $sale->callBootModuleMorphMap();
        $stock->callBootModuleMorphMap();

        $map = Relation::morphMap();
        $this->assertSame(MorphMapFakeSaleOrder::class, $map['sale_order']);
        $this->assertSame(MorphMapFakeProduct::class, $map['product']);
    }

    public function test_registration_does_not_wipe_preexisting_aliases(): void
    {
        Relation::morphMap(['legacy' => MorphMapFakeLegacy::class]);

        $module = $this->makeModule(['sale_order' => MorphMapFakeSaleOrder::class]);
        $module->callBootModuleMorphMap();

        $map = Relation::morphMap();
        $this->assertSame(MorphMapFakeLegacy::class, $map['legacy']);
        $this->assertSame(MorphMapFakeSaleOrder::class, $map['sale_order']);
    }

    /**
     * @param  array<string, class-string<Model>>  $aliases
     */
    private function makeModule(array $aliases): MorphMapTraitConsumer
    {
        return new MorphMapTraitConsumer($aliases);
    }
}

/**
 * @internal Concrete fixture that mounts {@see RegistersModuleMorphMap} so
 * tests have a typed handle on the trait's behaviour.
 */
class MorphMapTraitConsumer
{
    use RegistersModuleMorphMap;

    /**
     * @param  array<string, class-string<Model>>  $aliases
     */
    public function __construct(private array $aliases) {}

    protected function morphMap(): array
    {
        return $this->aliases;
    }

    public function callBootModuleMorphMap(): void
    {
        $this->bootModuleMorphMap();
    }
}

class MorphMapFakeSaleOrder extends Model {}
class MorphMapFakeProduct extends Model {}
class MorphMapFakeLegacy extends Model {}
