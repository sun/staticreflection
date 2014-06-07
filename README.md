# StaticReflection [![Build Status](https://travis-ci.org/sun/staticreflection.svg)](https://travis-ci.org/sun/staticreflection) [![Coverage Status](https://img.shields.io/coveralls/sun/staticreflection.svg)](https://coveralls.io/r/sun/staticreflection)
_Static PHP class code reflection for post-discovery scenarios._

This utility library for PHP frameworks/applications allows to reflect PHP class
files (headers) without loading them into memory, if the filesystem locations of
each class is known already _(cf. discovery/classmap)_.

Static reflection is useful in case you want to check a _large list_ of
previously discovered class files for common aspects, like interfaces or base
classes, in order to filter out inapplicable candidates.

In a decoupled, modern code base, native [PHP Reflection] can grow out of
control very easily, as it loads every reflected class and interface, as well as
every dependency of every class and interface. — Static reflection avoids to
(auto-)load all dependencies and ancestor classes of each reflected class into
memory.

High memory consumption can be a problem, because PHP's default memory limit is
very small.  Even if it was increased, loading hundreds or even thousands of
classes may easily exceed a custom limit, too.

If you need to reflect 1,000+ classes (e.g., tests), then, on average, 3x times
more PHP files are loaded into memory, which can result in a peak memory
consumption of 120+ MB by the total loaded code only — even though you only care
for the filtered 1k.

In the worst/ideal case, you're just trying to generate a _list_ of
available/discovered classes, without using them immediately (e.g., for a UI/CLI
selection or swappable/configurable plugin implementations).

`ReflectionClass` provides the same API as the native `\ReflectionClass`.


## Usage Example

1. Some (arbitrary) discovery, producing a classmap:  
    _(…you may skip this.)_

    ```php
    use Sun\StaticReflection\ReflectionClass;

    $loader = require __DIR__ . '/vendor/autoload.php';

    // This working example does not have a known classmap upfront, so:
    // Generate one, utilizing the ClassLoader.
    $prefixes = $loader->getPrefixesPsr4();

    $flags = \FilesystemIterator::UNIX_PATHS;
    $flags |= \FilesystemIterator::CURRENT_AS_SELF;

    $classmap = array();
    foreach ($prefixes as $namespace_prefix => $paths) {
      foreach ($paths as $path) {
        $iterator = new \RecursiveDirectoryIterator($path, $flags);
        $filter = new \RecursiveCallbackFilterIterator($iterator, function ($current, $key, $iterator) {
          if ($iterator->hasChildren()) {
            return TRUE;
          }
          return $current->isFile() && $current->getExtension() === 'php';
        });
        $files = new \RecursiveIteratorIterator($filter);
        foreach ($files as $fileinfo) {
          $class = $namespace_prefix;
          if ('' !== $subpath = $fileinfo->getSubPath()) {
            $class .= strtr($subpath, '/', '\\') . '\\';
          }
          $class .= $fileinfo->getBasename('.php');
          $classmap[$class] = $fileinfo->getPathname();
        }
      }
    }
    echo json_encode($classmap, JSON_PRETTY_PRINT);
    ```

    ```json
    {
      "Sun\StaticReflection\ReflectionClass": "src/ReflectionClass.php",
      "Sun\Tests\StaticReflection\ReflectionClassTest": "tests/src/ReflectionClassTest.php",
      "Sun\Tests\StaticReflection\Fixtures\Example": "tests/fixtures/Example.php",
      "Sun\Tests\StaticReflection\Fixtures\Base\ImportedInterface": "tests/fixtures/Base/ImportedInterface.php"
      ...
    }
    ```
    → You have a `classname => pathname` map.

1. _The Real Meat:_ **Filter all discovered class files.**

    ```php
    $list = array();
    foreach ($classmap as $classname => $pathname) {
      // Note: This IS a \ReflectionClass, but does NOT construct one.
      /** @var \Sun\StaticReflection\ReflectionClass */
      $class = new ReflectionClass($classname, $pathname);

      // Only include tests.
      if (!$class->isSubclassOf('PHPUnit_Framework_TestCase')) {
        continue;
      }

      // ...and (optionally) prepare them for a listing/later selection:
      $doc = $class->parseDocComment();
      $list[$classname] = array(
        'summary' => $doc['summary'],
        'covers' => $doc['coversDefaultClass'][0],
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
    → `ReflectionClass` achieved everything you wanted to achieve.

    …but without using `\ReflectionClass` + loading everything into memory.

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
      "Sun\Tests\StaticReflection\Fixtures\Example1Interface": true,
      "Sun\Tests\StaticReflection\Fixtures\Base\Example": true,
      ...
    }
    ```
    → The reflected classes themselves did not get loaded.

    However, you asked each class whether it is a subclass of _X_.  Due to
    class/interface inheritance, the condition of _X_ may not necessarily be
    within the "visible" scope of the statically reflected class (i.e., the
    class file itself).  So what happened?

    Instead of reflecting each class file itself, _only_ the **ancestors** of
    each class/interface were introspected.

    As an example, the following inheritance tree maps to the above
    `Fixtures\Example` class:

    ```
    Sun\Tests\StaticReflection\Fixtures\Example
    ∟ extends
      ∟ Sun\Tests\StaticReflection\Fixtures\Base\Example
        ∟ Sun\Tests\StaticReflection\Fixtures\Base\Root
    ∟ implements
      ∟ Sun\Tests\StaticReflection\Fixtures\Base\Example2Interface
      ∟ Sun\Tests\StaticReflection\Fixtures\Base\ImportedInterface
      ∟ Sun\Tests\StaticReflection\Fixtures\Example1Interface
        ∟ Sun\Tests\StaticReflection\Fixtures\Base\InvisibleInterface
    ```
    → Only the parent class and interfaces _(ancestors)_ were autoloaded.

    That is, because the full stack of their dependencies was not directly
    _"visible"_ in the statically reflected code.


_Pro-Tip:_ To filter for a unique parent/root class/interface, use
`ReflectionClass::isSubclassOfAny()` to check for the most common + expected
parent classes first and only compare against the statically reflected
information.  Only proceed to `isSubclassOf()` in case you want/need to check
further; e.g.:

```php
// Static reflection.
if (!$class->isSubclassOfAny(['Condition\Flavor1', 'Condition\Flavor1'])) {
  continue;
}
// Native reflection of ancestors (if the reflected class has any).
if (!$class->isSubclassOf('Condition\Absolute\Root')) {
  continue;
}
```


## Requirements

* PHP >=5.4.2


## Limitations

1. Only one class/interface/trait per file (PSR-2, PSR-0/PSR-4).

1. `implementsInterface($interface)` returns TRUE even if `$interface` is a
    class.

1. `\ReflectionClass::IS_IMPLICIT_ABSTRACT` is not supported, since methods are
    not analyzed. (only the file header is analyzed)

1. `\ReflectionClass::$name` is read-only and thus not available. Use
    `getName()` instead.

1. Calling any other `\ReflectionClass` methods that are not implemented (yet)
    causes a **fatal error**.

    The parent `\ReflectionClass` class might be dynamically instantiated
    on-demand in the future.  `ReflectionClass` does implement all methods that
    can be technically supported already.


## Notes

* Technically, StaticReflection is able to work around bytecode caches that are
    stripping off comments.  
    _(…just in case that even counts as an issue today)_


## License

[MIT](LICENSE) — Copyright (c) 2014 Daniel F. Kudwien (sun)


[PHP Reflection]: http://php.net/manual/en/book.reflection.php
