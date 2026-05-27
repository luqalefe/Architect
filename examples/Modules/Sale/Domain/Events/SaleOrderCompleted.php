<?php

declare(strict_types=1);

namespace Modules\Sale\Domain\Events;

/**
 * Domain Event: SaleOrderCompleted (INTERNAL).
 *
 * Recorded by the SaleOrder entity when it transitions to Completed; the
 * Application layer drains the queue and re-publishes a parallel public
 * Integration Event (see Modules\Sale\Contracts\Events\SaleOrderCompleted).
 */
final readonly class SaleOrderCompleted
{
    public function __construct(
        public string $orderId,
        public float $total,
    ) {}
}
