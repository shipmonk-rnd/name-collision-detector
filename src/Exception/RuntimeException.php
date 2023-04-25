<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision\Exception;

use RuntimeException as NativeRuntimeException;
use Throwable;

class RuntimeException extends NativeRuntimeException
{

    public function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct($reason, 0, $previous);
    }

}
