<?php

/**
 * @file
 * Contains \Sun\StaticReflection\ReflectionDocComment.
 */

namespace Sun\StaticReflection;

/**
 * Reflects a PHP doc comment block.
 *
 * Parses a doc comment for its PHPDoc summary line as well as (simple)
 * tags/annotations.
 *
 * @see \Sun\StaticReflection\ReflectionClass::getDocComment()
 * @author Daniel F. Kudwien (sun)
 */
class ReflectionDocComment {

  private $docblock;

  /**
   * Constructs a new ReflectionDocComment.
   *
   * @param string|\ReflectionClass $docblock
   *   A doc comment block string (including asterisks) or an instance of
   *   \ReflectionClass.
   *
   * @throws \InvalidArgumentException
   *   If $docblock is neither a string nor a \ReflectionClass instance.
   */
  public function __construct($docblock) {
    if (is_string($docblock)) {
      $this->docblock = $docblock;
    }
    elseif ($docblock instanceof \ReflectionClass) {
      $this->docblock = $docblock->getDocComment();
    }
    else {
      throw new \InvalidArgumentException(sprintf('Argument #1 to ReflectionDocComment must be a string or instance of \ReflectionClass, but %s was given.', is_object($docblock) ? get_class($docblock) : gettype($docblock)));
    }
  }

  /**
   * Returns the original doc comment block (including asterisks).
   *
   * @return string
   */
  public function getDocComment() {
    return $this->docblock;
  }

  /**
   * Returns the summary line of the class doc comment block.
   *
   * @return string
   *   The PHPDoc summary line.
   */
  public function getSummary() {
    return static::parseSummary(static::getPlainDocComment($this->getDocComment()));
  }

  /**
   * Returns the PHPDoc tags/annotations of the class doc comment block.
   *
   * @return array
   *   The parsed annotations. Each value is an array of values.
   */
  public function getAnnotations() {
    return static::parseAnnotations(static::getPlainDocComment($this->getDocComment()));
  }

  /**
   * Returns the cleaned doc commment block (without surrounding asterisks).
   *
   * @param string $docblock
   *   The original doc comment block (including asterisks).
   *
   * @return string
   */
  public static function getPlainDocComment($docblock) {
    $plainDocComment = preg_replace([
      // Strip trailing '*/', leading '/**', and '*' prefixes.
      '@^[ \t]*\*+/$|^[ \t]*/?\*+[ \t]?@m',
      // Normalize line endings.
      '@\r?\n@',
    ], ['', "\n"], $docblock);
    $plainDocComment = trim($plainDocComment, "\n");
    // Ensure that all lines end with a newline to simplify consuming code.
    if ($plainDocComment !== '') {
      $plainDocComment .= "\n";
    }
    return $plainDocComment;
  }

  /**
   * Parses the summary line from a plain doc comment block.
   *
   * @param string $plainDocComment
   *   The plain doc comment block (without surrounding asterisks), as returned
   *   by ReflectionDocComment::getPlainDocComment().
   *
   * @return string
   *   The PHPDoc summary line.
   */
  public static function parseSummary($plainDocComment) {
    // Strip everything starting with the first PHPDoc tag/annotation.
    $summary = preg_replace('/^@.+/ms', '', $plainDocComment);

    // Extract first paragraph (two newlines).
    if (preg_match('@(.+?)(?=\n\n)@s', $summary, $matches)) {
      $summary = $matches[1];
    }

    // Join multiple lines onto one.
    return trim(strtr($summary, "\n", ' '));
  }

  /**
   * Parses the PHPDoc tags/annotations of a plain doc comment block.
   *
   * Only single-line tags/annotations (key/value pairs) are supported.
   *
   * @param string $plainDocComment
   *   The plain doc comment block (without surrounding asterisks), as returned
   *   by ReflectionDocComment::getPlainDocComment().
   *
   * @return array
   *   The parsed annotations. Each value is an array of values.
   *
   * @see \PHPUnit_Util_Test::parseAnnotations()
   * @author Sebastian Bergmann <sebastian@phpunit.de>
   * @copyright 2001-2014 Sebastian Bergmann <sebastian@phpunit.de>
   */
  public static function parseAnnotations($plainDocComment) {
    $annotations = array();
    if (preg_match_all('/^[ \t]*@(?P<name>[A-Za-z_-]+)(?:[ \t]+(?P<value>.*?))?[ \t]*$/m', $plainDocComment, $matches)) {
      for ($i = 0, $ii = count($matches[0]); $i < $ii; ++$i) {
        $annotations[$matches['name'][$i]][] = $matches['value'][$i];
      }
    }
    return $annotations;
  }

  public function __toString() {
    return $this->getDocComment();
  }

}
