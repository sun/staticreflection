<?php

/**
 * @file
 * Contains \Sun\Tests\StaticReflection\ReflectionClassTest.
 */

namespace Sun\Tests\StaticReflection;

use Sun\StaticReflection\ReflectionClass;

/**
 * Tests ReflectionClass.
 *
 * @coversDefaultClass \Sun\StaticReflection\ReflectionClass
 */
class ReflectionClassTest extends \PHPUnit_Framework_TestCase {

  private $name;
  private $path;

  private $info = array(
    'fqcn' => 'Sun\Tests\StaticReflection\Fixtures\Example',
    T_DOC_COMMENT => '/**
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
 * @see ExampleInterface
 */',
    T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
    T_USE => array(
      'ReflectionClass' => 'Sun\StaticReflection\ReflectionClass',
      'ImportedInterface' => 'Sun\Tests\StaticReflection\Fixtures\Base\ImportedInterface',
      'FooAlias' => 'Foo',
    ),
    T_ABSTRACT => TRUE,
    T_FINAL => FALSE,
    T_CLASS => 'Sun\Tests\StaticReflection\Fixtures\Example',
    T_INTERFACE => FALSE,
    T_TRAIT => FALSE,
    T_EXTENDS => array(
      'Sun\Tests\StaticReflection\Fixtures\Base\Example',
    ),
    T_IMPLEMENTS => array(
      'Sun\Tests\StaticReflection\Fixtures\ExampleInterface',
      'Sun\Tests\StaticReflection\Fixtures\Base\NotImportedInterface',
      'Sun\Tests\StaticReflection\Fixtures\Base\ImportedInterface',
      'Countable',
    ),
  );

  private $defaults = array(
    'fqcn' => '',
    T_DOC_COMMENT => '',
    T_NAMESPACE => '',
    T_USE => array(),
    T_ABSTRACT => FALSE,
    T_FINAL => FALSE,
    T_CLASS => FALSE,
    T_INTERFACE => FALSE,
    T_TRAIT => FALSE,
    T_EXTENDS => array(),
    T_IMPLEMENTS => array(),
  );

  public function setUp() {
    $this->name = 'Sun\Tests\StaticReflection\Fixtures\Example';
    $this->path = dirname(__DIR__) . '/fixtures/Example.php';
  }

  /**
   * @runInSeparateProcess
   */
  public function testSetUp() {
    $this->assertTrue(class_exists($this->name));
    $path = realpath($this->path);
    $this->assertSame($path, (new \ReflectionClass($this->name))->getFileName());
  }

  /**
   * Returns a new ReflectionClass mock instance.
   *
   * @param array $return
   *   The return value for ReflectionClass::reflect().
   * @param array $methods
   *   Additional methods to replace with configurable mocks.
   *
   * @return PHPUnit_Mock_Object
   */
  private function getClassReflectorMock(array $return = array(), array $methods = array()) {
    $reflector = $this->getMockBuilder('Sun\StaticReflection\ReflectionClass')
      ->setMethods(array('reflect') + $methods)
      ->setConstructorArgs(array($this->name, $this->path))
      ->getMock();

    // Unless explicitly specified, automatically populate the FQCN.
    if (!isset($return['fqcn'])) {
      $return['fqcn'] = '';
      if (isset($return[T_NAMESPACE])) {
        $return['fqcn'] = $return[T_NAMESPACE] . '\\';
      }
      foreach (array(T_CLASS, T_INTERFACE, T_TRAIT) as $key) {
        if (isset($return[$key])) {
          $return['fqcn'] .= $return[$key];
          break;
        }
      }
    }
    $reflector
      ->expects($this->once())
      ->method('reflect')
      ->will($this->returnValue($return + $this->defaults));

    $reflector->__construct($this->name, $this->path);

    return $reflector;
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertFalse(class_exists($this->name, FALSE));
    $this->assertInstanceOf('\ReflectionClass', $reflector);
  }

  /**
   * @covers ::__construct
   */
  public function testConstructWithInstance() {
    $reflector = new ReflectionClass($this, __FILE__);
    $this->assertSame(__CLASS__, $reflector->getName());
    $this->assertSame(__FILE__, $reflector->getFileName());
  }

  /**
   * @covers ::__construct
   * @runInSeparateProcess
   */
  public function testConstructWithoutPathname() {
    $this->assertFalse(class_exists($this->name, FALSE));
    $reflector = new ReflectionClass($this->name);
    $this->assertTrue(class_exists($this->name, FALSE));
    $this->assertSame(realpath($this->path), $reflector->getFileName());
    $this->assertInstanceOf('\ReflectionClass', $reflector);
  }

  /**
   * @covers ::__construct
   * @covers ::readFileHeader
   * @expectedException \ReflectionException
   */
  public function testConstructWithBogusPathname() {
    $pathname = __DIR__ . '/404/Class.php';
    $reflector = new ReflectionClass($this->name, $pathname);
  }

  /**
   * @covers ::reflect
   * @covers ::readFileHeader
   */
  public function testReflect() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $method = new \ReflectionMethod($reflector, 'reflect');
    $method->setAccessible(TRUE);
    $actual = $method->invoke($reflector);
    $this->assertSame($this->info, $actual);
    $this->assertFalse(class_exists($this->name, FALSE));
  }

  /**
   * @covers ::reflect
   * @expectedException \ReflectionException
   */
  public function testReflectWithBogusClassname() {
    $classname = $this->name . 'Bogus';
    $reflector = new ReflectionClass($classname, $this->path);
    $method = new \ReflectionMethod($reflector, 'reflect');
    $method->setAccessible(TRUE);
    $method->invoke($reflector);
  }

  /**
   * @covers ::reflect
   * @expectedException \ReflectionException
   */
  public function testReflectWithBogusNamespace() {
    $classname = 'Bogus\\' . $this->name;
    $reflector = new ReflectionClass($classname, $this->path);
    $method = new \ReflectionMethod($reflector, 'reflect');
    $method->setAccessible(TRUE);
    $method->invoke($reflector);
  }

  /**
   * @covers ::tokenize
   * @dataProvider providerTokenize
   */
  public function testTokenize($expected, $content) {
    $method = new \ReflectionMethod('Sun\StaticReflection\ReflectionClass', 'tokenize');
    $method->setAccessible(TRUE);
    $this->assertEquals($expected, $method->invoke(NULL, $content));
  }

  public function providerTokenize() {
    $cases = array();

    // Namespace.
    $expected = [
      'fqcn' => 'Foo\Bar\Baz',
      T_NAMESPACE => 'Foo\Bar',
      T_CLASS => 'Foo\Bar\Baz',
    ];
    $content = '<?php
namespace Foo\Bar;

class Baz {
}
';
    $cases[] = [$expected, $content];

    // Scoped namespace.
    $expected = [
      'fqcn' => 'Foo\Bar\Baz',
      T_NAMESPACE => 'Foo\Bar',
      T_CLASS => 'Foo\Bar\Baz',
    ];
    $content = '<?php
namespace Foo\Bar {

class Baz {
}
}
';
    $cases[] = [$expected, $content];

    // PSR-2 coding style.
    $expected = [
      'fqcn' => 'Psr\Two',
      T_NAMESPACE => 'Psr',
      T_CLASS => 'Psr\Two',
    ];
    $content = '<?php
namespace Psr;

class Two
{
}
';
    $cases[] = [$expected, $content];

    // Use/As (namespace imports).
    $expected = [
      'fqcn' => 'Name\Space\MyClass',
      T_NAMESPACE => 'Name\Space',
      T_USE => ['Alias' => 'Clash\MyClass', 'AliasedSpace' => 'Third\Space', 'Unaliased' => 'Other\Unaliased'],
      T_CLASS => 'Name\Space\MyClass',
      T_EXTENDS => ['Clash\MyClass'],
      T_IMPLEMENTS => ['Third\Space\Alias', 'Other\Unaliased'],
    ];
    $content = '<?php
namespace Name\Space;
use Clash\MyClass as Alias;
use Third\Space as AliasedSpace;
use Other\Unaliased;
class MyClass extends Alias implements AliasedSpace\Alias, Unaliased {}
';
    $cases[] = [$expected, $content];

    // Doc comment block.
    $expected = [
      'fqcn' => 'Space\Name',
      T_DOC_COMMENT => '/**
 * The name.
 */',
      T_NAMESPACE => 'Space',
      T_CLASS => 'Space\Name',
    ];
    $content = '<?php
namespace Space;
/**
 * The name.
 */
class Name {}
';
    $cases[] = [$expected, $content];

    // NOT a doc comment block.
    $expected = [
      'fqcn' => 'Space\Name',
      T_NAMESPACE => 'Space',
      T_CLASS => 'Space\Name',
    ];
    $content = '<?php
namespace Space;
/*
 * The name.
 */
class Name {}
';
    $cases[] = [$expected, $content];

    // The last doc comment block applies (without namespace).
    $expected = [
      'fqcn' => 'Name',
      T_DOC_COMMENT => '/**
 * Class docblock.
 */',
      T_CLASS => 'Name',
    ];
    $content = '<?php
/**
 * File docblock.
 */
/**
 * Class docblock.
 */
class Name {}
';
    $cases[] = [$expected, $content];

    // A namespace delimits the doc comment block search scope.
    $expected = [
      'fqcn' => 'Space\Name',
      T_NAMESPACE => 'Space',
      T_CLASS => 'Space\Name',
    ];
    $content = '<?php
/**
 * File docblock.
 */
namespace Space;
class Name {}
';
    $cases[] = [$expected, $content];

    // A function delimits the doc comment block search scope.
    $expected = [
      'fqcn' => 'Name',
      T_CLASS => 'Name',
    ];
    $content = '<?php
/**
 * File docblock.
 */
function foo() {}
class Name {}
';
    $cases[] = [$expected, $content];

    // Any garbage between the doc comment and the class/element is ignored.
    // @see http://php.net/manual/en/reflectionclass.getdoccomment.php#115286
    $expected = [
      'fqcn' => 'Space\Name',
      T_DOC_COMMENT => '/**
 * Class docblock.
 */',
      T_NAMESPACE => 'Space',
      T_USE => ['Baz' => 'Bar\Baz'],
      T_CLASS => 'Space\Name',
    ];
    $content = '<?php
/**
 * File docblock.
 */
namespace Space;
  /**
 * Class docblock.
 */  

use Bar\Baz;
const FOO = "BAR";
$ns = "bar";
$a = 2 + 1;
#/** what? */
// ^^ A single-line T_DOC_COMMENT is invisible by commenting it out.
count(array());

class Name {}
';
    $cases[] = [$expected, $content];

    // Abstract.
    $expected = [
      'fqcn' => 'Name',
      T_ABSTRACT => TRUE,
      T_CLASS => 'Name',
    ];
    $content = '<?php
abstract class Name {}
';
    $cases[] = [$expected, $content];

    // Final.
    $expected = [
      'fqcn' => 'Name',
      T_FINAL => TRUE,
      T_CLASS => 'Name',
    ];
    $content = '<?php
final class Name {}
';
    $cases[] = [$expected, $content];

    // Extends / Implements.
    $expected = [
      'fqcn' => 'White\Space',
      T_NAMESPACE => 'White',
      T_CLASS => 'White\Space',
      T_EXTENDS => ['White\Grey'],
      T_IMPLEMENTS => ['White\Dust'],
    ];
    $content = '<?php
namespace White;
class Space
  extends Grey
  implements Dust
{
}
';
    $cases[] = [$expected, $content];

    foreach ($cases as &$case) {
      $case[0] += $this->defaults;
    }
    return $cases;
  }

  /**
   * @covers ::resolveName
   * @dataProvider providerResolveName
   */
  public function testResolveName($expected, $namespace, $name, $imports = array()) {
    $reflector = new ReflectionClass($this->name, $this->path);
    $method = new \ReflectionMethod($reflector, 'resolveName');
    $method->setAccessible(TRUE);
    $this->assertSame($expected, $method->invoke($reflector, $namespace, $name, $imports));
  }

  public function providerResolveName() {
    return [
      // Basic namespaced elements.
      ['Name',                 '', 'Name'],
      ['Name',                 '', '\Name'],
      ['Global\Name',          '', 'Global\Name'],
      ['Namespace\Name',       'Namespace', 'Name'],
      ['Some\Space\Name',      'Some', 'Space\Name'],
      ['Some\Space\Name',      'Some\Space', 'Name'],
      ['Some\Space\Some\Name', 'Some\Space', 'Some\Name'],
      // Basic namespaced elements + irrelevant import.
      ['Name',                 '', 'Name', ['Foo' => 'Foo']],
      ['Name',                 '', '\Name', ['Foo' => 'Foo']],
      ['Global\Name',          '', 'Global\Name', ['Foo' => 'Foo']],
      ['Namespace\Name',       'Namespace', 'Name', ['Foo' => 'Foo']],
      ['Some\Space\Name',      'Some', 'Space\Name', ['Foo' => 'Foo']],
      ['Some\Space\Name',      'Some\Space', 'Name', ['Foo' => 'Foo']],
      ['Some\Space\Some\Name', 'Some\Space', 'Some\Name', ['Foo' => 'Foo']],
      // Imported elements and namespaces.
      ['Name',                 '', 'Name', ['Name' => 'Name']],
      ['Name',                 '', '\Name', ['Name' => 'Name']],
      ['Name',                 '', '\Name', ['Name' => '\Name']],
      ['Imported\Name',        '', 'Name', ['Name' => 'Imported\Name']],
      ['Imported\Name',        'Namespace', 'Name', ['Name' => 'Imported\Name']],
      ['Imported\Space\Name',  'Namespace', 'Space\Name', ['Space' => 'Imported\Space']],
      // Aliasesed imported elements and namespaces.
      ['Imported\Name',        'Name\Space', 'Alias', ['Alias' => 'Imported\Name']],
      ['Imported\Space\Alias', 'Name\Space', 'AliasedSpace\Alias', ['AliasedSpace' => 'Imported\Space']],
    ];
  }

  /**
   * @covers ::getDocComment
   */
  public function testGetDocComment() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertInstanceOf('Sun\StaticReflection\ReflectionDocComment', $reflector->getDocComment());
    $this->assertSame($this->info[T_DOC_COMMENT], (string) $reflector->getDocComment());
  }

  /**
   * @covers ::getFileName
   */
  public function testGetFileName() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame($this->path, $reflector->getFileName());
  }

  /**
   * @covers ::getInterfaceNames
   * @dataProvider providerGetInterfaceNames
   */
  public function testGetInterfaceNames(array $interfaces) {
    $reflector = $this->getClassReflectorMock(array(
      T_IMPLEMENTS => $interfaces,
    ));
    $this->assertSame($interfaces, $reflector->getInterfaceNames());
  }

  public function providerGetInterfaceNames() {
    return [
      [[]],
      [['FooInterface']],
      [['FooInterface', 'Bar\BazInterface']],
    ];
  }

  /**
   * @covers ::getModifiers
   * @dataProvider providerGetModifiers
   */
  public function testGetModifiers($expected, array $info) {
    $reflector = $this->getClassReflectorMock($info);
    $this->assertSame($expected, $reflector->getModifiers());
  }

  public function providerGetModifiers() {
    return [
      [0, []],
      [\ReflectionClass::IS_EXPLICIT_ABSTRACT, [T_ABSTRACT => TRUE]],
      [\ReflectionClass::IS_FINAL, [T_FINAL => TRUE]],
      [\ReflectionClass::IS_EXPLICIT_ABSTRACT | \ReflectionClass::IS_FINAL, [T_ABSTRACT => TRUE, T_FINAL => TRUE]],
    ];
  }

  /**
   * @covers ::getName
   */
  public function testGetName() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame($this->name, $reflector->getName());
  }

  /**
   * @covers ::getNamespaceName
   */
  public function testGetNamespaceName() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame($this->info[T_NAMESPACE], $reflector->getNamespaceName());
  }

  /**
   * @covers ::getShortName
   */
  public function testGetShortName() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame('Example', $reflector->getShortName());
  }

  /**
   * @covers ::implementsInterface
   * @dataProvider providerImplementsInterface
   */
  public function testImplementsInterface($expected, array $info, $ancestor) {
    $reflector = $this->getClassReflectorMock($info, ['isSubclassOfAnyAncestors']);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfAnyAncestors');

    $this->assertSame($expected, $reflector->implementsInterface($ancestor));
  }

  public function providerImplementsInterface() {
    return [
      // Elements without ancestors.
      [FALSE, [T_TRAIT => 'FooTrait'], 'CheckedInterface'],
      [FALSE, [], 'CheckedInterface'],
      [FALSE, [T_CLASS => 'FooClass'], 'CheckedInterface'],
      [FALSE, [T_INTERFACE => 'FooInterface'], 'CheckedInterface'],
      // Interface self-test.
      [TRUE,  [T_INTERFACE => 'CheckedInterface'], 'CheckedInterface'],
      // Statically reflected cases.
      [TRUE,  [T_CLASS => 'FooClass', T_IMPLEMENTS => ['FooInterface']], 'FooInterface'],
      [TRUE,  [T_INTERFACE => 'FooInterface', T_EXTENDS => ['OtherInterface']], 'OtherInterface'],
    ];
  }

  /**
   * @covers ::implementsInterface
   * @covers ::isSubclassOfAnyAncestors
   * @runInSeparateProcess
   */
  public function testImplementsInterfaceReturnsFalseIfNotImplemented() {
    $reflector = $this->getClassReflectorMock(array(
      T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
      T_CLASS => 'Sun\Tests\StaticReflection\Fixtures\Example',
      T_IMPLEMENTS => ['Sun\Tests\StaticReflection\Fixtures\ExampleInterface'],
    ));

    $this->assertSame(FALSE, $reflector->implementsInterface('NotImplemented'));
  }

  /**
   * @covers ::implementsInterface
   * @covers ::isSubclassOfAnyAncestors
   * @runInSeparateProcess
   */
  public function testImplementsInterfaceReturnsTrueIfImplemented() {
    $reflector = $this->getClassReflectorMock(array(
      T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
      T_CLASS => 'Sun\Tests\StaticReflection\Fixtures\Example',
      T_EXTENDS => ['Sun\Tests\StaticReflection\Fixtures\Base\Example'],
      T_IMPLEMENTS => ['Sun\Tests\StaticReflection\Fixtures\ExampleInterface'],
    ));

    $this->assertSame(TRUE, $reflector->implementsInterface('Sun\Tests\StaticReflection\Fixtures\Base\ExtendedInterface'));
  }

  /**
   * @covers ::implementsInterface
   * @covers ::isSubclassOfAnyAncestors
   * @runInSeparateProcess
   */
  public function testImplementsInterfaceReturnsTrueIfExtended() {
    $reflector = $this->getClassReflectorMock(array(
      T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
      T_CLASS => 'Sun\Tests\StaticReflection\Fixtures\Example',
      T_EXTENDS => ['Sun\Tests\StaticReflection\Fixtures\Base\Example'],
      T_IMPLEMENTS => ['Sun\Tests\StaticReflection\Fixtures\ExampleInterface'],
    ));

    $this->assertSame(TRUE, $reflector->implementsInterface('Sun\Tests\StaticReflection\Fixtures\Base\BaseInterface'));
  }

  /**
   * @covers ::inNamespace
   * @dataProvider providerInNamespace
   */
  public function testInNamespace($expected, $namespace, $class) {
    $reflector = $this->getClassReflectorMock(array(
      T_NAMESPACE => $namespace,
      T_CLASS => $class,
    ));
    $this->assertSame($expected, $reflector->inNamespace());
  }

  public function providerInNamespace() {
    return [
      [FALSE, '', 'Foo'],
      [TRUE,  'Foo', 'Bar'],
      [TRUE,  'Foo\Bar', 'Baz'],
    ];
  }

  public function providerIsBoolean() {
    return [[FALSE], [TRUE]];
  }

  /**
   * @covers ::isAbstract
   * @dataProvider providerIsBoolean
   */
  public function testIsAbstract($value) {
    $reflector = $this->getClassReflectorMock(array(
      T_ABSTRACT => $value,
    ));
    $this->assertSame($value, $reflector->isAbstract());
  }

  /**
   * @covers ::isFinal
   * @dataProvider providerIsBoolean
   */
  public function testIsFinal($value) {
    $reflector = $this->getClassReflectorMock(array(
      T_FINAL => $value,
    ));
    $this->assertSame($value, $reflector->isFinal());
  }

  /**
   * @covers ::isInstantiable
   * @dataProvider providerIsInstantiable
   */
  public function testIsInstantiable($expected, $info) {
    $reflector = $this->getClassReflectorMock($info);
    $this->assertSame($expected, $reflector->isInstantiable());
  }

  public function providerIsInstantiable() {
    return [
      [FALSE, [T_ABSTRACT => TRUE]],
      [FALSE, [T_INTERFACE => 'FooInterface']],
      [FALSE, [T_TRAIT => 'FooTrait']],
      [TRUE,  [T_CLASS => 'FooClass']],
      [TRUE,  [T_CLASS => 'FooClass', T_FINAL => TRUE]],
    ];
  }

  /**
   * @covers ::isInterface
   * @dataProvider providerIsInterface
   */
  public function testIsInterface($expected, $info) {
    $reflector = $this->getClassReflectorMock($info);
    $this->assertSame($expected, $reflector->isInterface());
  }

  public function providerIsInterface() {
    return [
      [FALSE, [T_ABSTRACT => TRUE]],
      [FALSE, [T_TRAIT => 'FooTrait']],
      [FALSE, [T_CLASS => 'FooClass']],
      [TRUE,  [T_INTERFACE => 'FooInterface']],
    ];
  }

  /**
   * @covers ::isInternal
   */
  public function testIsInternal() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame(FALSE, $reflector->isInternal());
  }

  /**
   * @covers ::isIterateable
   * @dataProvider providerIsIterateable
   */
  public function testIsIterateable($expected, array $info) {
    $reflector = $this->getClassReflectorMock($info);
    $this->assertSame($expected, $reflector->isIterateable());
  }

  public function providerIsIterateable() {
    return [
      [FALSE, []],
      [FALSE, [T_IMPLEMENTS => ['FooInterface']]],
      [FALSE, [T_IMPLEMENTS => ['IteratorInterface']]],
      [TRUE,  [T_IMPLEMENTS => ['Traversable']]],
      [TRUE,  [T_IMPLEMENTS => ['IteratorAggregate']]],
      [TRUE,  [T_IMPLEMENTS => ['Iterator']]],
      [TRUE,  [T_IMPLEMENTS => ['ArrayIterator']]],
    ];
  }

  /**
   * @covers ::isSubclassOf
   * @dataProvider providerIsSubclassOfStatic
   */
  public function testIsSubclassOf($expected, $ancestor) {
    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', ['isSubclassOfAnyAncestors'], [$this->name, $this->path]);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfAnyAncestors');

    $this->assertSame($expected, $reflector->isSubclassOf($ancestor));
  }

  public function providerIsSubclassOfStatic() {
    return [
      [FALSE, $this->info[T_CLASS]],
      [TRUE,  $this->info[T_EXTENDS][0]],
      [TRUE,  $this->info[T_IMPLEMENTS][0]],
      [TRUE,  $this->info[T_IMPLEMENTS][1]],
      [TRUE,  $this->info[T_IMPLEMENTS][2]],
      [TRUE,  $this->info[T_IMPLEMENTS][3]],
    ];
  }

  /**
   * @covers ::isSubclassOf
   * @dataProvider providerIsSubclassOfFalseConditions
   */
  public function testIsSubclassOfSelfOrNothing($info, $ancestor) {
    $reflector = $this->getClassReflectorMock($info, ['isSubclassOfAny', 'isSubclassOfAnyAncestors']);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfAny');
    $reflector
      ->expects($this->never())
      ->method('isSubclassOfAnyAncestors');

    $this->assertFalse($reflector->isSubclassOf($ancestor));
  }

  public function providerIsSubclassOfFalseConditions() {
    return [
      [[], 'NoAncestors'],
      [[T_CLASS => 'FooClass'], 'FooClass'],
      [[T_INTERFACE => 'FooInterface'], 'FooInterface'],
      [[T_TRAIT => 'FooTrait'], 'FooTrait'],
    ];
  }

  /**
   * @covers ::isSubclassOf
   * @covers ::isSubclassOfAnyAncestors
   * @runInSeparateProcess
   */
  public function testIsSubclassOfReturnsTrueIfImplemented() {
    $reflector = $this->getClassReflectorMock(array(
      T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
      T_CLASS => 'Sun\Tests\StaticReflection\Fixtures\Example',
      T_EXTENDS => ['Sun\Tests\StaticReflection\Fixtures\Base\Example'],
      T_IMPLEMENTS => ['Sun\Tests\StaticReflection\Fixtures\ExampleInterface'],
    ));

    $this->assertSame(TRUE, $reflector->isSubclassOf('Sun\Tests\StaticReflection\Fixtures\Base\ExtendedInterface'));
  }

  /**
   * @covers ::isSubclassOfAny
   * @dataProvider providerIsSubclassOfAny
   */
  public function testIsSubclassOfAny($expected, array $parents, array $interfaces, array $candidates) {
    $reflector = $this->getClassReflectorMock(array(
      T_EXTENDS => $parents,
      T_IMPLEMENTS => $interfaces,
    ), ['isSubclassOfAnyAncestors']);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfAnyAncestors');

    $this->assertSame($expected, $reflector->isSubclassOfAny($candidates));
  }

  public function providerIsSubclassOfAny() {
    $parents = ['Parent1', 'Parent2'];
    $interfaces = ['Interface1', 'Interface2'];
    return [
      [FALSE, $parents, $interfaces, ['OtherParent']],
      [FALSE, $parents, $interfaces, ['OtherParent1', 'OtherParent2']],
      [TRUE,  $parents, $interfaces, ['Parent1']],
      [TRUE,  $parents, $interfaces, ['Interface1']],
      [TRUE,  $parents, $interfaces, ['OtherParent', 'Parent1']],
      [TRUE,  $parents, $interfaces, ['NotImplemented', 'Interface1']],
      [TRUE,  $parents, $interfaces, ['Parent1', 'OtherParent']],
      [TRUE,  $parents, $interfaces, ['Interface1', 'NotImplemented']],
    ];
  }

  /**
   * @covers ::isSubclassOf
   * @covers ::isSubclassOfAnyAncestors
   * @dataProvider providerIsSubclassOfStatic
   */
  public function testIsSubclassOfAnyAncestorsIsNotCalledOnStaticMatch($expected, $ancestor) {
    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', ['isSubclassOfAnyAncestors'], [$this->name, $this->path]);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfAnyAncestors');

    $this->assertSame($expected, $reflector->isSubclassOf($ancestor));
  }

  /**
   * @covers ::isSubclassOf
   * @covers ::isSubclassOfAnyAncestors
   * @runInSeparateProcess
   */
  public function testIsSubclassOfAnyAncestorsLoadsParentOfParentClass() {
    $parent        = 'Sun\Tests\StaticReflection\Fixtures\Base\Example';
    $parent_parent = 'Sun\Tests\StaticReflection\Fixtures\Base\Root';
    $this->assertFalse(class_exists($parent, FALSE));
    $this->assertFalse(class_exists($parent_parent, FALSE));

    $reflector = $this->getClassReflectorMock(array(
      T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
      T_EXTENDS => [$parent],
    ));

    $this->assertSame(TRUE, $reflector->isSubclassOf($parent_parent));

    $this->assertTrue(class_exists($parent, FALSE));
    $this->assertTrue(class_exists($parent_parent, FALSE));
  }

  /**
   * @covers ::isSubclassOf
   * @covers ::isSubclassOfAnyAncestors
   * @runInSeparateProcess
   */
  public function testIsSubclassOfAnyAncestorsLoadsInterfaceOfInterface() {
    $parent        = 'Sun\Tests\StaticReflection\Fixtures\ExampleInterface';
    $parent_parent = 'Sun\Tests\StaticReflection\Fixtures\Base\ExtendedInterface';
    $this->assertFalse(interface_exists($parent, FALSE));
    $this->assertFalse(interface_exists($parent_parent, FALSE));

    $reflector = $this->getClassReflectorMock(array(
      T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
      T_CLASS => 'Sun\Tests\StaticReflection\Fixtures\Example',
      T_IMPLEMENTS => [$parent],
    ));

    $this->assertSame(TRUE, $reflector->isSubclassOf($parent_parent));

    $this->assertTrue(interface_exists($parent, FALSE));
    $this->assertTrue(interface_exists($parent_parent, FALSE));
  }

  /**
   * @covers ::isSubclassOf
   * @covers ::isSubclassOfAnyAncestors
   * @runInSeparateProcess
   */
  public function testIsSubclassOfAnyAncestorsReturnsFalseIfNotContained() {
    $reflector = $this->getClassReflectorMock(array(
      T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
      T_CLASS => 'Sun\Tests\StaticReflection\Fixtures\Example',
      T_EXTENDS => ['Sun\Tests\StaticReflection\Fixtures\Base\Example'],
    ));

    $this->assertSame(FALSE, $reflector->isSubclassOf('NotImplemented'));
  }

  /**
   * @covers ::isTrait
   * @dataProvider providerIsTrait
   */
  public function testIsTrait($expected, $info) {
    $reflector = $this->getClassReflectorMock($info);
    $this->assertSame($expected, $reflector->isTrait());
  }

  public function providerIsTrait() {
    return [
      [FALSE, [T_ABSTRACT => TRUE]],
      [FALSE, [T_FINAL => TRUE]],
      [FALSE, [T_INTERFACE => 'FooInterface']],
      [FALSE, [T_CLASS => 'FooClass']],
      [TRUE,  [T_TRAIT => 'FooTrait']],
    ];
  }

  /**
   * @covers ::isUserDefined
   */
  public function testIsUserDefined() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame(TRUE, $reflector->isUserDefined());
  }

  /**
   * @covers ::basename
   * @dataProvider providerBasename
   */
  public function testBasename($expected, $fqcn) {
    $this->assertSame($expected, ReflectionClass::basename($fqcn));
  }

  public function providerBasename() {
    return [
      ['Example', 'Sun\Tests\StaticReflection\Fixtures\Example'],
      ['Example', '\Sun\Tests\StaticReflection\Fixtures\Example'],
      ['Example', 'Example'],
      ['Example', '\Example'],
    ];
  }

}
