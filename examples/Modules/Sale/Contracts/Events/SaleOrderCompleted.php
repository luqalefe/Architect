<?php

declare(strict_types=1);

namespace Modules\Sale\Contracts\Events;

/**
 * Integration Event: SaleOrderCompleted.
 *
 * Public payload (primitives only) re-published by CreateSaleOrder /
 * CompleteSaleOrder after the parallel internal Domain Event fires. Other
 * modules MAY listen to this event through an Anti-Corruption Layer.
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
