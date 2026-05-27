<?php

declare(strict_types=1);

namespace Modules\Sale\Infrastructure\ACL;

use Modules\Crm\Contracts\Events\LeadConverted;
use Modules\Sale\Application\Actions\CreateSaleOrder;
use Modules\Sale\Application\DTOs\CreateSaleOrderData;

/**
 * Anti-Corruption Layer: translates the Crm module's LeadConverted event
 * into a CreateSaleOrder call expressed in Sale's own vocabulary.
 *
 * Sale doesn't import or know anything about Crm's internal "Lead" concept —
 * only the primitives that crossed the public Contracts/Events boundary.
 *
 * @see "Domain-Driven Design" (Evans), Cap. 14 — Anti-Corruption Layer
 */
final class HandleLeadConverted
{
    public function __construct(
        private readonly CreateSaleOrder $createSaleOrder,
    ) {}

    public function handle(LeadConverted $event): void
    {
        $this->createSaleOrder->handle(CreateSaleOrderData::fromArray([
            'customer_id' => $event->customerId,
            'total_amount' => $event->estimatedValue,
        ]));
    }
}
