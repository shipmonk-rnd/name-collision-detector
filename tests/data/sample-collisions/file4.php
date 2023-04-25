<?php

use Foo\{
    UniqueClass,
    NamespacedClass1,
    UniqueInterface,
    NamespacedClass2,
    function namespacedFunction,
    const NAMESPACED_CONST,
    const UNIQUE_CONST
};

use Foo\{
    function globalFunction,
    const GLOBAL_CONST
};

class Foo {
    const NAMESPACED_CONST = 1;
    function namespacedFunction() {}
}

