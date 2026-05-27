<?php

declare(strict_types=1);

namespace Modules\Sale\Contracts;

/**
 * Public read-only contract over a SaleOrder. Other modules MAY depend on
 * this interface; they MUST NOT touch Domain\Entities\SaleOrder directly
 * (caught by R1 in arch:check-boundaries).
 */
interface SaleOrderContract
{
    public function getId(): string;

    public function getStatus(): string;

    public function getTotal(): float;
}
