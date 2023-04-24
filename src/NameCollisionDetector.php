<?php declare(strict_types = 1);

namespace ShipMonk;

use DirectoryIterator;
use Generator;
use LogicException;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function count;
use function file_get_contents;
use function is_array;
use function is_dir;
use function ksort;
use function preg_quote;
use function preg_replace;
use function sort;
use function substr;
use function token_get_all;
use const PHP_VERSION_ID;
use const T_CLASS;
use const T_COMMENT;
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_TRAIT;
use const T_WHITESPACE;
use const TOKEN_PARSE;

class NameCollisionDetector
{

    /**
     * @var list<string>
     */
    private $directories;

    /**
     * @var string|null
     */
    private $cwd;

    /**
     * @param list<string> $directories
     * @param string|null $cwd Path prefix to strip
     * @throws InvalidPathProvidedException
     */
    public function __construct(array $directories, ?string $cwd = null)
    {
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                throw new InvalidPathProvidedException("Path \"$directory\" is not directory");
            }
        }

        $this->directories = $directories;
        $this->cwd = $cwd;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCollidingClasses(): array
    {
        $classToFilesMap = [];

        foreach ($this->directories as $directory) {
            foreach ($this->listPhpFilesIn($directory) as $filePath) {
                foreach ($this->getClassesInFile($filePath) as $class) {
                    $classToFilesMap[$class][] = $this->normalizePath($filePath);
                }
            }
        }

        ksort($classToFilesMap);

        foreach ($classToFilesMap as $className => $fileNames) {
            if (count($fileNames) === 1) {
                unset($classToFilesMap[$className]);
            } else {
                sort($fileNames);
                $classToFilesMap[$className] = $fileNames;
            }
        }

        return $classToFilesMap;
    }

    private function normalizePath(string $path): string
    {
        if ($this->cwd !== null) {
            $cwdForRegEx = preg_quote($this->cwd, '~');
            $replacedFileName = preg_replace("~^{$cwdForRegEx}~", '', $path);

            if ($replacedFileName === null) {
                throw new LogicException('Invalid regex, should not happen');
            }

            return $replacedFileName;
        }

        return $path;
    }

    /**
     * Searches classes, interfaces and traits in PHP file.
     * Based on Nette\Loaders\RobotLoader::scanPhp
     *
     * @license https://github.com/nette/robot-loader/blob/v3.4.0/license.md
     * @return list<string>
     */
    private function getClassesInFile(string $file): array
    {
        $code = file_get_contents($file);

        if ($code === false) {
            throw new FileParsingException("Unable to get contents of $file");
        }

        $expected = false;
        $namespace = $name = '';
        $level = $minLevel = 0;
        $classes = [];

        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw new FileParsingException("Unable to parse $file: " . $e->getMessage(), $e);
        }

        foreach ($tokens as $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                    case T_WHITESPACE:
                        continue 2;

                    case T_STRING:
                    case PHP_VERSION_ID < 80000 ? T_NS_SEPARATOR : T_NAME_QUALIFIED:
                        if ($expected !== null && $expected !== false) {
                            $name .= $token[1];
                        }

                        continue 2;

                    case T_NAMESPACE:
                    case T_CLASS:
                    case T_INTERFACE:
                    case T_TRAIT:
                    case PHP_VERSION_ID < 80100 ? T_CLASS : T_ENUM:
                        $expected = $token[0];
                        $name = '';
                        continue 2;

                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                        $level++;
                }
            }

            if ($expected !== null && $expected !== false) {
                if ($expected === T_NAMESPACE) {
                    $namespace = $name !== '' ? $name . '\\' : '';
                    $minLevel = $token === '{' ? 1 : 0;
                } elseif ($name !== '' && $level === $minLevel) {
                    $classes[] = $namespace . $name;
                }

                $expected = null;
            }

            if ($token === '{') {
                $level++;
            } elseif ($token === '}') {
                $level--;
            }
        }

        return $classes;
    }

    /**
     * @return Generator<string>
     */
    private function listPhpFilesIn(string $directory): Generator
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $entry) {
            /** @var DirectoryIterator $entry */
            if (!$entry->isFile() || !$entry->isReadable() || substr($entry->getFilename(), -4) !== '.php') {
                continue;
            }

            yield $entry->getPathname();
        }
    }

}
