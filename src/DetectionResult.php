<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

class DetectionResult
{

    /**
     * @var int
     */
    private $analysedFilesCount;

    /**
     * @var int
     */
    private $excludedFilesCount;

    /**
     * @var array<string, list<FileLine>>
     */
    private $collisions;

    /**
     * @param array<string, list<FileLine>> $collisions
     */
    public function __construct(int $analysedFilesCount, int $excludedFilesCount, array $collisions)
    {
        $this->analysedFilesCount = $analysedFilesCount;
        $this->excludedFilesCount = $excludedFilesCount;
        $this->collisions = $collisions;
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
