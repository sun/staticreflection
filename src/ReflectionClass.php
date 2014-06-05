<?php

/**
 * @file
 * 
 */

namespace Sun\StaticReflection;

/**
 * 
 *
 * Wraps \ReflectionClass for type-hint compatibility.
 *
 * @author Daniel F. Kudwien (sun)
 */
class ReflectionClass extends \ReflectionClass {

  private $classname;
  private $pathname;
  private $info;
  private static $ancestorCache = array();

  public function __construct($classname, $pathname = NULL) {
    $this->classname = $classname;
    $this->pathname = $pathname;
  }

  public function parse() {
    if (isset($this->info)) {
      return $this->info;
    }
    $content = $this->readContent();
    $this->info = self::parseContent($content);
    return $this->info;
  }

  public function readContent() {
    $content = '';

    // @todo SplFileObject is a CPU/memory hog of its own.
//    $file = new \SplFileObject($this->pathname);
//    while (!$file->eof()) {
//      $lines[] = $line = $file->fgets();
//      if (preg_match('@^\s*(?:(?:abstract|final)\s+)?(?:interface|class|trait)\s+\w+@', $line)) {
//        break;
//      }
//    }
//    unset($file);
    $file = fopen($this->pathname, 'r');
    while (FALSE !== $line = fgets($file)) {
      $content .= $line;
      if (preg_match('@^\s*(?:(?:abstract|final)\s+)?(?:interface|class|trait)\s+\w+@', $line)) {
        break;
      }
    }
    fclose($file);

    // Strip '{' and trailing whitespace from definition.
//    $content = preg_replace('@[\{\s]+$@s', '', $content);
    $content = trim($content, " \t\r\n{");

    return $content;
  }

  /**
   *
   *
   * Vastly simplified re-implementation of Doctrine's TokenParser.
   *
   * @see \Doctrine\Common\Annotations\TokenParser
   */
  public static function parseContent($content) {
    $tokens = token_get_all($content);

//    $debug = $tokens;
//    foreach ($debug as &$token) {
//      if (is_array($token)) {
//        $token[0] = token_name($token[0]);
//      }
//    }
//    echo var_dump($debug), "\n";

    $result = array(
      T_DOC_COMMENT => array(),
      T_NAMESPACE => '',
      T_USE => array(),
      T_ABSTRACT => FALSE,
      T_FINAL => FALSE,
      T_CLASS => '',
      T_INTERFACE => '',
      T_TRAIT => '',
      T_EXTENDS => array(),
      T_IMPLEMENTS => array(),
    );
    /** @var mixed Reference to the last discovered result context. */
    $context = NULL;
    /** @var int   The ID of the last discovered result context token. */
    $context_id = NULL;

    foreach ($tokens as $token) {
      if (is_array($token)) {
        if (isset($result[$id = $token[0]])) {
          // Enter a new context.
          // For simple string contexts (e.g., T_NAMESPACE, T_CLASS) all code
          // subsequent code is appended until either a PHP statement delimiter
          // (e.g., ';') is encountered or new context is entered.
          $context = &$result[$id];
          $context_id = $id;

          // All doc comments are recorded; the last one wins. (see below)
          if ($id === T_DOC_COMMENT) {
            $context[] = $token[1];
            unset($context, $context_id);
          }
          // Create a new sub-element for contexts supporting multiple values.
          elseif ($id === T_USE || $id === T_IMPLEMENTS || $id === T_EXTENDS) {
            $context = &$context[];
            $context = '';
          }
          // Boolean flags.
          elseif ($id === T_ABSTRACT || $id === T_FINAL) {
            $context = TRUE;
            unset($context, $context_id);
          }
        }
        // Not a result context; append content to last result context.
        elseif (isset($context_id)) {
          if ($id === T_AS) {
            unset($context, $context_id);
          }
          elseif ($id !== T_WHITESPACE && $id !== T_COMMENT) {
            $context .= $token[1];
          }
        }
      }
      // Append simple strings to last result context.
      elseif (isset($context_id)) {
        // Create a new sub-element for T_IMPLEMENTS + T_EXTENDS (interfaces).
        if ($token === ',') {
          $context = &$result[$context_id][];
          $context = '';
        }
        // Force-terminate last result context upon PHP statement delimiters.
        elseif ($token === ';' || $token === '{') {
          unset($context, $context_id);
        }
        else {
          $context .= $token;
        }
      }
    }

    // The last doc comment belongs to the class.
    $result[T_DOC_COMMENT] = end($result[T_DOC_COMMENT]) ?: '';

    // Prepare import aliases.
    foreach ($result[T_USE] as $alias => $fqcn) {
      $result[T_USE][basename($fqcn)] = $fqcn;
      unset($result[T_USE][$alias]);
    }

    // Resolve class, parent class and interface names.
    foreach (array(T_CLASS, T_INTERFACE, T_TRAIT) as $id) {
      if ($result[$id] !== '') {
        $result[$id] = self::resolveName($result[T_NAMESPACE], $result[$id]);
      }
      else {
        $result[$id] = FALSE;
      }
    }
    foreach ($result[T_EXTENDS] as &$ancestor) {
      $ancestor = self::resolveName($result[T_NAMESPACE], $ancestor, $result[T_USE]);
    }
    foreach ($result[T_IMPLEMENTS] as &$interface) {
      $interface = self::resolveName($result[T_NAMESPACE], $interface, $result[T_USE]);
    }

    return $result;
  }

  /**
   * 
   */
  private static function resolveName($namespace, $name, $imports = array()) {
    if ($name[0] === '\\') {
      return substr($name, 1);
    }
    if ($imports) {
      if (isset($imports[$alias = basename($name)])) {
        return $imports[$alias];
      }
      $space = strtok($name, '\\');
      if (isset($imports[$space])) {
        return $imports[$space] . substr($name, strlen($space));
      }
    }
    if ($namespace === '') {
      return $name;
    }
    return $namespace . '\\' . $name;
  }

  public function getDocComment() {
    $this->parse();
    return $this->info[T_DOC_COMMENT];
  }

  /**
   * 
   *
   * @see \PHPUnit_Util_Test::parseAnnotations()
   * @see \Tonic\Application::parseDocComment()
   */
  public function parseDocComment() {
    $docblock = $this->getDocComment();
    $result = array();
    $result['summary'] = $this->parseSummary($docblock);
    $result += $this->parseAnnotations($docblock);
    return $result;
  }

  /**
   * 
   *
   * @param string $docblock
   *   The doc comment block to parse.
   *
   * @return string
   *   The parsed PHPDoc summary line.
   */
  public function parseSummary($docblock) {
    $content = preg_replace([
      // Strip trailing '*/', leading '/**', and '*' prefixes.
      '@^[ \t]*\*+/$|^[ \t]*/?\*+[ \t]*@m',
      // Normalize line endings.
      '@\r?\n@',
      // Strip everything starting with the first PHPDoc tag/annotation.
      '/^@.+/ms',
    ], ['', "\n", ''], $docblock);

    preg_match('@\n?(.+?)(?=\n\n)@s', $content, $matches);
    if (isset($matches[1])) {
      $summary = $matches[1];
    }
    else {
      $summary = substr($content, 1);
    }
    return trim(strtr($summary, "\n", ' '));
  }

  /**
   * 
   *
   * @param string $docblock
   *   The doc comment block to parse.
   *
   * @return array
   *   The parsed annotations.
   *
   * @see \PHPUnit_Util_Test::parseAnnotations()
   * @author Sebastian Bergmann <sebastian@phpunit.de>
   * @copyright 2001-2014 Sebastian Bergmann <sebastian@phpunit.de>
   */
  public function parseAnnotations($docblock) {
    $annotations = array();
    // Strip away the docblock header and footer to ease parsing of one line
    // annotations.
    $docblock = substr($docblock, 3, -2);

    if (preg_match_all('/@(?P<name>[A-Za-z_-]+)(?:[ \t]+(?P<value>.*?))?[ \t]*\r?$/m', $docblock, $matches)) {
      $numMatches = count($matches[0]);
      for ($i = 0; $i < $numMatches; ++$i) {
        $annotations[$matches['name'][$i]][] = $matches['value'][$i];
      }
    }
    return $annotations;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileName() {
    return $this->pathname;
  }

  //public function getInterfaceNames() {
  //public function getModifiers() {

  public function getName() {
    return $this->classname;
  }

  public function getNamespaceName() {
    $this->parse();
    return $this->info[T_NAMESPACE];
  }

  public function getShortName() {
    return basename($this->classname);
  }

  /**
   * {@inheritdoc}
   */
  public function implementsInterface($class) {
    $this->parse();
    // Check for a direct match first.
    if ($this->info[T_IMPLEMENTS]) {
      if (in_array($class, $this->info[T_IMPLEMENTS], TRUE)) {
        return TRUE;
      }
      // If no direct match, inspect each interface.
      // This causes interfaces and dependent classes to get autoloaded.
      foreach ($this->info[T_IMPLEMENTS] as $interface) {
        if ($this->isSubclassOfReal($interface, $class)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  //public function inNamespace() {

  /**
   * {@inheritdoc}
   */
  public function isAbstract() {
    $this->parse();
    return $this->info[T_ABSTRACT];
  }

  /**
   * {@inheritdoc}
   */
  public function isFinal() {
    $this->parse();
    return $this->info[T_FINAL];
  }

  /**
   * {@inheritdoc}
   */
  public function isInstantiable() {
    $this->parse();
    return $this->info[T_CLASS] && !$this->info[T_ABSTRACT];
    return !$this->info[T_ABSTRACT] && !$this->info[T_INTERFACE] && !$this->info[T_TRAIT];
  }

  /**
   * {@inheritdoc}
   */
  public function isInterface() {
    $this->parse();
    return $this->info[T_INTERFACE];
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  //public function isIterateable() {

  /**
   * {@inheritdoc}
   */
  public function isSubclassOf($class) {
    $this->parse();
    // Check for a direct match first.
    if ($this->info[T_EXTENDS]) {
      if (in_array($class, $this->info[T_EXTENDS], TRUE)) {
        return TRUE;
      }
    }
    // Same as implementsInterface(), inlined to avoid autoloading on match.
    if ($this->info[T_IMPLEMENTS]) {
      if (in_array($class, $this->info[T_IMPLEMENTS], TRUE)) {
        return TRUE;
      }
    }
    // If no direct match, inspect the parents of each parent.
    if ($this->info[T_EXTENDS]) {
      // This causes parent classes to be autoloaded.
      foreach ($this->info[T_EXTENDS] as $parent) {
        if ($this->isSubclassOfReal($parent, $class)) {
          return TRUE;
        }
      }
    }
    return $this->implementsInterface($class);
  }

  /**
   * Returns whether the reflected class is a subclass of one of the specified
   * classes.
   *
   * Same as isSubclassOf(), but allows to check multiple classes at once for a
   * direct match (as an OR condition), so as to avoid autoloading of all parent
   * classes and interfaces.
   */
  public function isSubclassOfAny(array $classes) {
    $this->parse();
    if ($this->info[T_EXTENDS]) {
      if (array_intersect($this->info[T_EXTENDS], $classes)) {
        return TRUE;
      }
    }
    if ($this->info[T_IMPLEMENTS]) {
      if (array_intersect($this->info[T_IMPLEMENTS], $classes)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Wrapper around is_subclass_of().
   *
   * Enables tests to guarantee that classes are not autoloaded upon direct
   * match.
   */
  private function isSubclassOfReal($ancestor, $child) {
    if (!isset(self::$ancestorCache[$ancestor])) {
      self::$ancestorCache[$ancestor] = array();
      self::$ancestorCache[$ancestor] += class_parents($ancestor) ?: array();
      self::$ancestorCache[$ancestor] += class_implements($ancestor) ?: array();
    }
    return in_array($child, self::$ancestorCache[$ancestor], TRUE);

    $result = is_subclass_of($ancestor, $child);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function isTrait() {
    $this->parse();
    return $this->info[T_TRAIT];
  }

  /**
   * {@inheritdoc}
   */
  public function isUserDefined() {
    return TRUE;
  }

}
