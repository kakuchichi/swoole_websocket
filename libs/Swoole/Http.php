<?php
namespace Swoole;

/**
 * Class Http_LAMP
 * @package Swoole
 */
class Http_PWS
{
    static function header($k, $v)
    {
        $k = ucwords($k);
        \Swoole::$php->response->send_head($k,$v);
    }
    static function status($code)
    {
        \Swoole::$php->response->send_http_status($code);
    }
    static function response($content)
    {
        global $php;
        $php->response->body = $content;
        self::finish();
    }
    static function redirect($url,$mode=301)
    {
        \Swoole::$php->response->send_http_status($mode);
        \Swoole::$php->response->send_head('Location',$url);
    }

    static function finish()
    {
        \Swoole::$php->request->finish = 1;
        throw new \Exception;
    }
}

class Http_LAMP
{
    static function header($k,$v)
    {
        header($k.':'.$v);
    }
    static function status($code)
    {
        header('HTTP/1.1 '.\Swoole\Response::$HTTP_HEADERS[$code]);
    }
    static function redirect($url, $mode=301)
    {
        header( "HTTP/1.1 ".\Swoole\Response::$HTTP_HEADERS[$mode]);
        header("Location:".$url);
    }
    static function finish()
    {
        exit;
    }
}

class Http
{
    static function __callStatic($func, $params)
    {
        if(defined('SWOOLE_SERVER'))
        {
            return call_user_func_array("\\Swoole\\Http_PWS::{$func}", $params);
        }
        else
        {
            return call_user_func_array("\\Swoole\\Http_LAMP::{$func}", $params);
        }
    }
}

