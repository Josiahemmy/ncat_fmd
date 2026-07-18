<?php

namespace App\Exceptions\Stock;

use RuntimeException;

/** Base for all stock-engine rule violations (mapped to HTTP 422). */
class StockException extends RuntimeException
{
}
