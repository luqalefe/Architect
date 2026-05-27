<?php

declare(strict_types=1);

namespace Modules\Sale\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: OrderTotal
 *
 * Immutable money-like value. Validates non-negativity on construction;
 * every transformation returns a new instance.
 */
final readonly class OrderTotal
{
    public function __construct(
        public float $value,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "OrderTotal cannot be negative. Got: {$value}",
            );
        }
    }

    public function withDiscount(float $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException(
                "Discount percentage must be 0..100. Got: {$percentage}",
            );
        }

        return new self($this->value * (1 - $percentage / 100));
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < PHP_FLOAT_EPSILON;
    }
}
