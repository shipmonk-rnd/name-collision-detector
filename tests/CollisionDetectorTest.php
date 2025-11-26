<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use PHPUnit\Framework\TestCase;
use ShipMonk\NameCollision\Exception\FileParsingException;
use function fclose;
use function preg_match;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use const PHP_EOL;
use const PHP_VERSION_ID;

class CollisionDetectorTest extends TestCase
{

    public function testBinScript(): void
    {
        $expectedNoDirectoryRegex = '~^ERROR: No directories provided, use e.g. `detect-collisions src tests` or setup scanPaths in~';
        $expectedInvalidDirectoryRegex = '~^ERROR: Provided directory to scan ".*?nonsense" is not directory nor a file~';
        $expectedSuccessWithConfigRegex = '~^Using config .*?' . PHP_EOL . PHP_EOL . 'OK \(no name collision found\)~';
        $expectedSuccessRegex = '~^OK \(no name collision found\)~';

        $space = ' '; // bypass editorconfig checker
        $expectedClasses = <<<EOF
Foo\NamespacedClass is defined 2 times:
$space> /data/multiple-files/colliding1.php:11
$space> /data/multiple-files/colliding3.php:5

GlobalClass is defined 2 times:
$space> /data/multiple-files/colliding1.php:4
$space> /data/multiple-files/colliding2.php:4

Foo\\namespacedFunction is defined 2 times:
$space> /data/multiple-files/colliding1.php:12
$space> /data/multiple-files/colliding3.php:6

globalFunction is defined 2 times:
$space> /data/multiple-files/colliding1.php:5
$space> /data/multiple-files/colliding2.php:5

Foo\NAMESPACED_CONST is defined 2 times:
$space> /data/multiple-files/colliding1.php:13
$space> /data/multiple-files/colliding3.php:7

GLOBAL_CONST is defined 2 times:
$space> /data/multiple-files/colliding1.php:6
$space> /data/multiple-files/colliding2.php:6



EOF;

        $successOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions ../src', 0);
        $successOutputWithCustomConfig = $this->runCommand(__DIR__ . '/../bin/detect-collisions --configuration data/config-files/valid.json ../src', 0);
        $regularOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions data/multiple-files', 1);
        $invalidDirectoryOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions nonsense', 255);
        $noDirectoryOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions', 255);

        self::assertSame($expectedClasses, $regularOutput);
        self::assertSame(1, preg_match($expectedNoDirectoryRegex, $noDirectoryOutput), 'Real output: ' . $noDirectoryOutput);
        self::assertSame(1, preg_match($expectedSuccessRegex, $successOutput), 'Real output: ' . $successOutput);
        self::assertSame(1, preg_match($expectedSuccessWithConfigRegex, $successOutputWithCustomConfig), 'Real output: ' . $successOutputWithCustomConfig);
        self::assertSame(1, preg_match($expectedInvalidDirectoryRegex, $invalidDirectoryOutput), 'Real output: ' . $invalidDirectoryOutput);
    }

    public function testParseError(): void
    {
        $detector = new CollisionDetector(
            new DetectionConfig(
                [__DIR__ . '/data/parse-error/code.php'],
                [],
                ['php'],
                __DIR__,
            ),
        );
        self::expectException(FileParsingException::class);
        $detector->getCollidingTypes();
    }

    /**
     * @param list<string> $paths
     * @param list<string> $excludedPaths
     * @param array<string, list<string>> $expectedResults
     * @dataProvider provideCases
     */
    public function testCollisionDetection(
        array $paths,
        array $excludedPaths,
        int $expectedAnalysedFiles,
        int $expectedExcludedFiles,
        array $expectedResults
    ): void
    {
        $detector = new CollisionDetector(
            new DetectionConfig(
                $paths,
                $excludedPaths,
                ['php'],
                __DIR__,
            ),
        );
        $result = $detector->getCollidingTypes();

        self::assertSame($expectedAnalysedFiles, $result->getAnalysedFilesCount());
        self::assertSame($expectedExcludedFiles, $result->getExcludedFilesCount());
        self::assertEquals($expectedResults, $result->getCollisions());
    }

    private function runCommand(string $command, int $expectedExitCode): string
    {
        $desc = [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ];

        $cwd = __DIR__;
        $procHandle = proc_open('php ' . $command, $desc, $pipes, $cwd);
        self::assertNotFalse($procHandle);

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        self::assertNotFalse($output);
        self::assertNotFalse($errorOutput);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($procHandle);
        self::assertSame(
            $expectedExitCode,
            $exitCode,
            "Output was:\n" . $output . "\n" .
            "Error was:\n" . $errorOutput . "\n",
        );

        return $output;
    }

    /**
     * @return mixed[]
     */
    public function provideCases(): iterable
    {
        yield 'allowed duplicates' => [
            'paths' => [__DIR__ . '/data/allowed-duplicates'],
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 1,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [],
        ];

        yield 'use statements' => [
            'paths' => [__DIR__ . '/data/use-statement'], // basically tests that isWithinUseStatement is working properly
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 3,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [],
        ];

        yield 'simple cases' => [
            'paths' => [__DIR__ . '/data/basic-cases/simple.php'],
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 1,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [
                'DuplicateClass' => [
                    new FileLine('/data/basic-cases/simple.php', 3),
                    new FileLine('/data/basic-cases/simple.php', 4),
                ],
                'duplicateFunction' => [
                    new FileLine('/data/basic-cases/simple.php', 6),
                    new FileLine('/data/basic-cases/simple.php', 7),
                ],
                'DUPLICATE_CONST' => [
                    new FileLine('/data/basic-cases/simple.php', 9),
                    new FileLine('/data/basic-cases/simple.php', 10),
                ],
            ],
        ];

        yield 'html case' => [
            'paths' => [__DIR__ . '/data/basic-cases/html.php'],
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 1,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [
                'Bar' => [
                    new FileLine('/data/basic-cases/html.php', 3),
                    new FileLine('/data/basic-cases/html.php', 9),
                ],
            ],
        ];

        yield 'fatal error' => [
            'paths' => [__DIR__ . '/data/fatal-error/code.php'],
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 1,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [
                'Exists' => [
                    new FileLine('/data/fatal-error/code.php', 6),
                    new FileLine('/data/fatal-error/code.php', 7),
                ],
            ],
        ];

        yield 'groups' => [
            'paths' => [__DIR__ . '/data/basic-cases/groups.php'],
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 1,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [
                'Go' => [
                    new FileLine('/data/basic-cases/groups.php', 3),
                    new FileLine('/data/basic-cases/groups.php', 4),
                    new FileLine('/data/basic-cases/groups.php', 5),
                ],
            ],
        ];

        if (PHP_VERSION_ID >= 80100) {
            yield 'groups with enum' => [
                'paths' => [__DIR__ . '/data/basic-cases/groups-with-enum.php'],
                'excludedPaths' => [],
                'expectedAnalysedFiles' => 1,
                'expectedExcludedFiles' => 0,
                'expectedResults' => [
                    'Go' => [
                        new FileLine('/data/basic-cases/groups-with-enum.php', 3),
                        new FileLine('/data/basic-cases/groups-with-enum.php', 4),
                        new FileLine('/data/basic-cases/groups-with-enum.php', 5),
                        new FileLine('/data/basic-cases/groups-with-enum.php', 6),
                    ],
                ],
            ];
        }

        yield 'multi namespace' => [
            'paths' => [__DIR__ . '/data/basic-cases/multiple-namespaces.php'],
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 1,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [
                'Foo\X' => [
                    new FileLine('/data/basic-cases/multiple-namespaces.php', 5),
                    new FileLine('/data/basic-cases/multiple-namespaces.php', 9),
                ],
            ],
        ];

        yield 'multi namespace braced' => [
            'paths' => [__DIR__ . '/data/basic-cases/multiple-namespaces-braced.php'],
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 1,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [
                'Foo\X' => [
                    new FileLine('/data/basic-cases/multiple-namespaces-braced.php', 4),
                    new FileLine('/data/basic-cases/multiple-namespaces-braced.php', 8),
                ],
            ],
        ];

        yield 'more files' => [
            'paths' => [__DIR__ . '/data/multiple-files'],
            'excludedPaths' => [],
            'expectedAnalysedFiles' => 5,
            'expectedExcludedFiles' => 0,
            'expectedResults' => [
                'Foo\NamespacedClass' => [
                    new FileLine('/data/multiple-files/colliding1.php', 11),
                    new FileLine('/data/multiple-files/colliding3.php', 5),
                ],
                'GlobalClass' => [
                    new FileLine('/data/multiple-files/colliding1.php', 4),
                    new FileLine('/data/multiple-files/colliding2.php', 4),
                ],
                'Foo\namespacedFunction' => [
                    new FileLine('/data/multiple-files/colliding1.php', 12),
                    new FileLine('/data/multiple-files/colliding3.php', 6),
                ],
                'globalFunction' => [
                    new FileLine('/data/multiple-files/colliding1.php', 5),
                    new FileLine('/data/multiple-files/colliding2.php', 5),
                ],
                'Foo\NAMESPACED_CONST' => [
                    new FileLine('/data/multiple-files/colliding1.php', 13),
                    new FileLine('/data/multiple-files/colliding3.php', 7),
                ],
                'GLOBAL_CONST' => [
                    new FileLine('/data/multiple-files/colliding1.php', 6),
                    new FileLine('/data/multiple-files/colliding2.php', 6),
                ],
            ],
        ];

        yield 'more files with exclude' => [
            'paths' => [__DIR__ . '/data/multiple-files'],
            'excludedPaths' => [__DIR__ . '/data/multiple-files/colliding3.php'],
            'expectedAnalysedFiles' => 4,
            'expectedExcludedFiles' => 1,
            'expectedResults' => [
                'GlobalClass' => [
                    new FileLine('/data/multiple-files/colliding1.php', 4),
                    new FileLine('/data/multiple-files/colliding2.php', 4),
                ],
                'globalFunction' => [
                    new FileLine('/data/multiple-files/colliding1.php', 5),
                    new FileLine('/data/multiple-files/colliding2.php', 5),
                ],
                'GLOBAL_CONST' => [
                    new FileLine('/data/multiple-files/colliding1.php', 6),
                    new FileLine('/data/multiple-files/colliding2.php', 6),
                ],
            ],
        ];
    }

}
