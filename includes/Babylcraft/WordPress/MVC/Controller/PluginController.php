<?php
namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\Util;

use Babylcraft\WordPress\Plugin\Config\IPluginInfo;

/**
 * Template class for Controller objects with factory
 * method for constructing from instance of IPluginInfo
 */
abstract class PluginController {
  protected $util;
  protected $pluginAPI;
  private $viewPath;

  //todo refactor so this class doesn't need to know details of how
  //its constructed and can just be new()ed.

  /**
   * Create a new Controller, passing in dependencies
   * @param PluginAPI   object for hooking into WordPress events
   */
  public function __construct(PluginAPI $pluginAPI,
            //todo rename this parameter
            Util $util,
            string $viewPath) {
    $this->pluginAPI = $pluginAPI;
    $this->util = $util; //todo rename this variable
    $this->viewPath = $viewPath;

    $this->registerHooks($pluginAPI);
  }

  protected abstract function registerHooks(PluginAPI $pluginAPI);

  /*
   * Must override. View files are by convention assumed to be located in
   * the $this->viewPath/$this->getControllerName()/ directory. This convention
   * is used by various functions of this class to get view markup or js files
   * for require_once'ing or wp_enqueue_script'ing.
   */
  protected abstract function getControllerName() : string;

  /*
   * Returns the full path and name of the view markup/PHP script.
   * This can then be require_once'd by controller functions to render
   * a given view.
   *
   * By convention the view markup is assumed to be in the path returned
   * from $this->getViewLocation() and named $viewName.php
   *
   * @param $viewName   The view name to get markup for
   */
  protected function getViewMarkupFile(string $viewName) : string {
    return "{$this->getViewLocation()}/${viewName}.php";
  }

  /*
   * Enqueues the view JS files given by viewName. By convention the view
   * script is assumed to be in the path returned from $this->getViewLocation()
   * and named $viewName.js. jQuery is added as a dependency.
   */
  protected function enqueueViewScript(string $viewName) {
    $this->enqueueScript($this->getViewScriptHandle($viewName),
      "{$this->getViewLocationURI()}{$viewName}.js", 'jquery');
  }

  /*
   * Enqueues the script given by scriptHandle. The script is
   * assumed to be in the View directory for this Controller
   *
   * @param $scriptHandle   name of script, minus the '.js' part
   */
  protected function enqueueOtherScript(string $scriptHandle) {
    wp_enqueue_script(
      $scriptHandle, "{$this->getViewLocationURI()}{$scriptHandle}.js",
      'jquery');
  }

  /*
   * Returns a string that can be used as a handle for the view script
   * in calls to wp_enqueue_script(), wp_localize_script() and
   * check_admin_referer()
   */
  protected function getViewScriptHandle(string $viewName) : string {
    return "{$this->getControllerName()}$viewName";
  }

  private function enqueueScript(string $scriptHandle,
    string $scriptPathAndName, string $dependencies) {
    wp_enqueue_script($scriptHandle, $scriptPathAndName, $dependencies,
      //Plugin::VERSION,
      "0.0.1", //todo update me
      //load scripts in footer to enable last-minute localising
      $in_footer = true);
  }

  private function getViewLocation() : string {
    return "$this->viewPath/{$this->getControllerName()}";
  }

  protected function getViewLocationURI() : string {
    return $this->util->getPathURI($this->getViewLocation(), false);
  }

  protected const ERROR_NOT_PERMITTED = 1;
  protected const ERROR_MISSING_DATA_FROM_CLIENT = 2;
  protected function sendJSONError(string $actionName, int $errorCode) {
    $message = "$actionName: ";
    switch($errorCode) {
      case Controller::ERROR_NOT_PERMITTED:
        $message .= 'not permitted for user';
        break;
      case Controller::ERROR_MISSING_DATA_FROM_CLIENT:
        $message .= 'missing data from request';
        break;
      default:
        Util::logMessage("Unknown error code $errorCode", __FILE__, __LINE__);
        $message .= "unknown error $errorCode";
    }

    wp_send_json_error(["message" => $message]);
  }
}