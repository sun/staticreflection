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
      'Foo' => 'Foo',
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
   *
   * @return PHPUnit_Mock_Object
   */
  private function getClassReflectorMock(array $return = array()) {
    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', array('reflect'), array($this->name, $this->path));

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

    $expected = [
      T_NAMESPACE => 'White',
      T_USE => ['Grey' => 'Black\Grey'],
      T_CLASS => 'White\Space',
      T_EXTENDS => ['Black\Grey'],
      T_IMPLEMENTS => ['White\Dust'],
    ];
    $content = '<?php
namespace White;
use Black\Grey;
class Space
  extends Grey
  implements Dust
{
}
';
    $cases[] = [$expected, $content];

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
      // Base conditions.
      ['Name',                 '', 'Name'],
      ['Name',                 '', '\Name'],
      ['Global\Name',          '', 'Global\Name'],
      ['Namespace\Name',       'Namespace', 'Name'],
      ['Some\Space\Name',      'Some', 'Space\Name'],
      ['Some\Space\Name',      'Some\Space', 'Name'],
      ['Some\Space\Some\Name', 'Some\Space', 'Some\Name'],
      // Base conditions + irrelevant import.
      ['Name',                 '', 'Name', ['Foo' => 'Foo']],
      ['Name',                 '', '\Name', ['Foo' => 'Foo']],
      ['Global\Name',          '', 'Global\Name', ['Foo' => 'Foo']],
      ['Namespace\Name',       'Namespace', 'Name', ['Foo' => 'Foo']],
      ['Some\Space\Name',      'Some', 'Space\Name', ['Foo' => 'Foo']],
      ['Some\Space\Name',      'Some\Space', 'Name', ['Foo' => 'Foo']],
      ['Some\Space\Some\Name', 'Some\Space', 'Some\Name', ['Foo' => 'Foo']],
      // Actual imports.
      ['Name',                 '', 'Name', ['Name' => 'Name']],
      ['Name',                 '', '\Name', ['Name' => 'Name']],
      ['Name',                 '', '\Name', ['Name' => '\Name']],
      ['Imported\Name',        '', 'Name', ['Name' => 'Imported\Name']],
      ['Imported\Name',        'Namespace', 'Name', ['Name' => 'Imported\Name']],
      ['Imported\Space\Name',  'Namespace', 'Space\Name', ['Space' => 'Imported\Space']],
      // @todo Aliases.
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
  public function testImplementsInterface($expected, $ancestor) {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame($expected, $reflector->implementsInterface($ancestor));
  }

  public function providerImplementsInterface() {
    return [
      [FALSE, $this->info[T_CLASS]],
      [FALSE, basename($this->info[T_CLASS])],
      [FALSE, $this->info[T_EXTENDS][0]],
      [TRUE,  $this->info[T_IMPLEMENTS][0]],
      [TRUE,  $this->info[T_IMPLEMENTS][1]],
      [TRUE,  $this->info[T_IMPLEMENTS][2]],
      [TRUE,  $this->info[T_IMPLEMENTS][3]],
      // Lastly, trigger an actual reflection.
      [TRUE,  'Sun\Tests\StaticReflection\Fixtures\Base\InvisibleInterface'],
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
   * @dataProvider providerIsSubclassOfDeep
   */
  public function testIsSubclassOf($expected, $ancestor) {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame($expected, $reflector->isSubclassOf($ancestor));
  }

  public function providerIsSubclassOfStatic() {
    return [
      [FALSE, $this->info[T_CLASS]],
      [FALSE, basename($this->info[T_CLASS])],
      [FALSE, 'Foo'],
      [TRUE,  $this->info[T_EXTENDS][0]],
      [TRUE,  $this->info[T_IMPLEMENTS][0]],
      [TRUE,  $this->info[T_IMPLEMENTS][1]],
      [TRUE,  $this->info[T_IMPLEMENTS][2]],
      [TRUE,  $this->info[T_IMPLEMENTS][3]],
    ];
  }

  public function providerIsSubclassOfDeep() {
    return [
      [TRUE, 'Sun\Tests\StaticReflection\Fixtures\Base\InvisibleInterface'],
    ];
  }

  /**
   * @covers ::isSubclassOfReal
   * @dataProvider providerIsSubclassOfStatic
   */
  public function testIsSubclassOfDirectMatch($expected, $ancestor) {
    $reflector = $this->getMock('Sun\StaticReflection\ReflectionClass', ['isSubclassOfReal'], [$this->name, $this->path]);

    $reflector
      ->expects($this->never())
      ->method('isSubclassOfReal');

    $this->assertSame($expected, $reflector->isSubclassOf($ancestor));
  }

  /**
   * @covers ::isSubclassOfAny
   * @dataProvider providerIsSubclassOfAny
   */
  public function testIsSubclassOfAny($expected, array $parents, array $interfaces, array $candidates) {
    $reflector = $this->getClassReflectorMock(array(
      T_EXTENDS => $parents,
      T_IMPLEMENTS => $interfaces,
    ));
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

}
