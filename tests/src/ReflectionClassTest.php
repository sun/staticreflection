<?php

/**
 * @file
 * Contains \Sun\Tests\StaticReflection\ReflectionClassTest.
 */

namespace Sun\Tests\StaticReflection;

use Sun\StaticReflection\ReflectionClass;

/**
 * @coversDefaultClass \Sun\StaticReflection\ReflectionClass
 */
class ReflectionClassTest extends \PHPUnit_Framework_TestCase {

  private $name;
  private $path;

  private $info = array(
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
 * @see Example1Interface
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
      'Sun\Tests\StaticReflection\Fixtures\Example1Interface',
      'Sun\Tests\StaticReflection\Fixtures\Base\Example2Interface',
      'Sun\Tests\StaticReflection\Fixtures\Base\ImportedInterface',
      'Countable',
    ),
  );

  private $defaults = array(
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
    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', array('reflect') + $methods, array($this->name, $this->path));

    $reflector
      ->expects($this->any())
      ->method('reflect')
      ->will($this->returnValue($return + $this->defaults));

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

    // Abstract.
    $expected = [
      T_ABSTRACT => TRUE,
      T_CLASS => 'Name',
    ];
    $content = '<?php
abstract class Name {}
';
    $cases[] = [$expected, $content];

    // Final.
    $expected = [
      T_FINAL => TRUE,
      T_CLASS => 'Name',
    ];
    $content = '<?php
final class Name {}
';
    $cases[] = [$expected, $content];

    // Extends / Implements.
    $expected = [
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
    $this->assertSame($this->info[T_DOC_COMMENT], $reflector->getDocComment());
  }

  /**
   * @covers ::parseDocComment
   */
  public function testParseDocComment() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertEquals(array(
      'summary' => 'PHPDoc summary line. Summary may wrap.',
      'tag' => array(''),
      'single' => array('parameter'),
      'multiple' => array('type $param'),
      'see' => array('Example1Interface'),
    ), $reflector->parseDocComment());
  }

  /**
   * @covers ::parseSummary
   * @dataProvider providerParseSummary
   */
  public function testParseSummary($expected, $docblock) {
    $method = new \ReflectionMethod('Sun\StaticReflection\ReflectionClass', 'parseSummary');
    $method->setAccessible(TRUE);
    $this->assertSame($expected, $method->invoke(NULL, $docblock));
  }

  public function providerParseSummary() {
    return [
      ['', <<<EOC
/**
 */
EOC
],
      ['One line.', <<<EOC
/**
 * One line.
 */
EOC
],
      ['Squashed.', <<<EOC
/**
 *Squashed.
 */
EOC
],
      ['One line.', <<<EOC
/**
 * One line.
 *
 * Description.
 */
EOC
],
      ['First line. Second line.', <<<EOC
/**
 * First line.
 * Second line.
 */
EOC
],
      ['First line. Second line.', <<<EOC
/**
 * First line.
 * Second line.
 *
 * Description.
 */
EOC
],
      ['', <<<EOC
/**
 * @param string
 * @return array
 *   Return description.
 */
EOC
],
      ['Summary immediately followed by tags.', <<<EOC
/**
 * Summary immediately followed by tags.
 * @param string
 */
EOC
],
      ['Summary containing a @tag.', <<<EOC
/**
 * Summary containing a @tag.
 */
EOC
],
      ['Alternate doc comment end style.', <<<EOC
/**
 * Alternate doc comment end style.
**/
EOC
],
    ];
  }

  /**
   * @covers ::parseAnnotations
   * @dataProvider providerParseAnnotations
   */
  public function testParseAnnotations($expected, $docblock) {
    $method = new \ReflectionMethod('Sun\StaticReflection\ReflectionClass', 'parseAnnotations');
    $method->setAccessible(TRUE);
    $this->assertSame($expected, $method->invoke(NULL, $docblock));
  }

  public function providerParseAnnotations() {
    return [
      [[], <<<EOC
/**
 */
EOC
],
      [[], <<<EOC
/**
 * None.
 */
EOC
],
      [[], <<<EOC
/**
 * Summary containing a @tag.
 */
EOC
],
      [['tag' => ['']], <<<EOC
/**
 * One.
 * @tag
 */
EOC
],
      [['param' => ['string']], <<<EOC
/**
 * @param string
 */
EOC
],
      [['param' => ['spaced string']], <<<EOC
/**
 * @param spaced string
 */
EOC
],
      [['param' => ['string']], <<<EOC
/**
 * @param string
 *   Description.
 */
EOC
],
      [['param' => ['string', 'second'], 'group' => ['Foo']], <<<EOC
/**
 * @param string
 *   Description.
 * @param second
 * @group Foo
 */
EOC
],
      [['param' => ['string']], <<<EOC
/**
 * Alternate doc comment end style.
 * @param string
**/
EOC
],
      [['Squashed' => ['tag']], <<<EOC
/**
 *@Squashed tag
 */
EOC
],
    ];
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
    $reflector = $this->getClassReflectorMock($info, ['isSubclassOfReal']);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfReal');

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
      [TRUE,  [T_CLASS => 'FooClass', T_IMPLEMENTS => ['FooInterface']], 'FooInterface'],
      [TRUE,  [T_INTERFACE => 'FooInterface', T_EXTENDS => ['OtherInterface']], 'OtherInterface'],
    ];
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
   * @covers ::isSubclassOf
   * @dataProvider providerIsSubclassOfStatic
   */
  public function testIsSubclassOf($expected, $ancestor) {
    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', ['isSubclassOfReal'], [$this->name, $this->path]);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfReal');

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
    $reflector = $this->getClassReflectorMock($info, ['isSubclassOfAny', 'isSubclassOfReal', 'implementsInterface']);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfAny');
    $reflector
      ->expects($this->never())
      ->method('isSubclassOfReal');
    $reflector
      ->expects($this->never())
      ->method('implementsInterface');

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
   * @covers ::isSubclassOfAny
   * @dataProvider providerIsSubclassOfAny
   */
  public function testIsSubclassOfAny($expected, array $parents, array $interfaces, array $candidates) {
    $reflector = $this->getClassReflectorMock(array(
      T_EXTENDS => $parents,
      T_IMPLEMENTS => $interfaces,
    ), ['isSubclassOfReal']);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfReal');

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
   * @covers ::isSubclassOfReal
   * @dataProvider providerIsSubclassOfStatic
   */
  public function testIsSubclassOfRealIsNotCalledOnStaticMatch($expected, $ancestor) {
    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', ['isSubclassOfReal'], [$this->name, $this->path]);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfReal');

    $this->assertSame($expected, $reflector->isSubclassOf($ancestor));
  }

  /**
   * @covers ::isSubclassOf
   * @covers ::isSubclassOfReal
   * @runInSeparateProcess
   */
  public function testIsSubclassOfRealLoadsParentOfParentClass() {
    $parent        = 'Sun\Tests\StaticReflection\Fixtures\Base\Example';
    $parent_parent = 'Sun\Tests\StaticReflection\Fixtures\Base\Root';
    $this->assertFalse(class_exists($parent, FALSE));
    $this->assertFalse(class_exists($parent_parent, FALSE));

    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', ['reflect'], [$this->name, $this->path]);

    $reflector
      ->expects($this->exactly(2))
      ->method('reflect')
      ->will($this->returnValue(array(
        T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
        T_EXTENDS => [$parent],
      ) + $this->defaults));

    $this->assertSame(TRUE, $reflector->isSubclassOf($parent_parent));

    $this->assertTrue(class_exists($parent, FALSE));
    $this->assertTrue(class_exists($parent_parent, FALSE));
  }

  /**
   * @covers ::isSubclassOf
   * @covers ::implementsInterface
   * @covers ::isSubclassOfReal
   * @runInSeparateProcess
   */
  public function testIsSubclassOfRealLoadsInterfaceOfInterface() {
    $parent        = 'Sun\Tests\StaticReflection\Fixtures\Example1Interface';
    $parent_parent = 'Sun\Tests\StaticReflection\Fixtures\Base\InvisibleInterface';
    $this->assertFalse(interface_exists($parent, FALSE));
    $this->assertFalse(interface_exists($parent_parent, FALSE));

    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', ['reflect'], [$this->name, $this->path]);

    $reflector
      ->expects($this->exactly(4))
      ->method('reflect')
      ->will($this->returnValue(array(
        T_NAMESPACE => 'Sun\Tests\StaticReflection\Fixtures',
        T_CLASS => 'Sun\Tests\StaticReflection\Fixtures\Example',
        T_IMPLEMENTS => [$parent],
      ) + $this->defaults));

    $this->assertSame(TRUE, $reflector->isSubclassOf($parent_parent));

    $this->assertTrue(interface_exists($parent, FALSE));
    $this->assertTrue(interface_exists($parent_parent, FALSE));
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
