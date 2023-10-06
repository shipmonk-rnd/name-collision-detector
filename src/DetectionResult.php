<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

class DetectionResult
{

    /**
     * @var int
     */
    private $filesScanned;

    /**
     * @var int
     */
    private $filesExcluded;

    /**
     * @var array<string, list<FileLine>>
     */
    private $collisions;

    /**
     * @param array<string, list<FileLine>> $collisions
     */
    public function __construct(int $filesScanned, int $filesExcluded, array $collisions)
    {
        $this->filesScanned = $filesScanned;
        $this->filesExcluded = $filesExcluded;
        $this->collisions = $collisions;
    }

    public function getFilesScanned(): int
    {
        return $this->filesScanned;
    }

    public function getFilesExcluded(): int
    {
        return $this->filesExcluded;
    }

    /**
     * @return array<string, list<FileLine>>
     */
    public function getCollisions(): array
    {
        return $this->collisions;
    }

}
