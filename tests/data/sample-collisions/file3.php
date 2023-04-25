<?php

namespace Foo;

class NotCollidingAsScopedByClass {
    const NAMESPACED_CONST = 1;
    function namespacedFunction() {}
}

new class {
    const NAMESPACED_CONST = 1;
    function namespacedFunction() {}
};
