<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use JsonException;
use LogicException;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Processor as SchemaProcessor;
use Nette\Schema\ValidationException;
use ShipMonk\NameCollision\Exception\InvalidConfigException;
use function array_map;
use function dirname;
use function extension_loaded;
use function file_get_contents;
use function is_dir;
use function is_file;
use function is_readable;
use function json_decode;
use function realpath;
use const DIRECTORY_SEPARATOR;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

class DetectionConfig
{

    /**
     * @var list<string>
     */
    private $scanPaths;

    /**
     * @var list<string>
     */
    private $excludePaths;

    /**
     * @var list<string>
     */
    private $fileExtensions;

    /**
     * @var string
     */
    private $currentDirectory;

    /**
     * @var bool
     */
    private $ignoreParseFailures;

    /**
     * @param list<string> $scanPaths Absolute paths
     * @param list<string> $excludePaths Absolute paths
     * @param list<string> $fileExtensions
     * @internal only for tests
     */
    public function __construct(
        array $scanPaths,
        array $excludePaths,
        array $fileExtensions,
        string $currentDirectory,
        bool $ignoreParseFailures = false
    )
    {
        $normalizePath = static function (string $path): string {
            if (!is_dir($path) && !is_file($path)) {
                throw new LogicException("Expected absolute path of existing file or dir, '$path' found.");
            }

            $absoluteRealPath = realpath($path);

            if ($absoluteRealPath === false) {
                throw new LogicException("Unable to realpath \"$path\" even though it is existing file or dir");
            }

            return $absoluteRealPath;
        };

        $this->scanPaths = array_map($normalizePath, $scanPaths);
        $this->excludePaths = array_map($normalizePath, $excludePaths);
        $this->currentDirectory = $normalizePath($currentDirectory);
        $this->ignoreParseFailures = $ignoreParseFailures;
        $this->fileExtensions = $fileExtensions;
    }

    /**
     * @param list<string> $providedDirectories
     * @throws InvalidConfigException
     */
    public static function fromConfigFile(array $providedDirectories, string $currentDirectory, string $configFilePath): self
    {
        if (!extension_loaded('json')) {
            throw new InvalidConfigException("Json extension not loaded, unable to parse config file: $configFilePath");
        }

        if (!is_file($configFilePath)) {
            throw new InvalidConfigException("Provided config filepath is not a file: $configFilePath");
        }

        if (!is_readable($configFilePath)) {
            throw new InvalidConfigException("Provided config filepath is not readable: $configFilePath");
        }

        $configData = file_get_contents($configFilePath);

        if ($configData === false) {
            throw new InvalidConfigException("Failure while opening config file $configFilePath");
        }

        try {
            /** @throws JsonException */
            $configArray = json_decode($configData, true, 512, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidConfigException("Failure while parsing JSON in $configFilePath: {$e->getMessage()}", $e);
        }

        return self::fromConfigData($providedDirectories, $currentDirectory, dirname($configFilePath), $configArray);
    }

    /**
     * @param list<string> $providedPaths
     * @throws InvalidConfigException
     */
    public static function fromDefaults(array $providedPaths, string $currentDirectory): self
    {
        return self::fromConfigData($providedPaths, $currentDirectory, $currentDirectory, []);
    }

    /**
     * @throws InvalidConfigException
     */
    private static function joinPath(string $directory, string $path): string
    {
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $path;

        if (!is_dir($absolutePath) && !is_file($absolutePath)) {
            throw new InvalidConfigException("Provided directory to scan \"$absolutePath\" is not directory nor a file");
        }

        return $absolutePath;
    }

    /**
     * @param list<string> $providedDirectories
     * @param mixed $configData
     * @throws InvalidConfigException
     */
    private static function fromConfigData(
        array $providedDirectories,
        string $currentDirectory,
        string $configFileDirectory,
        $configData
    ): self
    {
        try {
            $processor = new SchemaProcessor();

            /** @var array{scanPaths: list<string>, excludePaths: list<string>, fileExtensions: list<string>, ignoreParseFailures: bool} $normalizedConfig */
            $normalizedConfig = $processor->process(self::getConfigSchema(), $configData);
        } catch (ValidationException $e) {
            throw new InvalidConfigException('Parsing json config failed with: ' . $e->getMessage(), $e);
        }

        $absoluteScanPaths = [];
        $absoluteExcludePaths = [];

        $sourcePaths = $providedDirectories === []
            ? $normalizedConfig['scanPaths']
            : $providedDirectories;
        $pathDirectory = $providedDirectories === []
            ? $configFileDirectory
            : $currentDirectory;

        foreach ($sourcePaths as $paths) {
            $absoluteScanPaths[] = self::joinPath($pathDirectory, $paths);
        }

        foreach ($normalizedConfig['excludePaths'] as $paths) {
            $absoluteExcludePaths[] = self::joinPath($configFileDirectory, $paths);
        }

        return new self(
            $absoluteScanPaths,
            $absoluteExcludePaths,
            $normalizedConfig['fileExtensions'],
            $currentDirectory,
            $normalizedConfig['ignoreParseFailures'],
        );
    }

    /**
     * @return list<string>
     */
    public function getScanPaths(): array
    {
        return $this->scanPaths;
    }

    /**
     * @return list<string>
     */
    public function getExcludePaths(): array
    {
        return $this->excludePaths;
    }

    public function getCurrentDirectory(): string
    {
        return $this->currentDirectory;
    }

    public function shouldIgnoreParseFailures(): bool
    {
        return $this->ignoreParseFailures;
    }

    /**
     * @return list<string>
     */
    public function getFileExtensions(): array
    {
        return $this->fileExtensions;
    }

    private static function getConfigSchema(): Structure
    {
        return Expect::structure([
            'ignoreParseFailures' => Expect::bool()->default(false),
            'scanPaths' => Expect::listOf(Expect::string())->mergeDefaults(false)->default([]),
            'excludePaths' => Expect::listOf(Expect::string())->mergeDefaults(false)->default([]),
            'fileExtensions' => Expect::listOf(Expect::string())->mergeDefaults(false)->default(['php']),
        ])->castTo('array');
    }

}
