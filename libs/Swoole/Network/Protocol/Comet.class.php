<?php
class Comet extends HttpServer implements Swoole_TCP_Server_Protocol
{
    public $config;
    public $server;

    function log($msg)
    {
        //echo $msg;
    }
    function onStart()
    {
        $this->log("server running\n");
    }
    function onConnect($client_id)
    {
        $this->log("connect me\n");
    }
    /**
     * 接收到数据
     * @param $client_id
     * @param $data
     * @return unknown_type
     */
    function onRecive($client_id,$data)
    {
        $this->log($data);
        $request = $this->request($data);
        $response = new Response;

        $response->head['Date'] = gmdate("D, d M Y H:i:s T");
        $response->head['Server'] = 'Swoole';
//        $response->head['KeepAlive'] = 'off';
//        $response->head['Connection'] = 'close';
       // $response->head['Content-type'] = 'application/json';
        //$response->body = $request->get['callback'].'('.json_encode(array('successful'=>true)).')';
        //$response->head['Content-Length'] = strlen($response->body);

        $out = $response->head();

        //$out .= $response->body;
        $this->server->send($client_id,$out);
        for($i=0;$i<10;$i++)
        {
            $this->server->send($client_id,'<script language="javascript">alert("msg on");</script>');
            sleep(1);
        }

        //处理data的完整性
        $this->server->close($client_id);
    }

    function onClose($client_id)
    {

    }
    function onShutdown()
    {
        echo "server shutdown\n";
    }


}
