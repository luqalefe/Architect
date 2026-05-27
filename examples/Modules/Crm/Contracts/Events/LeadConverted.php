<?php

declare(strict_types=1);

namespace Modules\Crm\Contracts\Events;

/**
 * Integration Event: LeadConverted
 *
 * Part of the Crm module's public event API. Other modules MAY listen to this
 * event through an Anti-Corruption Layer (Infrastructure/ACL).
 *
 * @see "Implementing DDD" (Vernon), Cap. 8 — Integration Events
 */
final readonly class LeadConverted
{
    public function __construct(
        public string $leadId,
        public string $customerId,
        public float $estimatedValue,
        public string $convertedAt,
    ) {}
}
