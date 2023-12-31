<?php

namespace Scaliter;

use Scaliter\Assets;
use Scaliter\Router;
use Scaliter\Request;
use Scaliter\Visitor;
use Scaliter\Response;
use Scaliter\Database as DB;

class Render
{
    public static function render(string $twig_dir, string $twig_cache, bool $dev_mode = false,)
    {
        $Router = new Router($dev_mode);
        try {
            $Router->validate();

            DB::connect();
            $controller = $Router->Controller;
            $execute = new $controller();
            $method = $Router->Method;
            
            $RESPONSE = call_user_func_array(array($execute, $Router->Method), $Router->Params);

            if ($Router->Request == 'Controller') {
                $RESPONSE = array_merge($execute->response ?? [], $RESPONSE ?? []);
                $view = str_replace('Controller', '', $controller);

                $RESPONSE['statics'] = [
                    'styles' => Assets::get('styles', $execute->statics['styles'] ?? [], $view, $method),
                    'scripts' => Assets::get('scripts', $execute->statics['scripts'] ?? [], $view, $method),
                ];
                $RESPONSE['render'] = [
                    'view' => "Routes/$view.twig",
                    'page' =>  "Routes/$view/$method.twig"
                ];
                if (!$Router->JSON) {
                    $RESPONSE['statics'] = [
                        'styles' => Assets::get('styles', $execute->statics['styles'] ?? [], $view, $method),
                        'scripts' => Assets::get('scripts', $execute->statics['scripts'] ?? [], $view, $method),
                    ];
                    $options = $dev_mode ? [] : ['cache' => $twig_cache];
                    $loader = new \Twig\Loader\FilesystemLoader($twig_dir);
                    $twig = new \Twig\Environment($loader, $options);
                    $RESPONSE = $twig->render('index.twig', $RESPONSE);
                } else {
                    $RESPONSE['statics'] = [
                        'styles' => Assets::render('styles', $execute->statics['styles'] ?? [], $view, $method),
                        'scripts' => Assets::render('scripts', $execute->statics['scripts'] ?? [], $view, $method),
                    ];
                }
            }
            DB::disconnect();
        } catch (Response $e) {
            $RESPONSE = $e->message;
            if (!$Router->JSON) {
                if (isset($e->content['redirect_to'])) {
                    return header('Location: ' . $e->content['redirect_to']);
                } elseif (isset($e->content['redirect'])) {
                    return header('Location: ' . $e->content['redirect'] . '?redirect=' . Request::server('REQUEST_URI')->value(''));
                }
            }
        }
        return $Router->JSON ? json_encode($RESPONSE) : $RESPONSE;
    }
    public static function init(array $dir, array $dev_IP = [])
    {
        $dotenv = \Dotenv\Dotenv::createImmutable($dir['__DIR__']);
        $dotenv->load();

        $_SERVER['DOCUMENT_ROOT'] = $dir['__DIR__'];

        Assets::root($dir['assets_dir']);
        Assets::config($dir['assets_config']);

        Request::initialize($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
        Visitor::initialize();

        return self::render($dir['twig_dir'], $dir['twig_cache'], in_array(Visitor::$IP, $dev_IP));
    }
}
