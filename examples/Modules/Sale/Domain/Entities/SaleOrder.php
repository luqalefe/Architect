<?php

declare(strict_types=1);

namespace Modules\Sale\Domain\Entities;

use DateTimeImmutable;
use Modules\Sale\Domain\Enums\OrderStatus;
use Modules\Sale\Domain\Events\SaleOrderCompleted;
use Modules\Sale\Domain\Exceptions\InvalidOrderTransitionException;
use Modules\Sale\Domain\ValueObjects\OrderTotal;

/**
 * Domain Entity: SaleOrder.
 *
 * Encapsulates every transition rule on a sale (complete/cancel/discount).
 * Pure PHP — no Illuminate, no persistence concerns. The Repository in
 * Infrastructure is responsible for hydration; the Application layer drains
 * domain events via pullDomainEvents() after persisting.
 */
final class SaleOrder
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly string $id,
        private readonly string $customerId,
        private OrderStatus $status,
        private OrderTotal $total,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function total(): OrderTotal
    {
        return $this->total;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function complete(): void
    {
        if (! $this->status->canTransitionTo(OrderStatus::Completed)) {
            throw InvalidOrderTransitionException::cannotComplete($this->status);
        }

        $this->status = OrderStatus::Completed;

        $this->recordEvent(new SaleOrderCompleted(
            orderId: $this->id,
            total: $this->total->value,
        ));
    }

    public function cancel(): void
    {
        if (! $this->status->canTransitionTo(OrderStatus::Cancelled)) {
            throw InvalidOrderTransitionException::cannotCancel($this->status);
        }

        $this->status = OrderStatus::Cancelled;
    }

    public function applyDiscount(float $percentage): void
    {
        $this->total = $this->total->withDiscount($percentage);
    }

    /**
     * @return array<int, object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
