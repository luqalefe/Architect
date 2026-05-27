<?php

declare(strict_types=1);

namespace Modules\Sale\Application\ViewModels;

use Modules\Sale\Domain\Entities\SaleOrder;
use Modules\Sale\Domain\Enums\OrderStatus;

/**
 * ViewModel: SaleOrderIndexViewModel.
 *
 * Prepares SaleOrder data for presentation. Exposes methods (not raw
 * properties) so the view consumes a stable contract while the entity
 * keeps evolving freely.
 */
final class SaleOrderIndexViewModel
{
    /**
     * @param  list<SaleOrder>  $orders
     */
    public function __construct(
        private readonly array $orders,
    ) {}

    /**
     * @return list<array{id: string, total: string, status: string, badge: string}>
     */
    public function orders(): array
    {
        return array_map(fn (SaleOrder $order) => [
            'id' => $order->id(),
            'total' => 'R$ '.number_format($order->total()->value, 2, ',', '.'),
            'status' => $order->status()->value,
            'badge' => $this->statusBadge($order->status()),
        ], $this->orders);
    }

    public function totalCount(): int
    {
        return count($this->orders);
    }

    public function hasOrders(): bool
    {
        return $this->totalCount() > 0;
    }

    private function statusBadge(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft => 'secondary',
            OrderStatus::Pending => 'warning',
            OrderStatus::Completed => 'success',
            OrderStatus::Cancelled => 'danger',
        };
    }
}
