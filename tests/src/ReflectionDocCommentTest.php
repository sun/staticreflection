<?php

/**
 * @file
 * Contains \Sun\Tests\StaticReflection\ReflectionDocCommentTest.
 */

namespace Sun\Tests\StaticReflection;

use Sun\StaticReflection\ReflectionDocComment;

/**
 * Tests ReflectionDocComment.
 *
 * @coversDefaultClass \Sun\StaticReflection\ReflectionDocComment
 */
class ReflectionDocCommentTest extends \PHPUnit_Framework_TestCase {

  /**
   * @covers ::__construct
   * @covers ::getDocComment
   * @covers ::__toString
   */
  public function testConstruct() {
    $docblock = '/** */';
    $reflector = new ReflectionDocComment($docblock);
    $this->assertInstanceOf('Sun\StaticReflection\ReflectionDocComment', $reflector);
    $this->assertSame($docblock, $reflector->getDocComment());
    $this->assertSame($docblock, (string) $reflector);
  }

  /**
   * @covers ::__construct
   * @covers ::getDocComment
   */
  public function testConstructWithInstance() {
    $rc = new \ReflectionClass($this);
    $docblock = $rc->getDocComment();
    $reflector = new ReflectionDocComment($rc);
    $this->assertSame($docblock, $reflector->getDocComment());
  }

  /**
   * @covers ::__construct
   * @expectedException \InvalidArgumentException
   */
  public function testConstructWithBadInstance() {
    $reflector = new ReflectionDocComment($this);
  }

  /**
   * @covers ::getSummary
   * @dataProvider providerParseSummary
   */
  public function testGetSummary() {
    $docblock = '/**
 * Summary.
 *
 * A description.
 *
 * @return self
 */';
    $reflector = new ReflectionDocComment($docblock);
    $this->assertSame('Summary.', $reflector->getSummary());
  }

  /**
   * @covers ::getAnnotations
   * @dataProvider providerParseAnnotations
   */
  public function testGetAnnotations() {
    $docblock = '/**
 * Summary.
 *
 * A description.
 *
 * @return self
 */';
    $reflector = new ReflectionDocComment($docblock);
    $this->assertSame(['return' => ['self']], $reflector->getAnnotations());
  }

  /**
   * @covers ::getPlainDocComment
   * @dataProvider providerGetPlainDocComment
   */
  public function testGetPlainDocComment($expected, $docblock) {
    $this->assertSame($expected, ReflectionDocComment::getPlainDocComment($docblock));
  }

  public function providerGetPlainDocComment() {
    return [
      ['', <<<EOC
/**
 */
EOC
],
      ['One line.
', <<<EOC
/**
 * One line.
 */
EOC
],
      ['Squashed.
', <<<EOC
/**
 *Squashed.
 */
EOC
],
      ['Extraneous leading newline.
', <<<EOC
/**
 *
 * Extraneous leading newline.
 */
EOC
],
      ['Extraneous trailing newline.
', <<<EOC
/**
 * Extraneous trailing newline.
 *
 */
EOC
],
      ['Extraneous white-space on blank line

 and leading white-space on this line.
', <<<EOC
/**
 * Extraneous white-space on blank line
 * 
 *  and leading white-space on this line.
 */
EOC
],
      ['First line.
Second line.

Description.
', <<<EOC
/**
 * First line.
 * Second line.
 *
 * Description.
 */
EOC
],
      ['Some
- List that
  wraps with indentation.
', <<<EOC
/**
 * Some
 * - List that
 *   wraps with indentation.
 */
EOC
],
      ['Some edge-case
* Markdown  
  formatted
* list
', <<<EOC
/**
 * Some edge-case
 * * Markdown  
 *   formatted
 * * list
 */
EOC
],
      ['Alternate doc comment end style.
', <<<EOC
/**
 * Alternate doc comment end style.
**/
EOC
],
    ];
  }

  /**
   * @covers ::parseSummary
   * @covers ::getPlainDocComment
   * @dataProvider providerParseSummary
   */
  public function testParseSummary($expected, $docblock) {
    $this->assertSame($expected, ReflectionDocComment::parseSummary(ReflectionDocComment::getPlainDocComment($docblock)));
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
      ['Extraneous leading newline.', <<<EOC
/**
 *
 * Extraneous leading newline.
 */
EOC
],
      ['Extraneous trailing newline.', <<<EOC
/**
 * Extraneous trailing newline.
 *
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
      ['Doc comment without inner asterisks.', <<<EOC
/**
 Doc comment without inner asterisks.
 */
EOC
],
    ];
  }

  /**
   * @covers ::parseAnnotations
   * @covers ::getPlainDocComment
   * @dataProvider providerParseAnnotations
   */
  public function testParseAnnotations($expected, $docblock) {
    $this->assertSame($expected, ReflectionDocComment::parseAnnotations(ReflectionDocComment::getPlainDocComment($docblock)));
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
 * Summary containing a @tag looking like a value.
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
      [['param' => ['string']], <<<EOC
/**
 Doc comment without inner asterisks.
 @param string
*/
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

}
