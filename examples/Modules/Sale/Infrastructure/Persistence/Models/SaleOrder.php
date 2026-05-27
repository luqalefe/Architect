<?php

declare(strict_types=1);

namespace Modules\Sale\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Sale\Contracts\SaleOrderContract;

/**
 * Eloquent persistence Model — the mechanism, NOT the entity.
 *
 * Translates rows to/from the database. Implements {@see SaleOrderContract}
 * so it can be returned across module boundaries safely (other modules see
 * the public read-only interface, not this Model).
 */
class SaleOrder extends Model implements SaleOrderContract
{
    use HasUuids;

    protected $table = 'sale_orders';

    protected $fillable = [
        'customer_id',
        'status',
        'total',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function getId(): string
    {
        return (string) $this->getKey();
    }

    public function getStatus(): string
    {
        return (string) $this->getAttribute('status');
    }

    public function getTotal(): float
    {
        return (float) $this->getAttribute('total');
    }
}
