<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use Generator;
use PHPUnit\Framework\TestCase;
use ShipMonk\NameCollision\Exception\InvalidConfigException;
use function realpath;

class DetectorConfigTest extends TestCase
{

    /**
     * @param list<string> $cliArguments
     * @param list<string>|null $resultingScanPaths
     * @param list<string>|null $resultingFileExtensions
     * @dataProvider provideConfigs
     */
    public function testConfig(
        array $cliArguments,
        string $cwd,
        string $configPath,
        ?array $resultingScanPaths,
        ?array $resultingFileExtensions,
        ?bool $resultingIgnoreParseFailure,
        ?string $error
    ): void
    {
        try {
            $config = DetectionConfig::fromConfigFile($cliArguments, $cwd, $configPath);
            self::assertNull($error);
            self::assertSame($resultingScanPaths, $config->getScanPaths());
            self::assertSame($resultingFileExtensions, $config->getFileExtensions());
            self::assertSame($resultingIgnoreParseFailure, $config->shouldIgnoreParseFailures());

        } catch (InvalidConfigException $e) {
            if ($error === null) {
                throw $e;
            }

            self::assertNull($resultingScanPaths);
            self::assertNull($resultingFileExtensions);
            self::assertNull($resultingIgnoreParseFailure);
            self::assertStringContainsString($error, $e->getMessage());
        }
    }

    /**
     * @return Generator<mixed>
     */
    public function provideConfigs(): Generator
    {
        yield 'no directory provided anywhere' => [
            'cliArguments' => [],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/empty.json',
            'resultingScanPaths' => null,
            'resultingFileExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => 'At least one directory to scan must be provided.',
        ];

        yield 'non-existing directory' => [
            'cliArguments' => ['not-a-dir'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/empty.json',
            'resultingScanPaths' => null,
            'resultingFileExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => 'not-a-dir" is not directory nor a file',
        ];

        yield 'parsing failure' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/not-json.json',
            'resultingScanPaths' => null,
            'resultingFileExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => 'Failure while parsing JSON',
        ];

        yield 'unknown config options are detected' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/unknown-config.json',
            'resultingScanPaths' => null,
            'resultingFileExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => "Unexpected item 'unknown'",
        ];

        yield 'array instead of map' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/bad-shape.json',
            'resultingScanPaths' => null,
            'resultingFileExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => "Unexpected item '0'",
        ];

        yield 'scanDirs with non-list' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/invalid-type.json',
            'resultingScanPaths' => null,
            'resultingFileExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => "The item 'scanPaths' expects to be list, '.' given.",
        ];

        yield 'empty json means falls back to defaults' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/empty.json',
            'resultingScanPaths' => [realpath(__DIR__ . '/.')],
            'resultingFileExtensions' => ['.php'],
            'resultingIgnoreParseFailure' => false,
            'error' => null,
        ];

        yield 'all default can be overwritten' => [
            'cliArguments' => [],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/valid.json',
            'resultingScanPaths' => [realpath(__DIR__ . '/.'), realpath(__DIR__ . '/..')],
            'resultingFileExtensions' => ['.php8'],
            'resultingIgnoreParseFailure' => true,
            'error' => null,
        ];

        yield 'CLI paths have priority' => [
            'cliArguments' => ['data'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/valid.json',
            'resultingScanPaths' => [realpath(__DIR__ . '/data')],
            'resultingFileExtensions' => ['.php8'],
            'resultingIgnoreParseFailure' => true,
            'error' => null,
        ];
    }

}
