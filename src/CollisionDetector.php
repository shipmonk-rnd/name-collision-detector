<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use DirectoryIterator;
use Generator;
use LogicException;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ShipMonk\NameCollision\Exception\FileParsingException;
use function count;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_file;
use function ksort;
use function preg_quote;
use function preg_replace;
use function strlen;
use function strpos;
use function substr;
use function token_get_all;
use function token_name;
use function usort;
use const PHP_VERSION_ID;
use const T_CLASS;
use const T_COMMENT;
use const T_CONST;
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_ENUM;
use const T_FUNCTION;
use const T_INTERFACE;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;
use const TOKEN_PARSE;

class CollisionDetector
{

    const TYPE_GROUP_CLASS = 'class';
    const TYPE_GROUP_FUNCTION = 'function';
    const TYPE_GROUP_CONSTANT = 'const';

    /**
     * @var DetectionConfig
     */
    private $config;

    public function __construct(DetectionConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @throws FileParsingException
     */
    public function getCollidingTypes(): DetectionResult
    {
        $groups = [
            self::TYPE_GROUP_CLASS,
            self::TYPE_GROUP_FUNCTION,
            self::TYPE_GROUP_CONSTANT,
        ];
        $types = [];
        $filesAnalysed = 0;
        $filesExcluded = 0;

        foreach ($this->config->getScanPaths() as $scanPath) {
            foreach ($this->listPhpFilesIn($scanPath) as $filePath) {
                if ($this->isExcluded($filePath)) {
                    $filesExcluded++;
                    continue;
                }

                try {
                    foreach ($this->getTypesInFile($filePath) as $group => $classes) {
                        foreach ($classes as ['line' => $line, 'name' => $class]) {
                            $types[$group][$class][] = new FileLine($this->stripCwdFromPath($filePath), $line);
                        }
                    }
                } catch (FileParsingException $e) {
                    if ($this->config->shouldIgnoreParseFailures()) {
                        $filesExcluded++;
                        continue;
                    }

                    throw $e;
                }

                $filesAnalysed++;
            }
        }

        $collidingTypes = [];

        foreach ($groups as $group) {
            $classToFilesMap = $types[$group] ?? [];
            ksort($classToFilesMap);

            foreach ($classToFilesMap as $className => $fileLines) {
                if (count($fileLines) > 1) {
                    usort($fileLines, static function (FileLine $a, FileLine $b): int {
                        $pathDiff = $a->getFilePath() <=> $b->getFilePath();

                        if ($pathDiff === 0) {
                            return $a->getLine() <=> $b->getLine();
                        }

                        return $pathDiff;
                    });

                    $collidingTypes[$className] = $fileLines;
                }
            }
        }

        return new DetectionResult(
            $filesAnalysed,
            $filesExcluded,
            $collidingTypes
        );
    }

    private function stripCwdFromPath(string $path): string
    {
        $cwdForRegEx = preg_quote($this->config->getCurrentDirectory(), '~');
        $replacedFileName = preg_replace("~^{$cwdForRegEx}~", '', $path);

        if ($replacedFileName === null) {
            throw new LogicException('Invalid regex, should not happen');
        }

        return $replacedFileName;
    }

    /**
     * Searches enums, classes, interfaces, constants, functions and traits in PHP file.
     * Based on Nette\Loaders\RobotLoader::scanPhp
     *
     * @return array<self::TYPE_GROUP_*, list<array{line: int, name: string}>>
     *
     * @license https://github.com/nette/robot-loader/blob/v3.4.0/license.md
     * @throws FileParsingException
     */
    private function getTypesInFile(string $file): array
    {
        $code = file_get_contents($file);

        if ($code === false) {
            throw new FileParsingException("Unable to get contents of $file");
        }

        $line = -1;
        $expected = null;
        $namespace = $name = '';
        $level = $minLevel = 0;
        $types = [];

        try {
            /** @throws ParseError */
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw new FileParsingException("Unable to parse $file: " . $e->getMessage(), $e);
        }

        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                    case T_WHITESPACE:
                        continue 2;

                    case T_STRING:
                    case PHP_VERSION_ID < 80000 ? T_NS_SEPARATOR : T_NAME_QUALIFIED:
                        if ($expected !== null) {
                            $name .= $token[1];
                        }

                        continue 2;

                    case T_CONST:
                    case T_FUNCTION:
                    case T_NAMESPACE:
                    case T_CLASS:
                    case T_INTERFACE:
                    case T_TRAIT:
                    case PHP_VERSION_ID < 80100 ? T_CLASS : T_ENUM:
                        if (
                            ($token[0] === T_FUNCTION || $token[0] === T_CONST)
                            && $this->isWithinUseStatement($tokens, $index)
                        ) {
                            break;
                        }

                        $expected = $token[0];
                        $line = $token[2];
                        $name = '';
                        continue 2;

                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                        $level++;
                }
            }

            if ($expected !== null) {
                if ($expected === T_NAMESPACE) {
                    $namespace = $name !== '' ? $name . '\\' : '';
                    $minLevel = $token === '{' ? 1 : 0;

                } elseif ($name !== '' && $level === $minLevel) {
                    $types[$this->detectGroupType($expected)][] = ['line' => $line, 'name' => $namespace . $name];
                }

                $expected = null;
            }

            if ($token === '{') {
                $level++;
            } elseif ($token === '}') {
                $level--;
            }
        }

        return $types;
    }

    /**
     * @return Generator<string>
     */
    private function listPhpFilesIn(string $path): Generator
    {
        if (is_file($path) && $this->isExtensionToCheck($path)) {
            yield $path;
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $entry) {
            /** @var DirectoryIterator $entry */
            if (!$entry->isFile() || !$this->isExtensionToCheck($entry->getFilename())) {
                continue;
            }

            yield $entry->getPathname();
        }
    }

    private function isExtensionToCheck(string $filePath): bool
    {
        foreach ($this->config->getFileExtensions() as $extension) {
            if (substr($filePath, -(strlen($extension) + 1)) === ".$extension") {
                return true;
            }
        }

        return false;
    }

    /**
     * Helps to prevent detecting use statements as function/const definitions
     * - "use function fn"
     * - "use const FOO"
     *
     * Use statement with braces "use Foo\{ function fn }" is filtered out by $level === $minLevel condition above
     *
     * @param mixed[] $tokens
     */
    private function isWithinUseStatement(
        array $tokens,
        int $index
    ): bool
    {
        do {
            $previousToken = $tokens[--$index];

            if (!is_array($previousToken)) {
                return false;
            }

            if ($previousToken[0] === T_USE) {
                return true;
            }
        } while (in_array($previousToken[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true));

        return false;
    }

    /**
     * @return self::TYPE_GROUP_*
     */
    private function detectGroupType(int $tokenId): string
    {
        switch ($tokenId) {
            case PHP_VERSION_ID < 80100 ? T_CLASS : T_ENUM:
            case T_CLASS:
            case T_TRAIT:
            case T_INTERFACE:
                return self::TYPE_GROUP_CLASS;

            case T_FUNCTION:
                return self::TYPE_GROUP_FUNCTION;

            case T_CONST:
                return self::TYPE_GROUP_CONSTANT;

            default:
                throw new LogicException("Unexpected token #$tokenId: " . token_name($tokenId));
        }
    }

    private function isExcluded(string $filePath): bool
    {
        foreach ($this->config->getExcludePaths() as $excludePath) {
            if (strpos($filePath, $excludePath) !== false) {
                return true;
            }
        }

        return false;
    }

}
