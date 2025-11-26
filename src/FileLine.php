<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use function str_replace;

class FileLine
{

    private string $filePath;

    private int $line;

    public function __construct(
        string $filePath,
        int $line,
    )
    {
        $this->filePath = str_replace('\\', '/', $filePath);
        $this->line = $line;
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
