<?php

declare(strict_types=1);

namespace Modules\Sale\Domain\Repositories;

use Modules\Sale\Domain\Entities\SaleOrder;

/**
 * Persistence contract for SaleOrder. Lives in Domain so the entity never
 * depends on the underlying ORM; the Eloquent implementation lives in
 * Infrastructure/Persistence/Repositories.
 */
interface SaleOrderRepositoryInterface
{
    public function findById(string $id): ?SaleOrder;

    public function save(SaleOrder $order): void;

    public function delete(string $id): void;

    /**
     * @return list<SaleOrder>
     */
    public function findByCustomer(string $customerId): array;
}
