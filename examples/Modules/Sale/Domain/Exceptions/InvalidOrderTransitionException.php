<?php

declare(strict_types=1);

namespace Modules\Sale\Domain\Exceptions;

use DomainException;
use Modules\Sale\Domain\Enums\OrderStatus;

/**
 * Signals an attempted illegal SaleOrder state transition.
 *
 * Named constructors keep the call site readable:
 *   throw InvalidOrderTransitionException::cannotComplete($current);
 */
final class InvalidOrderTransitionException extends DomainException
{
    public static function cannotComplete(OrderStatus $current): self
    {
        return new self(sprintf(
            "Cannot complete a SaleOrder in status '%s'. Only 'pending' orders can be completed.",
            $current->value,
        ));
    }

    public static function cannotCancel(OrderStatus $current): self
    {
        return new self(sprintf(
            "Cannot cancel a SaleOrder in status '%s'. Completed orders are final.",
            $current->value,
        ));
    }
}
