<?php declare(strict_types = 1);

namespace ShipMonk;

use PHPUnit\Framework\TestCase;
use function fclose;
use function preg_match;
use function proc_close;
use function proc_open;
use function stream_get_contents;

class NameCollisionDetectorTest extends TestCase
{

    public function testBinScript(): void
    {
        $expectedNoDirectory = "ERROR: no directories provided, use e.g. `detect-collisions src tests`\n";
        $expectedInvalidDirectoryRegex = "~^ERROR: Path \".*?/tests/nonsense\" is not directory\n$~";
        $parsingFailed = "~^ERROR: Unable to parse .*?/tests/data/parse-failure/file1.php: .*?\n$~";
        $expectedSuccessRegex = '~OK: no name collision found in: .*?/src~';

        $space = ' '; // bypass editorconfig checker
        $expectedClasses = <<<EOF
Foo\NAMESPACED_CONST is defined 2 times:
$space> /data/sample-collisions/file1.php
$space> /data/sample-collisions/file2.php

Foo\NamespacedClass1 is defined 2 times:
$space> /data/sample-collisions/file1.php
$space> /data/sample-collisions/file2.php

Foo\NamespacedClass2 is defined 2 times:
$space> /data/sample-collisions/file2.php
$space> /data/sample-collisions/file2.php

Foo\\namespacedFunction is defined 2 times:
$space> /data/sample-collisions/file2.php
$space> /data/sample-collisions/file2.php

GLOBAL_CONST is defined 2 times:
$space> /data/sample-collisions/file1.php
$space> /data/sample-collisions/file2.php

GlobalClass1 is defined 2 times:
$space> /data/sample-collisions/file1.php
$space> /data/sample-collisions/file2.php

GlobalClass2 is defined 2 times:
$space> /data/sample-collisions/file2.php
$space> /data/sample-collisions/file2.php

globalFunction is defined 2 times:
$space> /data/sample-collisions/file1.php
$space> /data/sample-collisions/file2.php


EOF;

        $regularOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions data/sample-collisions', 1);
        $parseFailedOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions data/parse-failure', 255);
        $noDirectoryOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions', 255);
        $invalidDirectoryOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions nonsense', 255);
        $successOutput = $this->runCommand(__DIR__ . '/../bin/detect-collisions ../src', 0);

        self::assertSame($expectedClasses, $regularOutput);
        self::assertSame($expectedNoDirectory, $noDirectoryOutput);
        self::assertSame(1, preg_match($parsingFailed, $parseFailedOutput));
        self::assertSame(1, preg_match($expectedSuccessRegex, $successOutput));
        self::assertSame(1, preg_match($expectedInvalidDirectoryRegex, $invalidDirectoryOutput));
    }

    public function testCollisionDetection(): void
    {
        $detector = new NameCollisionDetector([__DIR__ . '/data/sample-collisions'], __DIR__);
        $collidingClasses = $detector->getCollidingTypes();

        self::assertSame(
            [
                'Foo\NAMESPACED_CONST' => [
                    '/data/sample-collisions/file1.php',
                    '/data/sample-collisions/file2.php',
                ],
                'Foo\NamespacedClass1' => [
                    '/data/sample-collisions/file1.php',
                    '/data/sample-collisions/file2.php',
                    ],
                'Foo\NamespacedClass2' => [
                    '/data/sample-collisions/file2.php',
                    '/data/sample-collisions/file2.php',
                ],
                'Foo\namespacedFunction' => [
                    '/data/sample-collisions/file2.php',
                    '/data/sample-collisions/file2.php',
                ],
                'GLOBAL_CONST' => [
                    '/data/sample-collisions/file1.php',
                    '/data/sample-collisions/file2.php',
                ],
                'GlobalClass1' => [
                    '/data/sample-collisions/file1.php',
                    '/data/sample-collisions/file2.php',
                ],
                'GlobalClass2' => [
                    '/data/sample-collisions/file2.php',
                    '/data/sample-collisions/file2.php',
                ],
                'globalFunction' => [
                    '/data/sample-collisions/file1.php',
                    '/data/sample-collisions/file2.php',
                ],
            ],
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
        self::assertSame($expectedExitCode, $exitCode);

        return $output;
    }

}
