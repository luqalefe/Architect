<?php

declare(strict_types=1);

namespace Modules\Sale\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Sale\Application\Validators\CreateSaleOrderRules;

/**
 * Adapter from Laravel's HTTP layer to the application validation rules.
 * Keeps the actual rules() definition in Application/Validators so non-HTTP
 * callers (CLI, queue) can reuse them.
 */
class CreateSaleOrderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return CreateSaleOrderRules::rules();
    }
}
