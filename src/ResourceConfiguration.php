<?php

namespace Drupal\restapi;

use Drupal\restapi\Exception\ClassNotValidException;
use Drupal\restapi\Exception\AuthClassNotValidException;
use Drupal\restapi\Auth\AuthenticationServiceInterface;
use Psr\Http\Message\RequestInterface;


/**
 * A configuration object for a resource.
 *
 * The configuration object holds metadata about the resource, and acts as a
 * factory for the main resource class and it's associated authentication
 * handler.
 *
 */
class ResourceConfiguration {

  /**
   * The raw path to the resource (e.g. items/%/thing).
   *
   * @var string
   *
   */
  protected $path = NULL;


  /**
   * The Drupal module that defined this resource.
   *
   * @var string
   *
   */
  protected $module = NULL;


  /**
   * The name of the class to be instantiated for this resource.
   *
   * @var string
   *
   */
  protected $class = NULL;


  /**
   * The name of the authentication class to use for this resource.
   *
   * @var string
   *
   */
  protected $auth_class = NULL;


  /**
   * The URL prefix to use for this resource.
   *
   * @var string
   *
   */
  protected $url_prefix = NULL;


  /**
   * Constructor
   *
   * @param string $path
   *   The raw path to the resource (e.g. items/%/thing).
   * @param string $module
   *   The module that defined this resource.
   * @param string $class
   *   The name of the class to be instantiated for this resource.
   * @param string $auth_class
   *   The name of the authentication class to use for this resource.
   * @param string $url_prefix
   *   (Optional) a string to use as the URL prefix for this resource.
   *
   * @throws ClassNotValidException
   * @throws AuthClassNotValidException
   *
   */
  public function __construct($path, $module, $class, $auth_class, $url_prefix = NULL) {

    if (!class_exists($class) || !in_array('Drupal\restapi\ResourceInterface', class_implements($class))) {
      $message = sprintf('The provided class %s does not exist, or is not an implementation of "Drupal\restapi\ResourceInterface".', $class);
      throw new ClassNotValidException($message);
    }

    if (!class_exists($auth_class) || !in_array('Drupal\restapi\Auth\AuthenticationServiceInterface', class_implements($auth_class))) {
      $message = sprintf('The provided authentication class %s does not exist, or is not an implementation of "Drupal\restapi\Auth\AuthenticationServiceInterface".', $class);
      throw new AuthClassNotValidException($message);
    }

    $this->path = $this->resolveTruePath($path, $url_prefix);
    $this->module = $module;
    $this->class = $class;
    $this->auth_class = $auth_class;
    $this->url_prefix = $url_prefix;

  }


  /**
   * Factory method to instantiate the resource.
   *
   * @param \StdClass $user
   *   A Drupal user object to access the resource as.
   * @param RequestInterface $request
   *   A HTTP request to set context for the resource.
   *
   * @return ResourceInterface
   *
   */
  public function invokeResource(\StdClass $user, RequestInterface $request) {
    $class = $this->getClass();
    return new $class($user, $request);
  }


  /**
   * Factory method to instantiate the authentication service.
   *
   * @param \StdClass $user
   *   A Drupal user object to access the resource as.
   * @param RequestInterface $request
   *   A HTTP request to set context for the authentication.
   *
   * @return AuthenticationServiceInterface
   *
   */
  public function invokeAuthenticationService(\StdClass $user, RequestInterface $request) {
    $class = $this->getAuthenticationClass();
    return new $class($user, $request);
  }


  /**
   * Returns the raw path of this resource.
   *
   * @return string
   *
   */
  public function getPath() {
    return $this->path;
  }


  /**
   * Returns the class name for this resource.
   *
   * @return string
   *
   */
  public function getClass() {
    return $this->class;
  }


  /**
   * Returns the module that defined this resource.
   *
   * @return string
   *
   */
  public function getModule() {
    return $this->module;
  }


  /**
   * Returns the authentication class for this resource.
   *
   * @returns string
   *
   */
  public function getAuthenticationClass() {
    return $this->auth_class;
  }


  /**
   * Returns a list of arguments for this resource, based on the provided path.
   *
   * @param string $path
   *   The path to generate arguments from.
   *
   * @return array
   *
   */
  public function getArgumentsForPath($path) {

    if (!$this->matchesPath($path)) {
      return [];
    }

    $arguments = [];
    $path      = explode('/', $path);

    foreach($this->getArgIndexes() as $index) {
      $arguments[] = $path[$index];
    }

    return $arguments;

  }


  /**
   * Determines if this resource will be matched to the provided path.
   *
   * The resource will match either a raw path (e.g. "items/%/thing") or a real
   * path (e.g. "items/123/thing".
   *
   * @param string $path
   *   The path to attempt to match to this resource.
   *
   * @return boolean
   *
   */
  public function matchesPath($path) {
    return ($this->getPath() == $path || preg_match($this->getMaskedPath(), $path));
  }


  /**
   * Returns an array of integers corresponding to the index of variables
   * within the path.
   *
   * @return array
   *
   */
  protected function getArgIndexes() {
    $parts = explode('/', $this->getPath());
    $args  = [];

    foreach($parts as $index => $part) {
      if ($part === '%') {
        $args[] = $index;
      }
    }

    return $args;
  }


  /**
   * Returns the regex masked path for this resource.
   *
   * Essentially, replaces any variable substitutions with a regex pattern
   * matching the variable. (e.g. "items/%/thing" becomes
   * "/items\/[^/]*\/thing".
   *
   * @return string
   *
   */
  protected function getMaskedPath() {
    return '#^' . str_replace('%', '.[^/]*', $this->getPath()) . '$#';
  }


  /**
   * Determines the real path, if a URL prefix has been used.
   *
   * @param string $path
   *   The original path of the resource.
   * @param string $prefix
   *   The URL prefix to use for this path.
   *
   * @return string
   *
   */
  protected function resolveTruePath($path, $prefix = NULL) {

    if (!$prefix) {
      return $path;
    }

    $path   = ltrim(trim($path), '/');
    $prefix = rtrim(ltrim(trim($prefix), '/'), '/');

    return $prefix . '/' . $path;

  }

}