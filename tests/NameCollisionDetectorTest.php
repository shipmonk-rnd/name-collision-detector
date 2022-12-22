<?php declare(strict_types = 1);

namespace ShipMonk;

use PHPUnit\Framework\TestCase;
use function fclose;
use function proc_close;
use function proc_open;
use function stream_get_contents;

class NameCollisionDetectorTest extends TestCase
{

    public function testBinScript(): void
    {
        $expectedNoDirectory = "ERROR: no directories provided, use e.g. `detect-collisions src tests`\n";
        $expectedInvalidDirectoryRegex = "~^ERROR: \".*?/tests/nonsense\" does not exist\n$~";
        $expectedSuccessRegex = <<<'EOF'
~Checking duplicates of classes and functions and constants in ../src:

OK: no name collision found in: .*?/src~
EOF;
        $space = ' '; // bypass editorconfig checker
        $expectedClasses = <<<EOF
Checking duplicates of classes in sample-collisions:

Foo\NamespacedClass1 is defined 2 times:
$space> /sample-collisions/file1.php
$space> /sample-collisions/file2.php

Foo\NamespacedClass2 is defined 2 times:
$space> /sample-collisions/file2.php
$space> /sample-collisions/file2.php

GlobalClass1 is defined 2 times:
$space> /sample-collisions/file1.php
$space> /sample-collisions/file2.php

GlobalClass2 is defined 2 times:
$space> /sample-collisions/file2.php
$space> /sample-collisions/file2.php


EOF;

        self::assertSame($expectedClasses, $this->runCommand(__DIR__ . '/../bin/detect-collisions --classes sample-collisions', 1));
        self::assertSame($expectedNoDirectory, $this->runCommand(__DIR__ . '/../bin/detect-collisions', 255));
        self::assertMatchesRegularExpression($expectedInvalidDirectoryRegex, $this->runCommand(__DIR__ . '/../bin/detect-collisions nonsense', 255));
        self::assertMatchesRegularExpression($expectedSuccessRegex, $this->runCommand(__DIR__ . '/../bin/detect-collisions ../src', 0));
    }

    public function testCollisionDetection(): void
    {
        $detector = new NameCollisionDetector([__DIR__ . '/sample-collisions'], __DIR__);
        $collidingClasses = $detector->getCollidingClasses();
        $collidingFunctions = $detector->getCollidingFunctions();
        $collidingConstants = $detector->getCollidingConstants();

        self::assertSame(
            [
                'Foo\NamespacedClass1' => [
                    '/sample-collisions/file1.php',
                    '/sample-collisions/file2.php',
                ],
                'Foo\NamespacedClass2' => [
                    '/sample-collisions/file2.php',
                    '/sample-collisions/file2.php',
                ],
                'GlobalClass1' => [
                    '/sample-collisions/file1.php',
                    '/sample-collisions/file2.php',
                ],
                'GlobalClass2' => [
                    '/sample-collisions/file2.php',
                    '/sample-collisions/file2.php',
                ],
            ],
            $collidingClasses
        );

        self::assertSame(
            [
                'Foo\namespacedFunction' => [
                    '/sample-collisions/file2.php',
                    '/sample-collisions/file2.php',
                ],
                'globalFunction' => [
                    '/sample-collisions/file1.php',
                    '/sample-collisions/file2.php',
                ],
            ],
            $collidingFunctions
        );

        self::assertSame(
            [
                'Foo\NAMESPACED_CONST' => [
                    '/sample-collisions/file1.php',
                    '/sample-collisions/file2.php',
                ],
                'GLOBAL_CONST' => [
                    '/sample-collisions/file1.php',
                    '/sample-collisions/file2.php',
                ],
            ],
            $collidingConstants
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
        self::assertSame($expectedExitCode, $exitCode);

        return $output;
    }

}
