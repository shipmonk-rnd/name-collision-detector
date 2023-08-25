<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use PHPUnit\Framework\TestCase;
use ShipMonk\NameCollision\Exception\FileParsingException;
use function fclose;
use function preg_match;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use const PHP_OS_FAMILY;
use const PHP_VERSION_ID;

class CollisionDetectorTest extends TestCase
{

    public function testBinScript(): void
    {
        $expectedNoDirectory = "ERROR: no directories provided, use e.g. `detect-collisions src tests`\n";
        $expectedInvalidDirectoryRegex = "~^ERROR: Provided directory to scan \".*?/tests/nonsense\" is not directory nor a file\n$~";
        $expectedSuccessRegex = '~OK: no name collision found in: .*?/src~';

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

        $regularOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions data/multiple-files', 1);
        $noDirectoryOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions', 255);
        $invalidDirectoryOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions nonsense', 255);
        $successOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions ../src', 0);

        self::assertSame($expectedClasses, $regularOutput);
        self::assertSame($expectedNoDirectory, $noDirectoryOutput);
        self::assertSame(1, preg_match($expectedSuccessRegex, $successOutput));
        self::assertSame(1, preg_match($expectedInvalidDirectoryRegex, $invalidDirectoryOutput));
    }

    public function testParseError(): void
    {
        $detector = new CollisionDetector(
            new DetectionConfig(
                ['data/parse-error/code.php'],
                ['.php'],
                __DIR__
            )
        );
        self::expectException(FileParsingException::class);
        $detector->getCollidingTypes();
    }

    /**
     * @param list<string> $paths
     * @param array<string, list<string>> $expectedResults
     * @dataProvider provideCases
     */
    public function testCollisionDetection(array $paths, array $expectedResults): void
    {
        $detector = new CollisionDetector(
            new DetectionConfig(
                $paths,
                ['.php'],
                __DIR__
            )
        );
        $collidingClasses = $detector->getCollidingTypes();

        self::assertEquals(
            $expectedResults,
            $collidingClasses
        );
    }

    private function runCommand(string $command, int $expectedExitCode): string
    {
        $desc = [
            PHP_OS_FAMILY === 'Windows' ? ['socket'] : ['pipe', 'r'],
            PHP_OS_FAMILY === 'Windows' ? ['socket'] : ['pipe', 'w'],
        ];

        $cwd = __DIR__;
        $procHandle = proc_open($command, $desc, $pipes, $cwd);
        self::assertNotFalse($procHandle);

        $output = stream_get_contents($pipes[1]);
        self::assertNotFalse($output);

        fclose($pipes[0]);
        fclose($pipes[1]);

        $exitCode = proc_close($procHandle);
        self::assertSame($expectedExitCode, $exitCode, "Output was:\n" . $output);

        return $output;
    }

    /**
     * @return mixed[]
     */
    public function provideCases(): iterable
    {
        yield [
            'paths' => ['data/allowed-duplicates'],
            'expectedResults' => [],
        ];

        yield [
            'paths' => ['data/use-statement'], // basically tests that isWithinUseStatement is working properly
            'expectedResults' => [],
        ];

        yield [
            'paths' => ['data/basic-cases/simple.php'],
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

        yield [
            'paths' => ['data/basic-cases/html.php'],
            'expectedResults' => [
                'Bar' => [
                    new FileLine('/data/basic-cases/html.php', 3),
                    new FileLine('/data/basic-cases/html.php', 9),
                ],
            ],
        ];

        yield [
            'paths' => ['data/fatal-error/code.php'],
            'expectedResults' => [
                'Exists' => [
                    new FileLine('/data/fatal-error/code.php', 6),
                    new FileLine('/data/fatal-error/code.php', 7),
                ],
            ],
        ];

        yield [
            'paths' => ['data/basic-cases/groups.php'],
            'expectedResults' => [
                'Go' => [
                    new FileLine('/data/basic-cases/groups.php', 3),
                    new FileLine('/data/basic-cases/groups.php', 4),
                    new FileLine('/data/basic-cases/groups.php', 5),
                ],
            ],
        ];

        if (PHP_VERSION_ID >= 80100) {
            yield [
                'paths' => ['data/basic-cases/groups-with-enum.php'],
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

        yield [
            'paths' => ['data/basic-cases/multiple-namespaces.php'],
            'expectedResults' => [
                'Foo\X' => [
                    new FileLine('/data/basic-cases/multiple-namespaces.php', 5),
                    new FileLine('/data/basic-cases/multiple-namespaces.php', 9),
                ],
            ],
        ];

        yield [
            'paths' => ['data/basic-cases/multiple-namespaces-braced.php'],
            'expectedResults' => [
                'Foo\X' => [
                    new FileLine('/data/basic-cases/multiple-namespaces-braced.php', 4),
                    new FileLine('/data/basic-cases/multiple-namespaces-braced.php', 8),
                ],
            ],
        ];

        yield [
            'paths' => ['data/multiple-files'],
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
    }

}
