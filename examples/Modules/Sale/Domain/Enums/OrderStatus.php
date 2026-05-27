<?php

declare(strict_types=1);

namespace Modules\Sale\Domain\Enums;

/**
 * Domain Enum: OrderStatus
 *
 * Finite SaleOrder lifecycle state with valid transitions declared inline.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Pending, self::Cancelled], true),
            self::Pending => in_array($target, [self::Completed, self::Cancelled], true),
            self::Completed => false,
            self::Cancelled => false,
        };
    }
}
