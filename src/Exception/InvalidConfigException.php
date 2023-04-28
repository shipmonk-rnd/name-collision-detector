<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision\Exception;

use Throwable;

class InvalidConfigException extends RuntimeException
{

    public function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct($reason, $previous);
    }

}
