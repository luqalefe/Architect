<?php

declare(strict_types=1);

namespace Modules\Sale\Application\Validators;

/**
 * Application-level validation rules for CreateSaleOrder (depend on
 * framework concerns like exists:table; domain invariants like
 * "total >= 0" live in the OrderTotal value object instead).
 */
final class CreateSaleOrderRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
