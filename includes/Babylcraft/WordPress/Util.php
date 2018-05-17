<?php
namespace Babylcraft\WordPress;

//todo refactor this stuff into PluginAPI
class Util {
  public static function logContent(string $message, $content,
    $fileName = '', $lineNum = '') {
    if (true == WP_DEBUG) {
      if (is_array($content) || is_object($content)) {
        Util::logMessage("{$message}: \n". print_r($content, true), $fileName, $lineNum);
      } else {
        Util::logMessage("{$message}: \n{$content}");
      }
    }
  }

  public static function logMessage(string $message, $fileName = '', $lineNum = '') {
    $date = new \DateTime("now", new \DateTimeZone("Pacific/Auckland"));

    error_log(
      "\n\n-------Babylon begin-------"
      ."\n{$date->format('d/m/Y h:i:s a')}: $message\n"
      .($fileName ? "at $fileName" : '') . ($lineNum ? ": $lineNum" : '') ."\n"
      ."-------Babylon end-------\n\n");
  }

  public function isAdminDashboard() : bool {
    if (function_exists('get_current_screen')) {
      $adminPage = get_current_screen();
    }

    return $adminPage->base == 'dashboard';
  }

  /*
   * Converts the given path into a web-accessible URI.
   *
   * @param $useParent  Whether to ascend to the parent dir or not.
   *                    Normally WordPress assumes that the path
   *                    points to a file, so would return the parent
   *                    directory of the final path element. By passing
   *                    $useParent = false, you can get the URI to the
   *                    final path element itself (this is useful when
   *                    your $path points to the directory you are
   *                    interested in)
   */
  public function getPathURI(string $path, bool $useParent) : string {
    //plugin_dir_url always takes the parent dir of whatever's passed
    //in so I'm passing placeholder text to stay in the view directory
    return $useParent ?
      plugin_dir_url($path) :
      plugin_dir_url("$path/placeholdertext");
  }
}