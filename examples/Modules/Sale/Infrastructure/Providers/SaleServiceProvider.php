<?php

declare(strict_types=1);

namespace Modules\Sale\Infrastructure\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LaravelModulesArch\Concerns\RegistersModuleMorphMap;
use Modules\Crm\Contracts\Events\LeadConverted;
use Modules\Sale\Domain\Repositories\SaleOrderRepositoryInterface;
use Modules\Sale\Infrastructure\ACL\HandleLeadConverted;
use Modules\Sale\Infrastructure\Persistence\Models\SaleOrder;
use Modules\Sale\Infrastructure\Persistence\Repositories\EloquentSaleOrderRepository;

class SaleServiceProvider extends ServiceProvider
{
    use RegistersModuleMorphMap;

    public function register(): void
    {
        // @arch-bindings-start
        $this->app->bind(
            SaleOrderRepositoryInterface::class,
            EloquentSaleOrderRepository::class,
        );
        // @arch-bindings-end
    }

    public function boot(): void
    {
        $this->bootModuleMorphMap();

        $this->loadRoutesFrom(__DIR__.'/../../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../../routes/api.php');

        // @arch-listeners-start
        Event::listen(
            LeadConverted::class,
            HandleLeadConverted::class,
        );
        // @arch-listeners-end
    }

    /**
     * @return array<string, class-string<Model>>
     */
    protected function morphMap(): array
    {
        return [
            'sale_order' => SaleOrder::class,
        ];
    }
}
