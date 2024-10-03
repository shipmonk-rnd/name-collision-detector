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
vendor/bin/detect-collisions dir1 dir2 dir3 # relative to cwd
```

Example error output:
```
Foo\NamespacedClass2 is defined 2 times:
 > /tests/sample-collisions/file2.php:23
 > /tests/sample-collisions/file2.php:45

GlobalInterface1 is defined 2 times:
 > /tests/sample-collisions/file1.php:8
 > /tests/sample-collisions/file2.php:11
```

Example success output:
```
OK (no name collision found)
 * analysed files: 9867
 * excluded files: 0
 * elapsed time: 1.057 s
```

- Note the performance: **10 000 files takes few seconds**!

## Configuration:
If file named `collision-detector.json` is present within current working directory, its contents are taken as configuration options. Possible config options:
```json5
{
    "scanPaths": ["src", "tests"], // files/directories to scan, relative to config file directory, glob not supported
    "excludePaths": ["tests/collisions"], // files/directories to exclude, relative to config file directory, glob not supported
    "fileExtensions": ["php"], // file extensions to parse
    "ignoreParseFailures": false // skip files with parse errors or not
}
```
Paths provided by CLI arguments have priority over those in `scanDirs`.

You can provide custom path to config file by `vendor/bin/detect-collisions --configuration path/to/config.json`

## Reasoning
Having colliding classes within project can cause crazy headaches while debugging why something works only sometimes.
Typically, you have PSR-4 autoloading which often solves this problem for you, but there are cases (like [PHPStan rules test files](https://github.com/shipmonk-rnd/phpstan-rules/tree/master/tests/Rule/data)) where you want to write any code (with [classmap](https://getcomposer.org/doc/04-schema.md#classmap) autoloading).
And in such cases, the test may work when executed in standalone run, but fail when running all the tests together (depending on which class was autoloaded first).
Therefore, having a collision detector in CI might be useful.

## Composer's Ambiguous class resolution
The check that Composer performs (which results in `Warning: Ambiguous class resolution`) [has some weird hidden ignores](https://github.com/composer/composer/issues/12140#issuecomment-2389035210) that makes it generally not usable.

## Supported PHP versions
- PHP 7.2 - PHP 8.3

