<?php declare(strict_types = 1);

namespace ShipMonk\NameCollision;

use PHPUnit\Framework\TestCase;
use ShipMonk\NameCollision\Exception\FileParsingException;
use function fclose;
use function preg_match;
use function proc_close;
use function proc_open;
use function stream_get_contents;

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
$space> /data/multiple-files/colliding1.php
$space> /data/multiple-files/colliding3.php

GlobalClass is defined 2 times:
$space> /data/multiple-files/colliding1.php
$space> /data/multiple-files/colliding2.php

Foo\\namespacedFunction is defined 2 times:
$space> /data/multiple-files/colliding1.php
$space> /data/multiple-files/colliding3.php

globalFunction is defined 2 times:
$space> /data/multiple-files/colliding1.php
$space> /data/multiple-files/colliding2.php

Foo\NAMESPACED_CONST is defined 2 times:
$space> /data/multiple-files/colliding1.php
$space> /data/multiple-files/colliding3.php

GLOBAL_CONST is defined 2 times:
$space> /data/multiple-files/colliding1.php
$space> /data/multiple-files/colliding2.php


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

        self::assertSame(
            $expectedResults,
            $collidingClasses
        );
    }

    private function runCommand(string $command, int $expectedExitCode): string
    {
        $desc = [
            ['pipe', 'r'],
            ['pipe', 'w'],
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
                    '/data/basic-cases/simple.php',
                    '/data/basic-cases/simple.php',
                ],
                'duplicateFunction' => [
                    '/data/basic-cases/simple.php',
                    '/data/basic-cases/simple.php',
                ],
                'DUPLICATE_CONST' => [
                    '/data/basic-cases/simple.php',
                    '/data/basic-cases/simple.php',
                ],
            ],
        ];

        yield [
            'paths' => ['data/basic-cases/html.php'],
            'expectedResults' => [
                'Bar' => [
                    '/data/basic-cases/html.php',
                    '/data/basic-cases/html.php',
                ],
            ],
        ];

        yield [
            'paths' => ['data/fatal-error/code.php'],
            'expectedResults' => [
                'Exists' => [
                    '/data/fatal-error/code.php',
                    '/data/fatal-error/code.php',
                ],
            ],
        ];

        yield [
            'paths' => ['data/basic-cases/groups.php'],
            'expectedResults' => [
                'Go' => [
                    '/data/basic-cases/groups.php',
                    '/data/basic-cases/groups.php',
                    '/data/basic-cases/groups.php',
                    '/data/basic-cases/groups.php',
                ],
            ],
        ];

        yield [
            'paths' => ['data/basic-cases/multiple-namespaces.php'],
            'expectedResults' => [
                'Foo\X' => [
                    '/data/basic-cases/multiple-namespaces.php',
                    '/data/basic-cases/multiple-namespaces.php',
                ],
            ],
        ];

        yield [
            'paths' => ['data/basic-cases/multiple-namespaces-braced.php'],
            'expectedResults' => [
                'Foo\X' => [
                    '/data/basic-cases/multiple-namespaces-braced.php',
                    '/data/basic-cases/multiple-namespaces-braced.php',
                ],
            ],
        ];

        yield [
            'paths' => ['data/multiple-files'],
            'expectedResults' => [
                'Foo\NamespacedClass' => [
                    '/data/multiple-files/colliding1.php',
                    '/data/multiple-files/colliding3.php',
                ],
                'GlobalClass' => [
                    '/data/multiple-files/colliding1.php',
                    '/data/multiple-files/colliding2.php',
                ],
                'Foo\namespacedFunction' => [
                    '/data/multiple-files/colliding1.php',
                    '/data/multiple-files/colliding3.php',
                ],
                'globalFunction' => [
                    '/data/multiple-files/colliding1.php',
                    '/data/multiple-files/colliding2.php',
                ],
                'Foo\NAMESPACED_CONST' => [
                    '/data/multiple-files/colliding1.php',
                    '/data/multiple-files/colliding3.php',
                ],
                'GLOBAL_CONST' => [
                    '/data/multiple-files/colliding1.php',
                    '/data/multiple-files/colliding2.php',
                ],
            ],
        ];
    }

}
