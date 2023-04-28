<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use Generator;
use PHPUnit\Framework\TestCase;
use ShipMonk\NameCollision\Exception\InvalidConfigException;

class DetectorConfigTest extends TestCase
{

    /**
     * @param list<string> $cliArguments
     * @param list<string>|null $resultingScanDirs
     * @param list<string>|null $resultingExtensions
     * @dataProvider provideConfigs
     */
    public function testConfig(
        array $cliArguments,
        string $cwd,
        string $configPath,
        ?array $resultingScanDirs,
        ?array $resultingExtensions,
        ?bool $resultingIgnoreParseFailure,
        ?string $error
    ): void
    {
        try {
            $config = DetectionConfig::fromConfigFile($cliArguments, $cwd, $configPath);
            self::assertNull($error);
            self::assertSame($resultingScanDirs, $config->getScanDirs());
            self::assertSame($resultingExtensions, $config->getExtensions());
            self::assertSame($resultingIgnoreParseFailure, $config->shouldIgnoreParseFailures());

        } catch (InvalidConfigException $e) {
            if ($error === null) {
                throw $e;
            }

            self::assertNull($resultingScanDirs);
            self::assertNull($resultingExtensions);
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
            'resultingScanDirs' => null,
            'resultingExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => 'At least one directory to scan must be provided.',
        ];

        yield 'non-existing directory' => [
            'cliArguments' => ['not-a-dir'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/empty.json',
            'resultingScanDirs' => null,
            'resultingExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => 'Provided directory to scan "not-a-dir" is not directory',
        ];

        yield 'parsing failure' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/not-json.json',
            'resultingScanDirs' => null,
            'resultingExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => 'Failure while parsing JSON',
        ];

        yield 'unknown config options are detected' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/unknown-config.json',
            'resultingScanDirs' => null,
            'resultingExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => "Unexpected item 'unknown'",
        ];

        yield 'array instead of map' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/bad-shape.json',
            'resultingScanDirs' => null,
            'resultingExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => "Unexpected item '0'",
        ];

        yield 'scanDirs with non-list' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/invalid-type.json',
            'resultingScanDirs' => null,
            'resultingExtensions' => null,
            'resultingIgnoreParseFailure' => null,
            'error' => "The item 'scanDirs' expects to be list, '.' given.",
        ];

        yield 'empty json means falls back to defaults' => [
            'cliArguments' => ['.'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/empty.json',
            'resultingScanDirs' => ['.'],
            'resultingExtensions' => ['.php'],
            'resultingIgnoreParseFailure' => false,
            'error' => null,
        ];

        yield 'all default can be overwritten' => [
            'cliArguments' => [],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/valid.json',
            'resultingScanDirs' => ['.', '..'],
            'resultingExtensions' => ['.php8'],
            'resultingIgnoreParseFailure' => true,
            'error' => null,
        ];

        yield 'config paths have priority' => [
            'cliArguments' => ['data'],
            'cwd' => __DIR__,
            'configPath' => __DIR__ . '/data/config-files/valid.json',
            'resultingScanDirs' => ['.', '..'],
            'resultingExtensions' => ['.php8'],
            'resultingIgnoreParseFailure' => true,
            'error' => null,
        ];
    }

}
