<?php declare(strict_types = 1);

namespace ShipMonk;

use RuntimeException;
use Throwable;

class FileParsingException extends RuntimeException
{

    public function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct($reason, 0, $previous);
    }

}
