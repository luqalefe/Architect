<?php

declare(strict_types=1);

namespace Modules\Sale\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Modules\Sale\Domain\Entities\SaleOrder as SaleOrderEntity;
use Modules\Sale\Domain\Enums\OrderStatus;
use Modules\Sale\Domain\Repositories\SaleOrderRepositoryInterface;
use Modules\Sale\Domain\ValueObjects\OrderTotal;
use Modules\Sale\Infrastructure\Persistence\Models\SaleOrder as SaleOrderModel;

/**
 * Eloquent implementation of {@see SaleOrderRepositoryInterface}. The only
 * place that knows how the entity maps to columns — swap the ORM, only this
 * file changes.
 */
final class EloquentSaleOrderRepository implements SaleOrderRepositoryInterface
{
    public function findById(string $id): ?SaleOrderEntity
    {
        $model = SaleOrderModel::find($id);

        return $model ? $this->toDomainEntity($model) : null;
    }

    public function save(SaleOrderEntity $order): void
    {
        SaleOrderModel::query()->updateOrCreate(
            ['id' => $order->id()],
            [
                'customer_id' => $order->customerId(),
                'status' => $order->status()->value,
                'total' => $order->total()->value,
            ],
        );
    }

    public function delete(string $id): void
    {
        SaleOrderModel::destroy($id);
    }

    public function findByCustomer(string $customerId): array
    {
        return SaleOrderModel::query()
            ->where('customer_id', $customerId)
            ->get()
            ->map(fn (SaleOrderModel $model) => $this->toDomainEntity($model))
            ->all();
    }

    private function toDomainEntity(SaleOrderModel $model): SaleOrderEntity
    {
        return new SaleOrderEntity(
            id: $model->getId(),
            customerId: (string) $model->getAttribute('customer_id'),
            status: OrderStatus::from($model->getStatus()),
            total: new OrderTotal($model->getTotal()),
            createdAt: new DateTimeImmutable((string) $model->getAttribute('created_at')),
        );
    }
}
