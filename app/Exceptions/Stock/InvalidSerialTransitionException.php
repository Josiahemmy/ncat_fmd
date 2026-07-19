<?php

namespace App\Exceptions\Stock;

/** A part serial was asked to move between two states that isn't allowed. */
class InvalidSerialTransitionException extends StockException
{
}
