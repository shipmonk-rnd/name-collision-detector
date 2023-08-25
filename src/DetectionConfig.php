<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use JsonException;
use LogicException;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Processor as SchemaProcessor;
use Nette\Schema\ValidationException;
use ShipMonk\NameCollision\Exception\InvalidConfigException;
use function extension_loaded;
use function file_get_contents;
use function is_dir;
use function is_file;
use function is_readable;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function realpath;
use const DIRECTORY_SEPARATOR;
use const JSON_ERROR_NONE;
use const JSON_PRESERVE_ZERO_FRACTION;

class DetectionConfig
{

    /**
     * @var list<string>
     */
    private $scanPaths;

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
     * @param list<string> $scanPaths Relative to $currentDirectory
     * @param list<string> $fileExtensions
     * @throws InvalidConfigException
     */
    public function __construct(
        array $scanPaths,
        array $fileExtensions,
        string $currentDirectory,
        bool $ignoreParseFailures = false
    )
    {
        if ($scanPaths === []) {
            throw new InvalidConfigException('At least one directory to scan must be provided.');
        }

        $absoluteScanPaths = [];

        foreach ($scanPaths as $scanPath) {
            $absolutePath = $currentDirectory . DIRECTORY_SEPARATOR . $scanPath;

            if (!is_dir($absolutePath) && !is_file($absolutePath)) {
                throw new InvalidConfigException("Provided directory to scan \"$absolutePath\" is not directory nor a file");
            }

            $absoluteRealPath = realpath($absolutePath);
            if ($absoluteRealPath === false) {
                throw new LogicException("Unable to realpath \"$absolutePath\" even though it is existing file or dir");
            }

            $absoluteScanPaths[] = $absoluteRealPath;
        }

        $this->scanPaths = $absoluteScanPaths;
        $this->currentDirectory = $currentDirectory;
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
            $configArray = json_decode($configData, true, JSON_PRESERVE_ZERO_FRACTION | 4194304); // throw on error if available
        } catch (JsonException $e) {
            throw new InvalidConfigException("Failure while parsing JSON in $configFilePath: {$e->getMessage()}", $e);
        }

        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            throw new InvalidConfigException("Failure while parsing JSON in $configFilePath: " . json_last_error_msg());
        }

        return self::fromConfigData($providedDirectories, $currentDirectory, $configArray);
    }

    /**
     * @param list<string> $providedDirectories
     * @throws InvalidConfigException
     */
    public static function fromDefaults(array $providedDirectories, string $currentDirectory): self
    {
        return self::fromConfigData($providedDirectories, $currentDirectory, []);
    }

    /**
     * @param list<string> $providedDirectories
     * @param mixed $configData
     * @throws InvalidConfigException
     */
    private static function fromConfigData(array $providedDirectories, string $currentDirectory, $configData): self
    {
        try {
            $processor = new SchemaProcessor();

            /** @var array{scanPaths: list<string>, fileExtensions: list<string>, ignoreParseFailures: bool} $normalizedConfig */
            $normalizedConfig = $processor->process(self::getConfigSchema(), $configData);
        } catch (ValidationException $e) {
            throw new InvalidConfigException($e->getMessage(), $e);
        }

        return new self(
            $providedDirectories === [] ? $normalizedConfig['scanPaths'] : $providedDirectories,
            $normalizedConfig['fileExtensions'],
            $currentDirectory,
            $normalizedConfig['ignoreParseFailures']
        );
    }

    /**
     * @return list<string>
     */
    public function getScanPaths(): array
    {
        return $this->scanPaths;
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
            'fileExtensions' => Expect::listOf(Expect::string())->mergeDefaults(false)->default(['.php']),
        ])->castTo('array');
    }

}
