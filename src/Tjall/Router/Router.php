<?php
    namespace Tjall\Router;

    use Tjall\Router\Config;
    use Tjall\Router\Lib;
    use Tjall\Router\Http\Request;
    use Tjall\Router\Http\Response;
    use Tjall\Router\Http\Status;
    use Exception;
    use Tjall\Router\RoutesGroup;
    use Tjall\Router\Handlers\ErrorHandler;
    use Tjall\Router\Handlers\MiddlewareHandler;

    class Router {
        protected static array $routes = [];
        protected static array $errorRoutes = [];
        public static ?RoutesGroup $currentRoutesGroup = null;
        public static $router;
        public static Request $request;
        public static Response $response;
        public static object $addMiddleware;

        static function run(?array $config = []) {
            // Store config
            Config::store($config);

            // Load routes
            $routes_dir = Lib::joinPaths(Config::get('rootDir'), Config::get('routes.dir'));
            Lib::requireAll($routes_dir);

            // Setup error handler in production mode
            if(Config::get('mode') !== 'dev') {
                set_error_handler([ ErrorHandler::class, 'handle' ]);
            }

            // Start router
            try {
                @static::$router->run();
            } catch(\Exception $e) {
                ErrorHandler::handle($e);
            }

            // Throw 404 if no matching route was found
            if(!isset(static::$response))
                static::callErrorRoutes(Status::NOT_FOUND);

            static::$response->end();
        }

        protected static function callErrorRoutes(int $status) {
            if(!isset(static::$errorRoutes[$status]))
                throw new Exception("Failed with status code $status.");

            foreach (static::$errorRoutes[$status] as $route) {
                $route->call();
            }
        }

        static function group(callable $add_routes): RoutesGroup {
            return new RoutesGroup($add_routes);
        }

        static function error(int $status, callable $callback): void {
            $route = new Route(null, null, $callback, null);
            static::$errorRoutes[$status] = static::$errorRoutes[$status] ?? [];

            array_push(static::$errorRoutes[$status], $route);
        }

        static function match(string $method, string $url, callable $callback): Route {
            $route = new Route($method, $url, $callback, static::$currentRoutesGroup);

            static::$router->match($route->method, $route->url, function(...$params) use($route) {
                return $route->call($params);
            });
            
            return $route;
        }

        static function all(string $url, callable $callback): void {
            static::match('GET|POST|PUT|PATCH|OPTIONS|DELETE', $url, $callback);
        }
        
        static function get(string $url, callable $callback): void {
            static::match('GET', $url, $callback);
        }

        static function post(string $url, callable $callback): void {
            static::match('POST', $url, $callback);
        }

        static function put(string $url, callable $callback): void {
            static::match('PUT', $url, $callback);
        }

        static function patch(string $url, callable $callback): void {
            static::match('PATCH', $url, $callback);
        }

        static function options(string $url, callable $callback): void {
            static::match('OPTIONS', $url, $callback);
        }

        static function delete(string $url, callable $callback): void {
            static::match('DELETE', $url, $callback);
        }
        
        static function _init() {
            static::$router = new \Bramus\Router\Router();
        }
    }

    Router::_init();