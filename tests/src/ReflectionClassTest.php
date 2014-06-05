<?php

/**
 * @file
 * Contains \Sun\StaticReflection\Tests\ReflectionClassTest.
 */

namespace Sun\StaticReflection\Tests;

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
    T_NAMESPACE => 'Sun\StaticReflection\Fixtures',
    T_USE => array(
      'ReflectionClass' => 'Sun\StaticReflection\ReflectionClass',
      'ImportedInterface' => 'Sun\StaticReflection\Fixtures\Base\ImportedInterface',
      'Foo' => 'Foo',
    ),
    T_ABSTRACT => TRUE,
    T_FINAL => FALSE,
    T_CLASS => 'Sun\StaticReflection\Fixtures\Example',
    T_INTERFACE => FALSE,
    T_TRAIT => FALSE,
    T_EXTENDS => array(
      'Sun\StaticReflection\Fixtures\Base\Example',
    ),
    T_IMPLEMENTS => array(
      'Sun\StaticReflection\Fixtures\Example1Interface',
      'Sun\StaticReflection\Fixtures\Base\Example2Interface',
      'Sun\StaticReflection\Fixtures\Base\ImportedInterface',
      'Countable',
    ),
  );

  public function setUp() {
    $this->name = 'Sun\StaticReflection\Fixtures\Example';
    $this->path = dirname(__DIR__) . '/fixtures/Example.php';
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertFalse(class_exists($this->name, FALSE));
  }

  /**
   * @covers ::parseContent
   */
  public function testParseContent() {
    #$this->markTestIncomplete();

    $reflector = new ReflectionClass($this->name, $this->path);
    $actual = $reflector->parseContent($reflector->readContent());
    $this->assertSame($this->info, $actual);
  }

  /**
   * @covers ::parseContent
   * @dataProvider providerParseContentRegressions
   */
  public function testParseContentRegressions($expected, $content) {
//    $reflector = new ReflectionClass($this->name, $this->path);
//    $actual = $reflector->parseContent($reflector->readContent());
    $this->assertEquals($expected, ReflectionClass::parseContent($content));
  }

  public function providerParseContentRegressions() {
    $defaults = array(
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
      $case[0] += $defaults;
    }
    return $cases;
  }

  /**
   * @covers ::resolveName
   * @dataProvider providerResolveName
   */
  public function testResolveName($expected, $namespace, $name, $imports = array()) {
    #$this->markTestIncomplete();

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
      // Aliases.
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
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame($expected, $reflector->parseSummary($docblock));
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
      [TRUE, $this->info[T_IMPLEMENTS][0]],
      [TRUE, $this->info[T_IMPLEMENTS][1]],
      [TRUE, $this->info[T_IMPLEMENTS][2]],
      [TRUE, $this->info[T_IMPLEMENTS][3]],
      // Lastly, trigger an actual reflection.
      [TRUE, 'Sun\StaticReflection\Fixtures\Base\InvisibleInterface'],
    ];
  }

  /**
   * @covers ::isAbstract
   */
  public function testIsAbstract() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame(TRUE, $reflector->isAbstract());
  }

  /**
   * @covers ::isFinal
   */
  public function testIsFinal() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame(FALSE, $reflector->isFinal());
  }

  /**
   * @covers ::isInstantiable
   */
  public function testIsInstantiable() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame(FALSE, $reflector->isInstantiable());
  }

  /**
   * @covers ::isInterface
   */
  public function testIsInterface() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame(FALSE, $reflector->isInterface());
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
      [TRUE, $this->info[T_EXTENDS][0]],
      [TRUE, $this->info[T_IMPLEMENTS][0]],
      [TRUE, $this->info[T_IMPLEMENTS][1]],
      [TRUE, $this->info[T_IMPLEMENTS][2]],
      [TRUE, $this->info[T_IMPLEMENTS][3]],
      [FALSE, 'Foo'],
    ];
  }

  public function providerIsSubclassOfDeep() {
    return [
      [TRUE, 'Sun\StaticReflection\Fixtures\Base\InvisibleInterface'],
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
    $reflector = new ReflectionClass($this->name, $this->path);
    $property = new \ReflectionProperty($reflector, 'info');
    $property->setAccessible(TRUE);
    $property->setValue($reflector, array(
      T_EXTENDS => $parents,
      T_IMPLEMENTS => $interfaces,
    ));
    $method = new \ReflectionMethod($reflector, 'isSubclassOfAny');
    $this->assertSame($expected, $method->invoke($reflector, $candidates));
  }

  public function providerIsSubclassOfAny() {
    $parents = ['Parent1', 'Parent2'];
    $interfaces = ['Interface1', 'Interface2'];
    return [
      [FALSE, $parents, $interfaces, ['OtherParent']],
      [FALSE, $parents, $interfaces, ['OtherParent1', 'OtherParent2']],
      [TRUE, $parents, $interfaces, ['Parent1']],
      [TRUE, $parents, $interfaces, ['Interface1']],
      [TRUE, $parents, $interfaces, ['OtherParent', 'Parent1']],
      [TRUE, $parents, $interfaces, ['NotImplemented', 'Interface1']],
      [TRUE, $parents, $interfaces, ['Parent1', 'OtherParent']],
      [TRUE, $parents, $interfaces, ['Interface1', 'NotImplemented']],
    ];
  }

  /**
   * @covers ::isTrait
   */
  public function testIsTrait() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame(FALSE, $reflector->isTrait());
  }

  /**
   * @covers ::isUserDefined
   */
  public function testIsUserDefined() {
    $reflector = new ReflectionClass($this->name, $this->path);
    $this->assertSame(TRUE, $reflector->isUserDefined());
  }

}
