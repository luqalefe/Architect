<?php

declare(strict_types=1);

namespace Modules\Crm\Infrastructure\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use LaravelModulesArch\Concerns\RegistersModuleMorphMap;

class CrmServiceProvider extends ServiceProvider
{
    use RegistersModuleMorphMap;

    public function register(): void
    {
        // @arch-bindings-start
        // @arch-bindings-end
    }

    public function boot(): void
    {
        $this->bootModuleMorphMap();

        // @arch-listeners-start
        // @arch-listeners-end
    }

    /**
     * @return array<string, class-string<Model>>
     */
    protected function morphMap(): array
    {
        return [
            // 'crm_lead' => Modules\Crm\Infrastructure\Persistence\Models\Lead::class,
        ];
    }
}
