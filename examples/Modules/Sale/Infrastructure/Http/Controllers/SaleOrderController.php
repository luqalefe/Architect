<?php

declare(strict_types=1);

namespace Modules\Sale\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Sale\Application\Actions\CreateSaleOrder;
use Modules\Sale\Application\DTOs\CreateSaleOrderData;
use Modules\Sale\Infrastructure\Http\Requests\CreateSaleOrderRequest;

/**
 * Thin HTTP adapter — converts request → DTO, delegates to the Action,
 * shapes the response. No business logic here.
 */
class SaleOrderController
{
    public function store(CreateSaleOrderRequest $request, CreateSaleOrder $action): JsonResponse
    {
        $order = $action->handle(CreateSaleOrderData::fromRequest($request));

        return new JsonResponse([
            'id' => $order->id(),
            'status' => $order->status()->value,
            'total' => $order->total()->value,
        ], 201);
    }
}
