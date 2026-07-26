<?php

namespace App\Exceptions\Shipping;

use App\Exceptions\DomainRefusal;
use RuntimeException;

/**
 * Thrown when something tries to append to a closed shipment.
 *
 * Closing finalises the timeline. It is not a delete and not a rewrite: the
 * correction path is to re-open the shipment, add the event that puts the
 * record straight, and close it again, which leaves both the re-open and the
 * new event in the trail.
 *
 * This exists because a closed shipment was the only finalised document in the
 * system without a lock. Posted SRVs and SIVs are immutable, issued orders
 * freeze, and the stock ledger is append-only.
 */
class ShipmentClosedException extends RuntimeException implements DomainRefusal
{
    public static function forAppend(string $reference): self
    {
        return new self(
            "Shipment {$reference} is closed, so no further entries can be recorded against it. "
            .'Re-open it, add the entry, then close it again.'
        );
    }
}
