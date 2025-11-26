<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

class DetectionResult
{

    /**
     * @param array<string, list<FileLine>> $collisions
     */
    public function __construct(
        private int $analysedFilesCount,
        private int $excludedFilesCount,
        private array $collisions,
    )
    {
    }

    public function getAnalysedFilesCount(): int
    {
        return $this->analysedFilesCount;
    }

    public function getExcludedFilesCount(): int
    {
        return $this->excludedFilesCount;
    }

    /**
     * @return array<string, list<FileLine>>
     */
    public function getCollisions(): array
    {
        return $this->collisions;
    }

}
