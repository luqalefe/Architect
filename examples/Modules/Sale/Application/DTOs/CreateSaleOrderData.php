<?php

declare(strict_types=1);

namespace Modules\Sale\Application\DTOs;

use Illuminate\Http\Request;

/**
 * Data Transfer Object for the CreateSaleOrder use case. Carries the
 * validated input across the HTTP/CLI/queue boundary into the Action.
 */
final readonly class CreateSaleOrderData
{
    public function __construct(
        public string $customerId,
        public float $totalAmount,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $data */
        $data = $request->all();

        return self::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            customerId: (string) ($data['customer_id'] ?? ''),
            totalAmount: (float) ($data['total_amount'] ?? 0.0),
        );
    }
}
