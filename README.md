# StaticReflection [![Build Status](https://travis-ci.org/sun/staticreflection.svg)](https://travis-ci.org/sun/staticreflection) [![Code Coverage](https://scrutinizer-ci.com/g/sun/staticreflection/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/sun/staticreflection/?branch=master) [![Code Quality](https://scrutinizer-ci.com/g/sun/staticreflection/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sun/staticreflection/?branch=master)
_Static PHP class code reflection for post-discovery scenarios._

This utility library for PHP frameworks allows to reflect the file header of a
PHP class without loading its code into memory, if its filesystem location is
known already (e.g., via discovery/classmap).

Static reflection is useful to filter a large list of previously discovered
class files for common aspects like interfaces or base classes.

`ReflectionClass` provides the same API as the native `\ReflectionClass`.

Native [PHP Reflection] can easily grow out of control, because it not only
loads the reflected class, but also all dependent classes and interfaces.  PHP
code cannot be unloaded.  A high memory consumption may cause the application to
exceed PHP's memory limit. — Static reflection avoids to (auto-)load all
dependencies and ancestor classes of each reflected class into memory.

In the worst/ideal use-case, you're only generating a list of _available_
classes, without using them immediately (e.g., for user selection or swappable
plugin implementations).

Example [xhprof](http://php.net/manual/en/book.xhprof.php) diff result:

1,538 candidate classes, of which 180 interfaces, traits, abstract and other
helper classes are filtered out:

|       | \ReflectionClass | ReflectionClass | Diff | Diff% |
| ----- | ----------------:| ---------------:| ----:| -----:|
| Number of Function Calls | 64,747 | 202,783 | 138,036 | 213.2%
| Incl. Wall Time (microsecs) | 2,514,801 | 3,272,539 |757,738 | 30.1%
| Incl. CPU (microsecs) | 2,480,415 | 3,120,020 | 639,605 | 25.8%
| Incl. MemUse (bytes) | 108,805,120 | 10,226,160 | -98,578,960 | -90.6%
| Incl. PeakMemUse (bytes) | 108,927,216 | 10,347,608 | -98,579,608 | **-90.5%**


## Usage Example

1. Prerequisite: Some discovery produces a classmap:

    ```json
    {
      "Sun\StaticReflection\ReflectionClass":
        "./src/ReflectionClass.php",
      "Sun\Tests\StaticReflection\ReflectionClassTest":
        "./tests/src/ReflectionClassTest.php",
      "Sun\Tests\StaticReflection\Fixtures\Example":
        "./tests/fixtures/Example.php",
      "Sun\Tests\StaticReflection\Fixtures\Base\ImportedInterface":
        "./tests/fixtures/Base/ImportedInterface.php"
      ...
    }
    ```
    → You have a `classname => pathname` map.

1. Filter all discovered class files:

    ```php
    use Sun\StaticReflection\ReflectionClass;

    $list = array();
    foreach ($classmap as $classname => $pathname) {
      $class = new ReflectionClass($classname, $pathname);

      // Only include tests.
      if (!$class->isSubclassOf('PHPUnit_Framework_TestCase')) {
        continue;
      }

      // …optionally prepare them for a listing/later selection:
      $list[$classname] = array(
        'summary' => $class->getSummary(),
        'covers' => $class->getAnnotations()['coversDefaultClass'][0],
      );
    }
    echo json_encode($list, JSON_PRETTY_PRINT);
    ```

    ```json
    {
      "Sun\Tests\StaticReflection\ReflectionClassTest": {
        "summary": "Tests ReflectionClass.",
        "covers": "\Sun\StaticReflection\ReflectionClass"
      }
    }
    ```
    → You filtered the list of available classes, without loading all code into
    memory.

1. Why this matters:

    ```php
    array_walk($classmap, function (&$pathname, $classname) {
      $pathname = class_exists($classname, FALSE) || interface_exists($classname, FALSE);
    });
    echo json_encode($classmap, JSON_PRETTY_PRINT);
    ```

    ```json
    {
      "Sun\Tests\StaticReflection\ReflectionClassTest": false,
      "Sun\Tests\StaticReflection\Fixtures\Example": false,
      "Sun\Tests\StaticReflection\Fixtures\ExampleInterface": true,
      "Sun\Tests\StaticReflection\Fixtures\Base\Example": true,
      ...
    }
    ```
    → Only the **ancestors** of each class/interface were loaded. The
    statically reflected classes themselves did not get loaded.

1. _ProTip™_ - `ReflectionClass::isSubclassOfAny()`

    To filter for a set of common parent classes/interfaces, check the
    statically reflected information first.  Only proceed to `isSubclassOf()` in
    case you need to check further; e.g.:

    ```php
    // Static reflection.
    if (!$class->isSubclassOfAny(array('Condition\FirstFlavor', 'Condition\SecondFlavor'))) {
      continue;
    }
    // Native reflection of ancestors (if the reflected class has any).
    if (!$class->isSubclassOf('Condition\BaseFlavor')) {
      continue;
    }
    ```


## Requirements

* PHP 5.4.2+


## Limitations

1. Only one class/interface/trait per file (PSR-2, PSR-0/PSR-4), which must be
    defined _first_ in the file.

1. `implementsInterface($interface)` returns `TRUE` even if `$interface` is a
    class.

1. `\ReflectionClass::IS_IMPLICIT_ABSTRACT` is not supported, since methods are
    not analyzed. (only the file header is analyzed)

1. `\ReflectionClass::$name` is read-only and thus not available. Use 
    `getName()` instead.

1. Calling any other `\ReflectionClass` methods that are not implemented (yet)
    causes a fatal error.

    The parent `\ReflectionClass` class might be lazily instantiated on-demand
    in the future (PRs welcome).  `ReflectionClass` does implement all methods
    that can be technically supported already.


## Notes

* StaticReflection may work around bytecode caches that strip off comments.


## Inspirations

Static/Reflection:

* Doctrine's (Static) [Reflection](https://github.com/doctrine/common/tree/master/lib/Doctrine/Common/Reflection)
* phpDocumentor's [Reflection](https://github.com/phpDocumentor/Reflection)
* Zend Framework's [Reflection](https://github.com/zendframework/zf2/tree/master/library/Zend/Server/Reflection)

PHPDoc tags/annotations parsing:

* PHPUnit's [Util\Test](https://github.com/sebastianbergmann/phpunit/blob/master/src/Util/Test.php)
* Doctrine's [Annotations](https://github.com/doctrine/annotations/tree/master/lib/Doctrine/Common/Annotations)
* phpDocumentor's [ReflectionDocBlock](https://github.com/phpDocumentor/ReflectionDocBlock) + [Descriptor](https://github.com/phpDocumentor/phpDocumentor2/tree/develop/src/phpDocumentor/Descriptor)
* Kuria's [PhpDocComment](https://github.com/kuria/php-doc-comment)
* Philip Graham's [Annotations](https://github.com/pgraham/php-annotations)


## License

[MIT](LICENSE) — Copyright (c) 2014 Daniel F. Kudwien (sun)


[PHP Reflection]: http://php.net/manual/en/book.reflection.php
