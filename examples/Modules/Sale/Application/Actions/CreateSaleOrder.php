<?php

declare(strict_types=1);

namespace Modules\Sale\Application\Actions;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\Sale\Application\DTOs\CreateSaleOrderData;
use Modules\Sale\Domain\Entities\SaleOrder;
use Modules\Sale\Domain\Enums\OrderStatus;
use Modules\Sale\Domain\Repositories\SaleOrderRepositoryInterface;
use Modules\Sale\Domain\ValueObjects\OrderTotal;

/**
 * Application Service / Use Case: CreateSaleOrder.
 *
 * Coordinates the operation:
 *   1. Materializes a SaleOrder entity (invariants validated in constructors).
 *   2. Persists via the Domain repository interface (impl lives in Infra).
 *   3. Drains the entity's domain events and re-publishes them.
 *
 * No business rules here — those live in the entity / value objects.
 */
final class CreateSaleOrder
{
    public function __construct(
        private readonly SaleOrderRepositoryInterface $repository,
    ) {}

    public function handle(CreateSaleOrderData $data): SaleOrder
    {
        $order = new SaleOrder(
            id: (string) Str::uuid(),
            customerId: $data->customerId,
            status: OrderStatus::Draft,
            total: new OrderTotal($data->totalAmount),
            createdAt: new DateTimeImmutable,
        );

        $this->repository->save($order);

        foreach ($order->pullDomainEvents() as $event) {
            event($event);
        }

        return $order;
    }
}
