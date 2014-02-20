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
    function onMessage($serv,$client_id, $ws)
    {
        $this->log("onMessage: ".$client_id.' = '.$ws['message']);
        $this->send($client_id, "Server: ".$ws['message']." client_id:".$client_id);
		$par=json_decode($ws['message'],true);
		$conn_list=$serv->connection_list(0,100);//获取从0~100号用户
		if($par['mode']=="login"){//登陆
			$re['client_id']=$client_id;
			$re['mode']="login_sucess";
			$this->send($client_id,json_encode($re));
			$this->listOnline($serv);
		}
		else if($par['mode']=="gp_say"){//群聊
			$re=array(
				'mode'=>'gp_say',
				'msg'=>nl2br($par['msg']),
				'from_id'=>$client_id,
			);
			$this->gp_say($conn_list,json_encode($re));
		}
		else if($par['mode']=="upload"){//未完待续
			var_dump($par);
		}
    }

    function gp_say($conn_list,$msg)
    {
		foreach($conn_list as $fd){
			$this->send($fd, $msg);
		}
    }

    function broadcast($conn_list,$client_id, $msg)
    {
		foreach($conn_list as $fd){
			if ($client_id != $fd)
			{
				$this->send($fd, $msg);
			}
		}
    }
	
	function listOnline($serv,$client_id=null){
		$msg="Online<br/>";
		$conn_list=$serv->connection_list(0,100);
		foreach($conn_list as $fd){
			if ($client_id != $fd){
				$msg.=" Client_id : ".$fd."<br/>";
			}
		}
		$re=array(
			'mode'=>'online_user_list',
			'msg'=>$msg,
		);
		foreach($conn_list as $fd){
			$this->send($fd,json_encode($re));
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
$server->daemonize(); //作为守护进程
$server->run(array('worker_num' =>5, 'max_request' =>1000));
