<?php
namespace Swoole;

abstract class Server implements Server\Driver
{
    /**
     * @var Server\Protocol
     */
    public $protocol;
	public $host = '0.0.0.0';
	public $port;
	public $timeout;

	public $buffer_size = 8192;
	public $write_buffer_size = 2097152;
	public $server_block = 0; //0 block,1 noblock
	public $client_block = 0; //0 block,1 noblock
	//最大连接数
	public $max_connect=1000;
	//客户端socket列表
	public $client_sock;

	function __construct($host,$port,$timeout=30)
	{
		$this->host = $host;
		$this->port = $port;
		$this->timeout = $timeout;
	}
	/**
	 * 应用协议
	 * @return unknown_type
	 */
	function setProtocol($protocol)
	{
        //初始化事件系统
        if(!($protocol instanceof \Swoole\Server\Protocol))
        {
             throw new \Exception("The protocol is not instanceof \\Swoole\\Server\\Protocol");
        }
		$this->protocol = $protocol;
		$this->protocol->server = $this;
	}

	function accept()
	{
		$client_socket = stream_socket_accept($this->server_sock, 0);
        //惊群
        if($client_socket === false)
        {
            return false;
        }
		$client_socket_id = (int)$client_socket;
		stream_set_blocking($client_socket, $this->client_block);
		$this->client_sock[$client_socket_id] = $client_socket;
		$this->client_num++;
		if($this->client_num > $this->max_connect)
		{
			sw_socket_close($client_socket);
			return false;
		}
		else
		{
			//设置写缓冲区
			stream_set_write_buffer($client_socket, $this->write_buffer_size);
			return $client_socket_id;
		}
	}

    function spawn($setting)
    {
        if(!extension_loaded('pcntl'))
        {
            trigger_error(__METHOD__." require pcntl extension!");
            return;
        }
        $num = 0;
        if(isset($setting['worker_num']))
        {
            $num = (int) $setting['worker_num'] - 1;
        }
        if($num < 2)
        {
            return;
        }
        $pids = array();
        for($i=0; $i<$num; $i++)
        {
            $pid = pcntl_fork();
            if($pid > 0)
            {
                $pids[] = $pid;
            }
            else
            {
                break;
            }
        }
        return $pids;
    }

    function startWorker()
    {

    }

    abstract function run($setting);
    abstract function send($client_id, $data);
    abstract function close($client_id);
    abstract function shutdown();

    function daemonize()
    {

    }

	function onError($errno,$errstr)
	{
		exit("$errstr ($errno)");
	}
	/**
	 * 创建一个Stream Server Socket
	 * @param $uri
	 * @return unknown_type
	 */
	function create($uri,$block=0)
	{
		//UDP
		if($uri{0}=='u') $socket = stream_socket_server($uri,$errno,$errstr,STREAM_SERVER_BIND);
		//TCP
		else $socket = stream_socket_server($uri,$errno,$errstr);

		if(!$socket) $this->onError($errno,$errstr);
		//设置socket为非堵塞或者阻塞
		stream_set_blocking($socket, $block);
		return $socket;
	}
	function create_socket($uri, $block=false)
	{
		$set = parse_url($uri);
		if($uri{0}=='u') $sock = socket_create(AF_INET, SOCK_DGRAM , SOL_UDP);
		else $sock = socket_create(AF_INET, SOCK_STREAM , SOL_TCP);

		if($block) socket_set_block($sock);
		else socket_set_nonblock($sock);
		socket_bind($sock,$set['host'],$set['port']);
		socket_listen($sock);
		return $sock;
	}
	function sendData($sock, $data)
	{
		return Network\Stream::write($sock, $data);
	}
	function log($log)
	{
		echo $log,NL;
	}
}
function sw_run($cmd)
{
	if(PHP_OS=='WINNT') pclose(popen("start /B ".$cmd,"r"));
	else exec($cmd." > /dev/null &");
}
function sw_gc_array($array)
{
	$new = array();
	foreach($array as $k=>$v)
	{
		$new[$k] = $v;
		unset($array[$k]);
	}
	unset($array);
	return $new;
}
/**
 * 关闭socket
 * @param $socket
 * @param $event
 * @return unknown_type
 */
function sw_socket_close($socket,$event=null)
{
	if($event)
	{
		event_del($event);
		event_free($event);
	}
	stream_socket_shutdown($socket,STREAM_SHUT_RDWR);
	fclose($socket);
}

interface TCP_Server_Driver
{
	function run($num=1);
	function send($client_id,$data);
	function close($client_id);
	function shutdown();
	function setProtocol($protocol);
}

interface UDP_Server_Driver
{
	function run($num=1);
	function shutdown();
	function setProtocol($protocol);
}

interface TCP_Server_Protocol
{
	function onStart();
	function onConnect($client_id);
	function onReceive($client_id, $data);
	function onClose($client_id);
	function onShutdown($server);
}

interface UDP_Server_Protocol
{
	function onStart();
	function onData($peer,$data);
	function onShutdown();
}
