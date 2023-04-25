<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision\Exception;

class InvalidPathProvidedException extends RuntimeException
{

    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }

}
