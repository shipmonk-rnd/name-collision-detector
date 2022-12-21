<?php declare(strict_types = 1);

namespace ShipMonk;

use LogicException;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionConstant;
use Roave\BetterReflection\Reflection\ReflectionFunction;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Exception\InvalidDirectory;
use Roave\BetterReflection\SourceLocator\Exception\InvalidFileInfo;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use function count;
use function ksort;
use function preg_quote;
use function preg_replace;

class NameCollisionDetector
{

    private DefaultReflector $reflector;

    private ?string $cwd;

    /**
     * Based on: https://github.com/Roave/BetterReflection/blob/396a07c9d276cb9ffba581b24b2dadbb542d542e/demo/parsing-whole-directory/example2.php
     *
     * @param list<string> $directories
     * @param ?string $cwd Path prefix to strip
     * @throws InvalidPathProvidedException
     */
    public function __construct(array $directories, ?string $cwd = null)
    {
        try {
            $sourceLocator = new AggregateSourceLocator([
                new DirectoriesSourceLocator(
                    $directories,
                    (new BetterReflection())->astLocator(),
                ),
                new AutoloadSourceLocator((new BetterReflection())->astLocator()),
            ]);
        } catch (InvalidFileInfo | InvalidDirectory $e) {
            throw new InvalidPathProvidedException($e);
        }

        $this->reflector = new DefaultReflector($sourceLocator);
        $this->cwd = $cwd;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCollidingConstants(): array
    {
        return $this->getCollisions($this->reflector->reflectAllConstants());
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCollidingFunctions(): array
    {
        return $this->getCollisions($this->reflector->reflectAllFunctions());
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCollidingClasses(): array
    {
        return $this->getCollisions($this->reflector->reflectAllClasses());
    }

    /**
     * @param iterable<ReflectionClass>|iterable<ReflectionFunction>|iterable<ReflectionConstant> $reflections
     * @return array<string, list<string>>
     */
    private function getCollisions(iterable $reflections): array
    {
        $classToFilesMap = [];

        foreach ($reflections as $reflection) {
            $className = $reflection->getName();

            if (!isset($classToFilesMap[$className])) {
                $classToFilesMap[$className] = [];
            }

            $classToFilesMap[$className][] = $this->normalizeFileName($reflection->getFileName());
        }

        ksort($classToFilesMap);

        foreach ($classToFilesMap as $className => $fileNames) {
            if (count($fileNames) === 1) {
                unset($classToFilesMap[$className]);
            }
        }

        return $classToFilesMap;
    }

    private function normalizeFileName(?string $fileName): string
    {
        if ($fileName === null) {
            return 'unknown file';
        }

        if ($this->cwd !== null) {
            $cwdForRegEx = preg_quote($this->cwd);
            $replacedFileName = preg_replace("~^{$cwdForRegEx}~", '', $fileName);

            if ($replacedFileName === null) {
                throw new LogicException('Invalid regex, should not happen');
            }

            return $replacedFileName;
        }

        return $fileName;
    }

}
