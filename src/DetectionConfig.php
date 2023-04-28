<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use JsonException;
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
use const JSON_ERROR_NONE;
use const JSON_PRESERVE_ZERO_FRACTION;

class DetectionConfig
{

    /**
     * @var list<string>
     */
    private $directories;

    /**
     * @var list<string>
     */
    private $extensions;

    /**
     * @var string
     */
    private $currentDirectory;

    /**
     * @var bool
     */
    private $ignoreParseFailures;

    /**
     * @param list<string> $scanDirs Relative to $currentDirectory
     * @param list<string> $extensions
     * @throws InvalidConfigException
     */
    public function __construct(
        array $scanDirs,
        array $extensions,
        string $currentDirectory,
        bool $ignoreParseFailures = false
    )
    {
        if ($scanDirs === []) {
            throw new InvalidConfigException('At least one directory to scan must be provided.');
        }

        $absoluteDirectories = [];

        foreach ($scanDirs as $directory) {
            $absoluteDirectoryPath = $currentDirectory . '/' . $directory;

            if (!is_dir($absoluteDirectoryPath)) {
                throw new InvalidConfigException("Provided directory to scan \"$absoluteDirectoryPath\" is not directory");
            }

            $absoluteDirectories[] = $absoluteDirectoryPath;
        }

        $this->directories = $absoluteDirectories;
        $this->currentDirectory = $currentDirectory;
        $this->ignoreParseFailures = $ignoreParseFailures;
        $this->extensions = $extensions;
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

            /** @var array{scanDirs: list<string>, extensions: list<string>, ignoreParseFailures: bool} $normalizedConfig */
            $normalizedConfig = $processor->process(self::getConfigSchema(), $configData);
        } catch (ValidationException $e) {
            throw new InvalidConfigException($e->getMessage(), $e);
        }

        return new self(
            $providedDirectories === [] ? $normalizedConfig['scanDirs'] : $providedDirectories,
            $normalizedConfig['extensions'],
            $currentDirectory,
            $normalizedConfig['ignoreParseFailures']
        );
    }

    /**
     * @return list<string>
     */
    public function getScanDirs(): array
    {
        return $this->directories;
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
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    private static function getConfigSchema(): Structure
    {
        return Expect::structure([
            'ignoreParseFailures' => Expect::bool()->default(false),
            'scanDirs' => Expect::listOf(Expect::string())->mergeDefaults(false)->default([]),
            'extensions' => Expect::listOf(Expect::string())->mergeDefaults(false)->default(['.php']),
        ])->castTo('array');
    }

}
