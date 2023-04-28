# Name collision detector

Simple tool which allows you to detect if there are no types defined multiple times within the same namespace.
This means that any ambiguous class, interface, enum, trait, constant or function is reported.
Non-zero exit code is returned when any duplicate is found.

## Installation:

```sh
composer require --dev shipmonk/name-collision-detector
```

## Usage:
Check duplicate types:
```sh
vendor/bin/detect-collisions dir1 dir2 dir3
```

Example output:
```
Foo\NamespacedClass2 is defined 2 times:
 > /tests/sample-collisions/file2.php
 > /tests/sample-collisions/file2.php

GlobalInterface1 is defined 2 times:
 > /tests/sample-collisions/file1.php
 > /tests/sample-collisions/file2.php
```

## Configuration:
If file named `collision-detector.json` is present within current working directory, its contents are taken as configuration options. Possible config options:
```json
{
    "scanDirs": ["src", "tests"], // directories to scan, relative to CWD
    "extensions": [".php"], // file extensions to parse
    "ignoreParseFailures": false // skip files with parse errors or not
}
```
While `scanDirs` within config file has priority over those provided by CLI arguments.

## Reasoning
Having colliding classes within project can cause crazy headaches while debugging why something works only sometimes.
Typically, you have PSR-4 autoloading solving this problem for you, but there are cases (like [PHPStan rules test files](https://github.com/shipmonk-rnd/phpstan-rules/tree/master/tests/Rule/data)) where you want to write any code (with [classmap](https://getcomposer.org/doc/04-schema.md#classmap) autoloading).
And in such cases, the test may work when executed in standalone run, but fail when running all the tests together (depending on which class was autoloaded first).
Therefore, having a collision detector in CI might be useful.

## Supported PHP versions
- PHP 7.2 - PHP 8.2

