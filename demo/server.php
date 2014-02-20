<?php
define('DEBUG', 'on');
define("WEBPATH", str_replace("\\","/", __DIR__));
require __DIR__ . '/../libs/lib_config.php';

class WebSocket extends Swoole\Network\Protocol\WebSocket
{
    /**
     * 下线时，通知所有人
     */
    function onClose($serv, $client_id, $from_id)
    {
		$this->listOnline($client_id);//刷新在线人数
        parent::onClose($serv, $client_id, $from_id);
    }

    /**
     * 接收到消息时
     * @see WSProtocol::onMessage()
     */
    function onMessage($client_id, $ws)
    {
        $this->log("onMessage: ".$client_id.' = '.$ws['message']);
        $this->send($client_id, "Server: ".$ws['message']." client_id:".$client_id);
		
		$par=json_decode($ws['message'],true);
		if($par['mode']=="login"){//登陆
			$re['client_id']=$client_id;
			$re['mode']="login_sucess";
			$this->send($client_id,json_encode($re));
			$this->listOnline();
		}
		else if($par['mode']=="gp_say"){//群聊
			$re=array(
				'mode'=>'gp_say',
				'msg'=>nl2br($par['msg']),
				'from_id'=>$client_id,
			);
			$this->gp_say(json_encode($re));
		}
		else if($par['mode']=="upload"){//未完待续
			var_dump($par);
		}
    }

    function gp_say($msg)
    {
        foreach ($this->connections as $clid => $info)
        {
            $this->send($clid, $msg);
        }
    }

    function broadcast($client_id, $msg)
    {
        foreach ($this->connections as $clid => $info)
        {
            if ($client_id != $clid)
            {
                $this->send($clid, $msg);
            }
        }
    }
	
	function listOnline($client_id=null){
		$msg="Online<br/>";
		foreach($this->connections as $clid=>$info){
            if ($client_id != $clid){
				$msg.=" Client_id : ".$clid."<br/>";
			}
		}
		$re=array(
			'mode'=>'online_user_list',
			'msg'=>$msg,
		);
		foreach($this->connections as $clid=>$info){
			$this->send($clid,json_encode($re));
		}
	}
}

//require __DIR__'/phar://swoole.phar';
Swoole\Config::$debug = true;
Swoole\Error::$echo_html = false;

$AppSvr = new WebSocket();
//$AppSvr->loadSetting(__DIR__."/swoole.ini"); //加载配置文件
$AppSvr->setLogger(new \Swoole\Log\EchoLog(true)); //Logger

/**
 * 如果你没有安装swoole扩展，这里还可选择
 * BlockTCP 阻塞的TCP，支持windows平台
 * SelectTCP 使用select做事件循环，支持windows平台
 * EventTCP 使用libevent，需要安装libevent扩展
 */
$server = new \Swoole\Network\Server('157.7.141.215', 9503);
$server->setProtocol($AppSvr);
//$server->daemonize(); //作为守护进程
$server->run(array('worker_num' =>5, 'max_request' =>1000));
