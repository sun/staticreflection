<?php

/**
 * @file
 * Contains \Sun\StaticReflection\Fixtures\Example.
 *
 * Some description.
 */

namespace Sun\StaticReflection\Fixtures;

use Sun\StaticReflection\ReflectionClass; // The thing.
use Sun\StaticReflection\Fixtures\Base\ImportedInterface;
use Foo
  // Name clash.
  as FooAlias;

define('POORLY_CODED_APP_ROOT', 'some poorly authored code');

/**
 * PHPDoc summary line.
 * Summary may wrap.
 *
 * Description #1.
 *
 * @tag
 * @single parameter
 * @multiple type $param
 *
 * Description #2.
 *
 * @see Example1Interface
 */
abstract class Example extends Base\Example implements Example1Interface, Base\Example2Interface, ImportedInterface, \Countable {
}
