<?php

namespace Scaliter;

use Scaliter\Cookie;
use Scaliter\Request;
use Scaliter\Response;

class Router
{
    public ?string $Request, $Controller, $Method = NULL;
    public bool $JSON = false;

    public array $Params = [];

    private string $http_hash;
    private string $http_url;
    private string $dev_mode;

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
    public function __construct(bool $dev_mode = false)
    {
        $this->dev_mode = $dev_mode;

        $this->http_hash    = Request::server('HTTP_CONTENT_HASH')->value('');
        $this->http_url     = Request::server('REDIRECT_URL')->value(''); // '/' : ''

        $REQUEST_METHOD     = Request::server('REQUEST_METHOD')->value('');
        $HTTP_ACCEPT        = Request::server('HTTP_ACCEPT')->value('');

        $this->JSON = str_contains('application/json', $HTTP_ACCEPT) || php_sapi_name() == 'cli';

        $this->Request = $this->request($REQUEST_METHOD, $this->http_hash);

        if ($this->Request != 0) {
            $routes = self::$_ROUTES[$this->Request] ?? [];

            $mapper = $this->mapper($this->Request, $this->http_url, $routes);

            $Controller   = $mapper[0];
            $Method       = $mapper[1];

            if (class_exists($Controller) && method_exists($Controller, $Method)) {
                $this->Controller   = $mapper[0];
                $this->Method       = $mapper[1];
                $this->Params       = $mapper[2];
            }
        }
    }

    public function validate()
    {
        $REQUEST_SID = Request::cookie('_SID')->value('');
        $REQUEST_SID = preg_replace('/[^a-zA-Z0-9]+/', '', $REQUEST_SID);

        if ($this->Controller == NULL || $this->Method == NULL)
            Response::error('404 Not Found', code: 404);

        if ($REQUEST_SID == '' || strlen($REQUEST_SID) != 64)
            Cookie::set('_SID', hash('sha256', mt_rand() . uniqid('scaliter', true)));

        if ($this->Request == 'Response')
            $this->validate_request($REQUEST_SID, Request::$query);

        if ($this->Request == 'Request')
            $this->validate_request($REQUEST_SID, Request::$request);
    }
    private function validate_request(string $REQUEST_SID, array $params)
    {
        $REQUEST_TYPE = substr($this->http_hash, 0, 1) == '0' ? 'Response' : 'Request';

        $sc_hash = 'url:' . $this->http_url . ';params:' . http_build_query($params, '', '&') . ';accept:' . $this->Request . ';token:' . $REQUEST_SID;

        if ($this->dev_mode) {
            header("SC_REQUEST_TYPE: $REQUEST_TYPE");
            header("SC_PRE_HASH: $sc_hash");
            header("SC_HASH: " . md5($sc_hash));
        }

        if (md5($sc_hash) != substr($this->http_hash, 1) || $REQUEST_TYPE != $this->Request)
            Response::error('401 Unauthorized', code: 401);
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
