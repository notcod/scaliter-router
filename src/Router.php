<?php

namespace Scaliter;

class Router
{
    public $Request, $Controller, $Method, $JSON = NULL;
    public array $Params = [];
    private static array $_ROUTES = [
        'Controller' => [
            '/' => 'HomeController', 'index'
        ]
    ];

    public static function view(string $url, array $call)
    {
        self::$_ROUTES['Controller'][$url]  = $call;
    }
    public static function get(string $url, array $call)
    {
        self::$_ROUTES['Response'][$url]    = $call;
    }
    public static function post(string $url, array $call)
    {
        self::$_ROUTES['Request'][$url]     = $call;
    }

    public function __construct(array $server)
    {
        $HTTP_CONTENT_HASH  = $server['HTTP_CONTENT_HASH']  ?? '';
        $REQUEST_METHOD     = $server['REQUEST_METHOD']     ?? '';
        $REQUEST_URL        = $server['REDIRECT_URL']       ?? '';

        $this->JSON = str_contains('application/json', $server['HTTP_ACCEPT'] ?? '') || php_sapi_name() == 'cli';

        $this->Request = $this->request($REQUEST_METHOD, $HTTP_CONTENT_HASH);

        if ($this->Request != 0) {
            $routes = self::$_ROUTES[$this->Request] ?? [];

            $mapper = $this->mapper($this->Request, $REQUEST_URL, $routes);

            $Controller   = $mapper[0];
            $Method       = $mapper[1];

            if (class_exists($Controller) && method_exists($Controller, $Method)) {
                $this->Controller   = $mapper[0];
                $this->Method       = $mapper[1];
                $this->Params       = $mapper[2];
            }
        }
    }

    private function request(string $request_method, string $http_hash)
    {
        return match (true) {
            $http_hash == '' && $request_method == 'GET' => 'Controller',
            $http_hash != '' && $request_method == 'GET' && $this->JSON => 'Response',
            $http_hash != '' && $request_method == 'POST' && $this->JSON => 'Request',
            $http_hash == '' && $request_method == '' && php_sapi_name() == 'cli' => 'Cron',
            default => 0
        };
    }

    private function mapper(string $request, string $uri, array $routes = [])
    {
        $params = array_diff(explode('/', $uri), array(""));
        if ($req = $routes[$uri] ?? NULL) {
            $class  = $req[0] ?? NULL;
            $method = $req[1] ?? NULL;
            $params = [];
        } elseif ($req = $this->params_try($params, $routes)) {
            $class  = $req[0] ?? NULL;
            $method = $req[1] ?? NULL;
            $params = $req[2] ?? [];
        } else {
            $class  = current($params) != NULL ? ucfirst(strtolower(array_shift($params))) . $request : NULL;
            $method = array_shift($params);
        }
        return [$class ?? NULL, $method ?? NULL, $params ?? []];
    }

    private function params_try(array $params, array $routes)
    {
        $params_try = [];
        while (count($params) >= 1) {
            $route_try = '/' . implode('/', $params) . '/';
            $req = $routes[$route_try] ?? false;
            if (!$req) {
                $params_try[] = array_pop($params);
            } else {
                return [$req[0] ?? NULL, $req[1] ?? NULL, $params_try];
            }
        }
        return false;
    }
}
