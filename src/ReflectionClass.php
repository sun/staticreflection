<?php

/**
 * @file
 * Contains \Sun\StaticReflection\ReflectionClass.
 */

namespace Sun\StaticReflection;

/**
 * Statically reflects a PHP class.
 *
 * Use this class when operating on many PHP class files that have been
 * discovered upfront and which need to be minimally validated at the
 * class-level (e.g., testing for base classes/interfaces).
 *
 * Native PHP facilities like \ReflectionClass and is_subclass_of() would:
 * 1. trigger the classloader to autoload each file
 * 2. trigger the classloader to recursively autoload all parent classes and
 *    interfaces
 * 3. exceed reasonable CPU and memory consumption very quickly.
 *
 * Usage is identical to \ReflectionClass. For that reason (and type-hint
 * compatibility), this class wraps \ReflectionClass.
 *
 * Note: The read-only public property \ReflectionClass::$name does not get
 * populated by this implementation. Use ReflectionClass::getName() instead.
 *
 * Optionally, the doc comment block of the statically reflected class can be
 * parsed for its PHPDoc summary line as well as (simple) tags/annotations.
 *
 * @author Daniel F. Kudwien (sun)
 *
 * @todo Dynamically instantiate the wrapped \ReflectionClass in case a parent
 *   method requiring native/non-static reflection is called.
 */
class ReflectionClass extends \ReflectionClass {

  private $classname;
  private $pathname;
  private $info;
  private static $ancestorCache = array();

  /**
   * Constructs a new ReflectionClass.
   *
   * @param string $classname
   *   The fully-qualified class name (FQCN) to reflect.
   * @param string $pathname
   *   The pathname of the file containing $classname. If omitted, a native
   *   \ReflectionClass will be instantiated.
   *
   * @throws \ReflectionException
   *   If the given $classname is not located in the given $pathname.
   */
  public function __construct($classname, $pathname = NULL) {
    // If the pathname is unknown, it must be retrieved from \ReflectionClass.
    // If a class instance was passed then there's no point in omitting
    // \ReflectionClass, since code has been loaded already.
    if (!isset($pathname) || !is_string($classname)) {
      parent::__construct($classname);
      $this->classname = is_object($classname) ? get_class($classname) : $classname;
      $this->pathname = parent::getFileName();
    }
    else {
      $this->classname = $classname;
      $this->pathname = $pathname;
    }

    // Resemble \ReflectionClass instantiation.
    $this->info = $this->reflect();
  }

  /**
   * Statically reflects the PHP class file.
   */
  protected function reflect() {
    $content = $this->readFileHeader();
    $info = self::tokenize($content);

    if ($info['fqcn'] !== $this->classname) {
      throw new \ReflectionException(vsprintf('Expected %s but found %s in %s.', array(
        $this->classname,
        $info['fqcn'],
        $this->pathname,
      )));
    }
    return $info;
  }

  /**
   * Reads the PHP class file header.
   *
   * @todo Throw \ReflectionException on 404.
   */
  private function readFileHeader() {
    $content = '';

    // \SplFileObject is very resource-intensive when operating on thousands of
    // files. Use legacy functions until PHP core improves.
    $file = fopen($this->pathname, 'r');
    while (FALSE !== $line = fgets($file)) {
      $content .= $line;
      if (preg_match('@^\s*(?:(?:abstract|final)\s+)?(?:interface|class|trait)\s+\w+@', $line)) {
        break;
      }
    }
    fclose($file);
    unset($file);

    // Strip '{' and (most importantly trailing) whitespace from definition.
    $content = trim($content, " \t\r\n{");
    return $content;
  }

  /**
   * Tokenizes the file (header) content of a PHP class file.
   *
   * @param string $content
   *   The PHP file (header) content to tokenize.
   *
   * @return array
   *   An associative array containing the parsed results, keyed by PHP
   *   Tokenizer tokens:
   *   - fqcn: The FQCN of the parsed element.
   *   - T_NAMESPACE: The namespace (if any).
   *   - One of T_CLASS, T_INTERFACE, T_TRAIT: The FQCN of the parsed element
   *     (the other two will be FALSE).
   *   - T_EXTENDS, T_IMPLEMENTS: FQCNs of ancestors.
   *   - T_USE: Imported namespaces (if any), keyed by local alias.
   *   - T_ABSTRACT, T_FINAL: Respective Boolean flags.
   *   - T_DOC_COMMENT: The doc comment block of the class.
   *
   * This is a vastly simplified re-implementation of Doctrine's TokenParser.
   * @see \Doctrine\Common\Annotations\TokenParser
   *
   * @todo Add public static utility method returning translated values.
   */
  private static function tokenize($content) {
    $tokens = token_get_all($content);

    $result = array(
      'fqcn' => '',
      T_DOC_COMMENT => array(),
      T_NAMESPACE => '',
      T_USE => array(),
      T_AS => '',
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
        elseif (isset($context_id) && $id !== T_WHITESPACE && $id !== T_COMMENT) {
          $context .= $token[1];
        }
      }
      // Append simple strings to last result context.
      elseif (isset($context_id)) {
        // Create a new sub-element for T_IMPLEMENTS, T_EXTENDS, T_USE.
        if ($token === ',') {
          $context = &$result[$context_id][];
          $context = '';
        }
        // Terminate last result context upon PHP statement delimiters.
        elseif ($token === ';' || $token === '{') {
          // When terminating 'use' or 'as', inject the local alias as key.
          if ($context_id === T_AS) {
            $import = array_pop($result[T_USE]);
            $result[T_USE][$context] = $import;
            $context = '';
          }
          elseif ($context_id === T_USE) {
            $import = array_pop($result[T_USE]);
            $result[T_USE][self::basename($import)] = $import;
          }
          unset($context, $context_id);
        }
      }
    }
    unset($result[T_AS]);

    // The last doc comment belongs to the class.
    $result[T_DOC_COMMENT] = end($result[T_DOC_COMMENT]) ?: '';

    // Resolve class, parent class, interface, and ancestor names.
    foreach (array(T_CLASS, T_INTERFACE, T_TRAIT) as $id) {
      if ($result[$id] !== '') {
        $result[$id] = self::resolveName($result[T_NAMESPACE], $result[$id]);
        $result['fqcn'] = $result[$id];
      }
      else {
        $result[$id] = FALSE;
      }
    }
    foreach (array(T_EXTENDS, T_IMPLEMENTS) as $id) {
      foreach ($result[$id] as &$ancestor) {
        $ancestor = self::resolveName($result[T_NAMESPACE], $ancestor, $result[T_USE]);
      }
    }

    return $result;
  }

  /**
   * Resolves the name of an ancestor class.
   *
   * @param string $namespace
   *   The namespace context of the parsed class.
   * @param string $name
   *   The name to resolve against $namespace.
   * @param array $imports
   *   An associative array of imported namespaces, keyed by local alias, as
   *   parsed by ReflectionClass::tokenize().
   *
   * @return string
   *   $name resolved against $namespace and $imports.
   */
  private static function resolveName($namespace, $name, array $imports = array()) {
    // Strip namespace prefix, if any.
    if ($name[0] === '\\') {
      return substr($name, 1);
    }
    if ($imports) {
      // If $name maps directly to an imported namespace alias, use its FQCN.
      if (isset($imports[$name])) {
        return $imports[$name];
      }
      // Otherwise, check whether $name up until the first namespace separator
      // maps to an alias. If so, prefix $name with its FQCN.
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

  /**
   * {@inheritdoc}
   */
  public function getDocComment() {
    return $this->info[T_DOC_COMMENT];
  }

  /**
   * Parses the doc block comment of the class.
   *
   * @return array
   *   An associative array whose key 'summary' contains the PHPDoc summary line
   *   of the class. Other keys contain the PHPDoc tags/annotations (if any).
   *
   * @todo Replace with separate getDocCommentSummary() + getAnnotations() methods.
   */
  public function parseDocComment() {
    $docblock = $this->getDocComment();
    $result = array();
    $result['summary'] = self::parseSummary($docblock);
    $result += self::parseAnnotations($docblock);
    return $result;
  }

  /**
   * Parses the summary line from the class doc comment block.
   *
   * @param string $docblock
   *   The doc comment block to parse.
   *
   * @return string
   *   The parsed PHPDoc summary line.
   *
   * @todo Split docblock cleaning/stripping into separate method.
   */
  private static function parseSummary($docblock) {
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
   * Parses PHPDoc tags/annotations from the class doc comment block.
   *
   * @param string $docblock
   *   The doc comment block to parse.
   *
   * @return array
   *   The parsed annotations. Each value is an array of values.
   *
   * @see \PHPUnit_Util_Test::parseAnnotations()
   * @author Sebastian Bergmann <sebastian@phpunit.de>
   * @copyright 2001-2014 Sebastian Bergmann <sebastian@phpunit.de>
   */
  private static function parseAnnotations($docblock) {
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

  /**
   * {@inheritdoc}
   *
   * Note that only interfaces implemented directly on the statically reflected
   * class are returned.
   */
  public function getInterfaceNames() {
    return $this->info[T_IMPLEMENTS];
  }

  /**
   * {@inheritdoc}
   */
  public function getModifiers() {
    $flags = 0;
    if ($this->isAbstract()) {
      $flags |= \ReflectionClass::IS_EXPLICIT_ABSTRACT;
    }
    if ($this->isFinal()) {
      $flags |= \ReflectionClass::IS_FINAL;
    }
    return $flags;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->classname;
  }

  /**
   * {@inheritdoc}
   */
  public function getNamespaceName() {
    return $this->info[T_NAMESPACE];
  }

  /**
   * {@inheritdoc}
   */
  public function getShortName() {
    return self::basename($this->classname);
  }

  /**
   * {@inheritdoc}
   *
   * Note: This method returns TRUE even if $class is not an interface; i.e.,
   * if the input is bogus. Static reflection should not be used if that level
   * of accuracy is required.
   */
  public function implementsInterface($class) {
    // Traits cannot implement interfaces.
    if ($this->info[T_TRAIT]) {
      return FALSE;
    }
    // An interface implements itself.
    if ($class === $this->info['fqcn']) {
      return TRUE;
    }
    return $this->isSubclassOf($class);
  }

  /**
   * {@inheritdoc}
   */
  public function inNamespace() {
    return !empty($this->info[T_NAMESPACE]);
  }

  /**
   * {@inheritdoc}
   */
  public function isAbstract() {
    return $this->info[T_ABSTRACT];
  }

  /**
   * {@inheritdoc}
   */
  public function isFinal() {
    return $this->info[T_FINAL];
  }

  /**
   * {@inheritdoc}
   */
  public function isInstantiable() {
    return $this->info[T_CLASS] && !$this->info[T_ABSTRACT];
  }

  /**
   * {@inheritdoc}
   */
  public function isInterface() {
    return !empty($this->info[T_INTERFACE]);
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isIterateable() {
    return array_intersect($this->info[T_IMPLEMENTS], array('Iterator', 'IteratorAggregate', 'Traversable')) || preg_grep('/Iterator$/', $this->info[T_IMPLEMENTS]);
  }

  /**
   * {@inheritdoc}
   */
  public function isSubclassOf($class) {
    if (!$this->info[T_EXTENDS] && !$this->info[T_IMPLEMENTS]) {
      return FALSE;
    }
    if ($class === $this->info['fqcn']) {
      return FALSE;
    }
    // Check for a direct match first.
    if ($this->isSubclassOfAny(array($class))) {
      return TRUE;
    }
    // Otherwise, inspect all ancestors. This causes all interfaces and parent
    // classes, and all of their dependencies to get autoloaded.
    if ($this->isSubclassOfAnyAncestors($this->info[T_EXTENDS], $class)) {
      return TRUE;
    }
    return $this->isSubclassOfAnyAncestors($this->info[T_IMPLEMENTS], $class);
  }

  /**
   * Returns whether the statically reflected class is a subclass of one of the
   * given classes.
   *
   * Same as isSubclassOf(), but allows to check multiple classes at once for a
   * direct match (as an OR condition), so as to avoid autoloading of all parent
   * classes and interfaces.
   *
   * Only use this as a separate precondition prior to calling
   * ReflectionClass::isSubclassOf() in order to improve performance when
   * testing many classes that commonly extend from certain base classes or
   * implement certain interfaces.
   *
   * @param string[] $classes
   *   A list of FQCNs to test against the statically reflected class.
   *
   * @return bool
   *   TRUE if the statically reflected class is a subclass of any class in
   *   $classes, FALSE otherwise. Note that indirect ancestor classes are NOT
   *   resolved; only direct matches in the statically reflected class may be
   *   found.
   *
   * @see ReflectionClass::isSubclassOf()
   */
  public function isSubclassOfAny(array $classes) {
    if ($this->info[T_EXTENDS] && array_intersect($this->info[T_EXTENDS], $classes)) {
      return TRUE;
    }
    return $this->info[T_IMPLEMENTS] && array_intersect($this->info[T_IMPLEMENTS], $classes);
  }

  /**
   * Returns whether a class is a subclass of a given class.
   *
   * To avoid loading the statically reflected class itself, this
   * re-implementation of is_subclass_of() is used to test against the ancestor
   * classes only (which will be reflected by PHP core); i.e., only the parent
   * class and interfaces.
   *
   * It uses an internal cache, because it is assumed that many classes inherit
   * from the same ancestors.
   *
   * @see is_subclass_of()
   */
  protected function isSubclassOfAnyAncestors(array $ancestors, $class) {
    foreach ($ancestors as $ancestor) {
      if (!isset(self::$ancestorCache[$ancestor])) {
        self::$ancestorCache[$ancestor] = array();
        self::$ancestorCache[$ancestor] += class_parents($ancestor) ?: array();
        self::$ancestorCache[$ancestor] += class_implements($ancestor) ?: array();
      }
      if (isset(self::$ancestorCache[$ancestor][$class])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isTrait() {
    return !empty($this->info[T_TRAIT]);
  }

  /**
   * {@inheritdoc}
   */
  public function isUserDefined() {
    return TRUE;
  }

  /**
   * Returns the basename (short name) of a class name.
   *
   * @param string $fqcn
   *   The fully-qualified class name for which to return the basename.
   *
   * @return string
   *
   * This function is named basename(), because basename() natively supports the
   * operation, but only on Windows. PHP core does not provide a native function.
   * This algorithm requires two lines of code, but has been measured to be the
   * most performant user space implementation.
   *
   * @see basename()
   */
  public static function basename($fqcn) {
    $parts = explode('\\', $fqcn);
    return end($parts);
  }

}
