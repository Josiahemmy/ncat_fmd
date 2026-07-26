<?php

namespace App\Exceptions\Shipping;

use RuntimeException;

/**
 * Thrown when something tries to edit or delete a posted shipment event. The
 * log is append-only; a correction is a new event, not a rewrite.
 */
class ShipmentEventImmutableException extends RuntimeException
{
}
