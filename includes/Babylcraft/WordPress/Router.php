<?php
namespace Babylcraft\WordPress;

//refactor me: core classes should not depend on specific plugins
use Babylcraft\Plugins\Bookings\BabylonBookings;

/**
 * Facade class for the router implementation so can "easily" swap it out
 * should that become necessary
 */
class Router
{
    private $wp_router;

  //don't call this function directly except in plugin bootstrapping
  //use getRouter() instead
    public function __construct(\WP_Router $router)
    {
        $this->wp_router = $router;
    }

  /**
   * Get the singleton Router object instead of making a new
   * one you nong.
   *
   * @return Router object from Pimple
   */
    public static function getRouter() : Router
    {
        assert(
            function_exists('babylGetServices'),
            AssertionError('global fn babylGetServices() does not exist')
        );

        return babylGetServices()[BabylonBookings::ROUTER];
    }

  /**
   * Adds a GET uri to WordPress
   *
   * @param $as            for named routes
   * @param $uri           the uri to hit
   * @param $uses          anonymous, named or class function
   * @param $preHandlers   names of pre handlers, null for none, else either a
   *                       string or array of string for multiple
   */
    public function addGet(
        string $as,
        string $uri,
        callable $uses,
        string $prefix = null,
        $preHandlers = null
    ) {
        $this->wp_router->get(
            [
            'as' => $as,
            'uri' => $uri,
            'uses' => $uses,
            'prefix' => $prefix,
            'middlewares' => $preHandlers
            ]
        );
    }
}
