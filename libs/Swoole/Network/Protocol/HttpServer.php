<?php
namespace Swoole\Network\Protocol;

use Swoole;

/**
 * HTTP Server
 * @author Tianfeng.Han
 * @link http://www.swoole.com/
 * @package Swoole
 * @subpackage net.protocol
 */
class HttpServer extends Swoole\Network\Protocol implements Swoole\Server\Protocol
{
    public $config = array();

    public $keepalive = false;
    public $gzip = false;
    public $expire = false;

    /**
     * @var \Swoole\Http\Parser
     */
    protected $parser;

    protected $mime_types;
    protected $static_dir;
    protected $static_ext;
    protected $dynamic_ext;
    protected $document_root;
    protected $deny_dir;

    public $requests = array(); //保存请求信息,里面全部是Request对象
    protected $buffer_maxlen = 65535; //最大POST尺寸，超过将写文件

    const SOFTWARE = "Swoole";
    const DATE_FORMAT_HTTP = 'D, d-M-Y H:i:s T';

    const HTTP_EOF = "\r\n\r\n";
    const HTTP_HEAD_MAXLEN = 2048; //http头最大长度不得超过2k

    const ST_FINISH = 1; //完成，进入处理流程
    const ST_WAIT   = 2; //等待数据
    const ST_ERROR  = 3; //错误，丢弃此包

    function __construct($config = array())
    {
        define('SWOOLE_SERVER', true);
        $mimes = require(LIBPATH . '/data/mimes.php');
        $this->mime_types = array_flip($mimes);
        $this->config = $config;
        Swoole\Error::$echo_html = true;
        $this->parser = new Swoole\Http\Parser;
    }

    function onStart($serv)
    {
        if (!defined('WEBROOT'))
        {
            define('WEBROOT', $this->config['server']['webroot']);
        }
        if (isset($this->config['server']['user']))
        {
            $user = posix_getpwnam($this->config['server']['user']);
            if($user)
            {
                posix_setuid($user['uid']);
                posix_setgid($user['gid']);
            }
        }
        $this->log(self::SOFTWARE . ". running. on {$this->server->host}:{$this->server->port}");
    }

    function onShutdown($serv)
    {
        $this->log(self::SOFTWARE . " shutdown");
    }

    function onConnect($serv, $client_id, $from_id)
    {
        $this->log("client[#$client_id@$from_id] connect");
    }

    function setDocumentRoot($path)
    {
        $this->document_root = $path;
    }

    function onClose($serv, $client_id, $from_id)
    {
        $this->log("client[#$client_id@$from_id] close");
        unset($this->requests[$client_id]);
    }

    function loadSetting($ini_file)
    {
        if (!is_file($ini_file)) exit("Swoole AppServer配置文件错误($ini_file)\n");
        $config = parse_ini_file($ini_file, true);
        /*--------------Server------------------*/
        if (empty($config['server']['webroot']))
        {
            $config['server']['webroot'] = 'http://' . $this->server->host . ':' . $this->server->port;
        }
        //开启http keepalive
        if (!empty($config['server']['keepalive']))
        {
            $this->keepalive = true;
        }
        //是否压缩
        if (!empty($config['server']['gzip_open']) and function_exists('gzdeflate'))
        {
            $this->gzip = true;
        }
        //过期控制
        if (!empty($config['server']['expire_open']))
        {
            $this->expire = true;
        }
        /*--------------Session------------------*/
        if (empty($config['session']['cookie_life'])) $config['session']['cookie_life'] = 86400; //保存SESSION_ID的cookie存活时间
        if (empty($config['session']['session_life'])) $config['session']['session_life'] = 1800; //Session在Cache中的存活时间
        if (empty($config['session']['cache_url'])) $config['session']['cache_url'] = 'file://localhost#sess'; //Session在Cache中的存活时间
        /*--------------Apps------------------*/
        if (empty($config['apps']['url_route'])) $config['apps']['url_route'] = 'url_route_default';
        if (empty($config['apps']['auto_reload'])) $config['apps']['auto_reload'] = 0;
        if (empty($config['apps']['charset'])) $config['apps']['charset'] = 'utf-8';
        /*--------------Access------------------*/
        $this->deny_dir = array_flip(explode(',', $config['access']['deny_dir']));
        $this->static_dir = array_flip(explode(',', $config['access']['static_dir']));
        $this->static_ext = array_flip(explode(',', $config['access']['static_ext']));
        $this->dynamic_ext = array_flip(explode(',', $config['access']['dynamic_ext']));
        /*--------------document_root------------*/
        if (empty($this->document_root) and !empty($config['server']['document_root']))
        {
            $this->document_root = $config['server']['document_root'];
        }
        /*-----merge----*/
        if (!is_array($this->config))
        {
            $this->config = array();
        }
        $this->config = array_merge($this->config, $config);
    }

    function checkHeader($client_id, $http_data)
    {
        //新的连接
        if (!isset($this->requests[$client_id]))
        {
            //HTTP结束符
            $ret = strpos($http_data, self::HTTP_EOF);
            //没有找到EOF
            if($ret === false)
            {
                return false;
            }
            else
            {
                $request = new Swoole\Request;
                //GET没有body
                list($header, $request->body) = explode(self::HTTP_EOF, $http_data, 2);
                $request->head = $this->parser->parseHeader($header);
                //使用head[0]保存额外的信息
                $request->meta = $request->head[0];
                unset($request->head[0]);
                //保存请求
                $this->requests[$client_id] = $request;
                //解析失败
                if($request->head == false)
                {
                    $this->log("parseHeader fail. header=".$header);
                    return false;
                }
            }
        }
        //POST请求需要合并数据
        else
        {
            $request = $this->requests[$client_id];
            $request->body .= $http_data;
        }
        return $request;
    }

    function checkPost($request)
    {
        if(isset($request->head['Content-Length']))
        {
            //超过最大尺寸
            if(intval($request->head['Content-Length']) > $this->config['access']['post_maxsize'])
            {
                return self::ST_ERROR;
            }
            //不完整，继续等待数据
            if(intval($request->head['Content-Length']) > strlen($request->body))
            {
                return self::ST_WAIT;
            }
            //长度正确
            else
            {
                return self::ST_FINISH;
            }
        }
        //POST请求没有Content-Length，丢弃此请求
        return self::ST_ERROR;
    }

    function checkData($client_id, $http_data)
    {
        //检测头
        $request = $this->checkHeader($client_id, $http_data);
        //错误的http头
        if($request === false)
        {
            return self::ST_ERROR;
        }
        //POST请求需要检测body是否完整
        if($request->meta['method'] == 'POST')
        {
            return $this->checkPost($request);
        }
        //GET请求直接进入处理流程
        else
        {
            return self::ST_FINISH;
        }
    }

    /**
     * 接收到数据
     * @param $client_id
     * @param $data
     * @return unknown_type
     */
    function onReceive($serv, $client_id, $from_id, $data)
    {
        //检测request data完整性
        $ret = $this->checkData($client_id, $data);
        switch($ret)
        {
            //错误的请求
            case self::ST_ERROR;
                $this->server->close($client_id);
                return true;
            //请求不完整，继续等待
            case self::ST_WAIT:
                return true;
            default:
                break;
        }
        //完整的请求
        //开始处理
        $request = $this->requests[$client_id];
        $this->parseRequest($request);
        //处理请求，产生response对象
        $response = $this->onRequest($request);
        //发送response
        $this->response($client_id, $request, $response);
        if(!$this->keepalive or $response->head['Connection'] == 'close')
        {
            $this->server->close($client_id);
        }
        $request->unsetGlobal();
        //清空request缓存区
        unset($this->requests[$client_id]);
        unset($request);
        unset($response);
    }

    /**
     * 解析请求
     * @param $request Swoole\Request
     * @return unknown_type
     */
    function parseRequest($request)
    {
        $url_info = parse_url($request->meta['uri']);
        $request->meta['request_time'] = time();
        $request->meta['path'] = $url_info['path'];
        if (isset($url_info['fragment'])) $request->meta['fragment'] = $url_info['fragment'];
        if (isset($url_info['query']))
        {
            parse_str($url_info['query'], $request->get);
        }
        //POST请求,有http body
        if ($request->meta['method'] === 'POST')
        {
            $this->parser->parseBody($request);
        }
        //解析Cookies
        if (!empty($request->head['Cookie']))
        {
            $this->parser->parseCookie($request);
        }
    }

    /**
     * 发送响应
     * @param $client_id
     * @param $response
     * @return unknown_type
     */
    function response($client_id, Swoole\Request $request, Swoole\Response $response)
    {
        if (!isset($response->head['Date']))
        {
            $response->head['Date'] = gmdate("D, d M Y H:i:s T");
        }
        if (!isset($response->head['Server']))
        {
            $response->head['Server'] = self::SOFTWARE;
        }
        if(!isset($response->head['Connection']))
        {
            //keepalive
            if($this->keepalive and (isset($request->head['Connection']) and $request->head['Connection'] == 'keep-alive'))
            {
                $response->head['KeepAlive'] = 'on';
                $response->head['Connection'] = 'keep-alive';
            }
            else
            {
                $response->head['KeepAlive'] = 'off';
                $response->head['Connection'] = 'close';
            }
        }
        //过期命中
        if ($this->expire and $response->http_status == 304)
        {
            $out = $response->head();
            $this->server->send($client_id, $out);
            return;
        }
        //压缩
        if ($this->gzip)
        {
            $response->head['Content-Encoding'] = 'deflate';
            $response->body = gzdeflate($response->body, $this->config['server']['gzip_level']);
        }
        $response->head['Content-Length'] = strlen($response->body);
        $out = $response->head();
        $out .= $response->body;
        $this->server->send($client_id, $out);
    }

    function http_error($code, Swoole\Response $response, $content = '')
    {
        $response->send_http_status($code);
        $response->head['Content-Type'] = 'text/html';
        $response->body = Swoole\Error::info(Swoole\Response::$HTTP_HEADERS[$code], "<p>$content</p><hr><address>" . self::SOFTWARE . " at {$this->server->host} Port {$this->server->port}</address>");
    }

    /**
     * 错误请求
     * @param $request
     */
    function onError($request)
    {

    }
    /**
     * 处理请求
     * @param $request
     * @return Swoole\Response
     */
    function onRequest(Swoole\Request $request)
    {
        $response = new Swoole\Response;
        //请求路径
        if ($request->meta['path'][strlen($request->meta['path']) - 1] == '/')
        {
            $request->meta['path'] .= $this->config['request']['default_page'];
        }
        if($this->doStaticRequest($request, $response))
        {
             //pass
        }
        /* 动态脚本 */
        elseif (isset($this->dynamic_ext[$request->ext_name]) or empty($ext_name))
        {
            $this->process_dynamic($request, $response);
        }
        else
        {
            $this->http_error(404, $response, "Http Not Found({($request->meta['path']})");
        }
        return $response;
    }

    /**
     * 过滤请求，阻止静止访问的目录，处理静态文件
     */
    function doStaticRequest($request, $response)
    {
        $path = explode('/', trim($request->meta['path'], '/'));
        //扩展名
        $request->ext_name = $ext_name = \Upload::file_ext($request->meta['path']);
        /* 检测是否拒绝访问 */
        if (isset($this->deny_dir[$path[0]]))
        {
            $this->http_error(403, $response, "服务器拒绝了您的访问({$request->meta['path']})");
            return true;
        }
        /* 是否静态目录 */
        elseif (isset($this->static_dir[$path[0]]) or isset($this->static_ext[$ext_name]))
        {
            return $this->process_static($request, $response);
        }
        return false;
    }

    /**
     * 静态请求
     * @param $request
     * @param $response
     * @return unknown_type
     */
    function process_static($request, Swoole\Response $response)
    {
        $path = $this->document_root . '/' . $request->meta['path'];
        if (is_file($path))
        {
            $read_file = true;
            if($this->expire)
            {
                $expire = intval($this->config['server']['expire_time']);
                $fstat = stat($path);
                //过期控制信息
                if (isset($request->head['If-Modified-Since']))
                {
                    $lastModifiedSince = strtotime($request->head['If-Modified-Since']);
                    if ($lastModifiedSince and $fstat['mtime'] <= $lastModifiedSince)
                    {
                        //不需要读文件了
                        $read_file = false;
                        $response->send_http_status(304);
                    }
                }
                else
                {
                    $response->head['Cache-Control'] = "max-age={$expire}";
                    $response->head['Pragma'] = "max-age={$expire}";
                    $response->head['Last-Modified'] = date(self::DATE_FORMAT_HTTP, $fstat['mtime']);
                    $response->head['Expires'] = "max-age={$expire}";
                }
            }
            $ext_name = \Upload::file_ext($request->meta['path']);
            if($read_file)
            {
                $response->head['Content-Type'] = $this->mime_types[$ext_name];
                $response->body = file_get_contents($path);
            }
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 动态请求
     * @param $request
     * @param $response
     * @return unknown_type
     */
    function process_dynamic($request, $response)
    {
        $path = $this->document_root . '/' . $request->meta['path'];
        if (is_file($path))
        {
            $request->setGlobal();
            $response->head['Content-Type'] = 'text/html';
            ob_start();
            try
            {
                include $path;
            }
            catch (\Exception $e)
            {
                $response->send_http_status(404);
                $response->body = $e->getMessage() . '!<br /><h1>' . self::SOFTWARE . '</h1>';
            }
            $response->body = ob_get_contents();
            ob_end_clean();
        }
        else
        {
            $this->http_error(404, $response, "页面不存在({$request->meta['path']})！");
        }
    }
}
