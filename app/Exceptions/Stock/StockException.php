<?php

namespace App\Exceptions\Stock;

use App\Exceptions\DomainRefusal;
use RuntimeException;

/** Base for all stock-engine rule violations (mapped to HTTP 422). */
class StockException extends RuntimeException implements DomainRefusal
{
}
