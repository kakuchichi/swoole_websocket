<?php
namespace Swoole\Network\Protocol;
use Swoole;

require_once LIBPATH.'/function/cli.php';
class AppServer extends HttpServer
{
    protected $router_function;
    protected $apps_path;

    function onStart($serv)
    {
        parent::onStart($serv);
        if(empty($this->apps_path))
        {
            if(!empty($this->config['apps']['apps_path']))
            {
                $this->apps_path = $this->config['apps']['apps_path'];
            }
            else
            {
                throw new \Exception("AppServer require apps_path");
            }
        }
        $php = Swoole::getInstance();
        $php->addHook(Swoole::HOOK_CLEAN, function(){
            $php = Swoole::getInstance();
            //模板初始化
            if(!empty($php->tpl))
            {
                $php->tpl->clear_all_assign();
            }
            //还原session
            $php->session->open = false;
            $php->session->readonly = false;
        });
    }
    function setAppPath($path)
    {
        $this->apps_path = $path;
    }

    function onRequest(Swoole\Request $request)
    {
        $response = new Swoole\Response();
        $php = Swoole::getInstance();
        $request->setGlobal();

//        if($this->doStaticRequest($request, $response))
//        {
//            return $response;
//        }
        //将对象赋值到控制器
        $php->request = $request;
        $php->response = $response;

        $response->head['Cache-Control'] = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0';
        $response->head['Pragma'] = 'no-cache';
        try
        {
            ob_start();
            /*---------------------处理MVC----------------------*/
            $response->body = $php->runMVC();
            $response->body .= ob_get_contents();
            ob_end_clean();
        }
        catch(\Exception $e)
        {
            if ($request->finish != 1) $this->http_error(404, $response, $e->getMessage());
        }
        if(!isset($response->head['Content-Type']))
        {
            $response->head['Content-Type'] = 'text/html; charset='.$this->config['apps']['charset'];
        }
        //重定向
        if(isset($response->head['Location']))
        {
            $response->send_http_status(301);
        }
        return $response;
    }
}