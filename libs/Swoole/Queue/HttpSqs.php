<?php
class HttpQueue implements IQueue
{
    public $host = 'localhost';
    public $debug = false;
    public $port = 1218;
    public $client_type;
    public $http;
    public $name = 'swoole';
    public $charset = 'utf-8';

    private $base;

    function __construct($config)
    {
        if(!empty($config['server_url'])) $this->server_url = $config['server_url'];
        if(!empty($config['name'])) $this->name = $config['name'];
        if(!empty($config['charset'])) $this->charset = $config['charset'];
        if(!empty($config['debug'])) $this->debug = $config['debug'];

        $this->base = "{$this->server_url}/?charset={$this->charset}&name={$this->name}";

        if(!extension_loaded('curl'))
        {
            import('http.CURL');
            $header[] = "Connection: keep-alive";
            $header[] = "Keep-Alive: 300";
            $this->client_type = 'curl';
            $this->http = new CURL($this->debug);
            $this->http->set_header($headers);
        }
        else
        {
            import('http.HttpClient');
            $this->client_type = 'HttpClient';
        }
    }
    function http_get($opt)
    {
        $url = $this->base.'&opt='.$opt;
        if($this->client_type=='curl') return $this->http->get($url);
        else return HttpClient::quickGet($url);
    }
    function http_post($opt,$data)
    {
        $url = $this->base.'&opt='.$opt;
        if($this->client_type=='curl') return $this->http->post($url,$data);
        else return HttpClient::quickPost($url,$data);
    }
    function push($data)
    {
        $result = $this->http_post("put",$data);
        if ($result == "HTTPSQS_PUT_OK") return true;
        else if($result== "HTTPSQS_PUT_END") return $result;
        else return false;
    }
    function pop()
    {
        $result = $this->http_get("get");
        if ($result == false || $result== "HTTPSQS_ERROR" || $result== false) return false;
        else
        {
            parse_str($result,$res);
            return $res;
        }
    }
    function status()
    {
        $result = $this->http_get("status");
        if ($result == false || $result == "HTTPSQS_ERROR" || $result== false) return false;
        else return $result;
    }
}