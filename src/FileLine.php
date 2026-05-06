<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use function str_replace;

class FileLine
{

    private readonly string $filePath;

    public function __construct(
        string $filePath,
        private readonly int $line,
    )
    {
        $this->filePath = str_replace('\\', '/', $filePath);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getLine(): int
    {
        return $this->line;
    }

}
