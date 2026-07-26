<?php

namespace App\Exceptions;

/**
 * Marks an exception as a business-rule refusal rather than a fault.
 *
 * A refusal means the request was understood and deliberately declined: closing
 * a draft order, issuing more than is on hand, editing a posted event. These
 * are the engine working, so they must reach the clerk as a readable reason,
 * not as a 500. `bootstrap/app.php` renders anything carrying this marker as a
 * flashed error (Inertia) or HTTP 422 (everything else).
 *
 * Refusals about one line of a document should throw ValidationException with
 * the field path instead, so the message renders against that line.
 */
interface DomainRefusal
{
}
