<?php

namespace {
    class GlobalClass1 {

    }

    class GlobalClass2 {

    }

    enum GlobalClass2 {

    }

    function globalFunction() {

    }

    const GLOBAL_CONST = 1;
}

namespace Foo {
    class NamespacedClass1 {

    }

    interface
        // comment
        NamespacedClass2 {

    }

    function namespacedFunction() {

    }

    const NAMESPACED_CONST = 1;
}

namespace Foo {
    trait/**/NamespacedClass2 {

    }

    function namespacedFunction() {

    }
}
