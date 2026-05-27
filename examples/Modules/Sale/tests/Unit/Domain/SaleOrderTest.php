<?php

declare(strict_types=1);

namespace Modules\Sale\Tests\Unit\Domain;

use DateTimeImmutable;
use Modules\Sale\Domain\Entities\SaleOrder;
use Modules\Sale\Domain\Enums\OrderStatus;
use Modules\Sale\Domain\Events\SaleOrderCompleted;
use Modules\Sale\Domain\Exceptions\InvalidOrderTransitionException;
use Modules\Sale\Domain\ValueObjects\OrderTotal;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain test — extends PHPUnit's TestCase directly (NOT Laravel's),
 * confirming the entity can be exercised without ANY framework boot.
 */
class SaleOrderTest extends TestCase
{
    public function test_pending_order_can_be_completed_and_records_a_domain_event(): void
    {
        $order = $this->makeOrder(status: OrderStatus::Pending);

        $order->complete();

        $this->assertSame(OrderStatus::Completed, $order->status());
        $events = $order->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SaleOrderCompleted::class, $events[0]);
    }

    public function test_draft_order_cannot_be_completed(): void
    {
        $order = $this->makeOrder(status: OrderStatus::Draft);

        $this->expectException(InvalidOrderTransitionException::class);

        $order->complete();
    }

    public function test_completed_order_cannot_be_cancelled(): void
    {
        $order = $this->makeOrder(status: OrderStatus::Pending);
        $order->complete();

        $this->expectException(InvalidOrderTransitionException::class);

        $order->cancel();
    }

    public function test_apply_discount_reduces_the_total_via_value_object(): void
    {
        $order = $this->makeOrder(total: 100.0);

        $order->applyDiscount(10.0);

        $this->assertSame(90.0, $order->total()->value);
    }

    public function test_pull_domain_events_clears_the_queue(): void
    {
        $order = $this->makeOrder(status: OrderStatus::Pending);
        $order->complete();

        $first = $order->pullDomainEvents();
        $second = $order->pullDomainEvents();

        $this->assertCount(1, $first);
        $this->assertSame([], $second);
    }

    private function makeOrder(
        OrderStatus $status = OrderStatus::Draft,
        float $total = 100.0,
    ): SaleOrder {
        return new SaleOrder(
            id: 'order-1',
            customerId: 'customer-1',
            status: $status,
            total: new OrderTotal($total),
            createdAt: new DateTimeImmutable('2026-01-01'),
        );
    }
}
