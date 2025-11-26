<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use function str_replace;

class FileLine
{

    private string $filePath;

    public function __construct(
        string $filePath,
        private int $line,
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
