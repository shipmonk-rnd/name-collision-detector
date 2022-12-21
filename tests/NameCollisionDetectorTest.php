<?php declare(strict_types = 1);

namespace ShipMonk;

use PHPUnit\Framework\TestCase;

class NameCollisionDetectorTest extends TestCase
{

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
            $collidingClasses,
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
            $collidingFunctions,
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
            $collidingConstants,
        );
    }

}
