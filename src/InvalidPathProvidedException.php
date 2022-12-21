<?php declare(strict_types = 1);

namespace ShipMonk;

use RuntimeException;
use Throwable;

class InvalidPathProvidedException extends RuntimeException
{

    public function __construct(Throwable $previous)
    {
        parent::__construct($previous->getMessage(), 0, $previous);
    }

}
