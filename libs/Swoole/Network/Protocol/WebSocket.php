<?php
namespace Swoole\Network\Protocol;
use Swoole;

abstract class WebSocket extends HttpServer
{
    const OPCODE_CONTINUATION_FRAME = 0x0;
    const OPCODE_TEXT_FRAME         = 0x1;
    const OPCODE_BINARY_FRAME       = 0x2;
    const OPCODE_CONNECTION_CLOSE   = 0x8;
    const OPCODE_PING               = 0x9;
    const OPCODE_PONG               = 0xa;

    const CLOSE_NORMAL              = 1000;
    const CLOSE_GOING_AWAY          = 1001;
    const CLOSE_PROTOCOL_ERROR      = 1002;
    const CLOSE_DATA_ERROR          = 1003;
    const CLOSE_STATUS_ERROR        = 1005;
    const CLOSE_ABNORMAL            = 1006;
    const CLOSE_MESSAGE_ERROR       = 1007;
    const CLOSE_POLICY_ERROR        = 1008;
    const CLOSE_MESSAGE_TOO_BIG     = 1009;
    const CLOSE_EXTENSION_MISSING   = 1010;
    const CLOSE_SERVER_ERROR        = 1011;
    const CLOSE_TLS                 = 1015;

    const WEBSOCKET_VERSION         = 13;
    /**
     * GUID.
     *
     * @const string
     */
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public $ws_list = array();
    public $connections = array();
    public $max_connect = 10000;
    public $heart_time = 600; //600s life time
    /**
     * Do the handshake.
     *
     * @param   Swoole\Request $request
     * @param   Swoole\Response $response
     * @throws   \Exception
     * @return  bool
     */
    public function doHandshake(Swoole\Request $request,  Swoole\Response $response)
    {
        if (!isset($request->head['Sec-WebSocket-Key']))
        {
            $this->log('Bad protocol implementation: it is not RFC6455.');
            return false;
        }
        $key = $request->head['Sec-WebSocket-Key'];
        if (0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $key) || 16 !== strlen(base64_decode($key)))
        {
            $this->log('Header Sec-WebSocket-Key: $key is illegal.');
            return false;
        }
        /**
         * @TODO
         *   ? Origin;
         *   ? Sec-WebSocket-Protocol;
         *   ? Sec-WebSocket-Extensions.
         */
        $response->send_http_status(101);
        $response->addHeader(array(
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => base64_encode(sha1($key . static::GUID, true)),
            'Sec-WebSocket-Version' => self::WEBSOCKET_VERSION,
        ));
        return true;
    }

    /**
     * clean all connection
     */
    function cleanConnection()
    {
        $now = time();
        foreach($this->connections as $client_id => $conn)
        {
            if($conn['time'] < $now - $this->heart_time)
            {
                $this->close($client_id);
            }
        }
        $this->log('clean connections');
    }
    abstract function onMessage($client_id, $message);

    /**
     * 握手建立连接
     */
    function createConnection($client_id, $data)
    {
        $st = $this->checkData($client_id, $data);
        if ($st === self::ST_ERROR)
        {
            $this->server->close($client_id);
            return false;
        }
        $request = $this->requests[$client_id];
        $response = new Swoole\Response;
        $this->doHandshake($request, $response);
        $this->response($client_id, $request, $response);

        $conn = array('header' => $request->head, 'time' => time(), 'buffer' => '');
        $this->connections[$client_id] = $conn;

        if(count($this->connections) > $this->max_connect)
        {
            $this->cleanConnection();
        }
    }
    /**
     * Read a frame.
     *
     * @access  public
     * @throw   \Exception
     */
    public function onReceive($server, $client_id, $from_id, $data)
    {
        //未连接
        if(!isset($this->connections[$client_id]))
        {
            $this->createConnection($client_id, $data);
            return;
        }
        //已连接
        do
        {
            if(empty($this->ws_list[$client_id]))
            {
                $ws = $this->parseFrame($data);
                //解析失败了
                if($ws === false)
                {
                    $this->close($client_id, self::CLOSE_PROTOCOL_ERROR);
                }
                //数据包就绪
                if($ws['finish'])
                {
                    $this->opcodeSwitch($client_id, $ws);
                    //还有数据
                    if(strlen($data) > 0)
                    {
                        continue;
                    }
                    //数据已处理完
                    unset($this->ws_list[$client_id]);
                }
                //未就绪，先加入到ws_list中
                else
                {
                    $this->ws_list[$client_id] = $ws;
                }
            }
            else
            {
                $ws = $this->ws_list[$client_id];
                $ws['buffer'] .= $data;
                $message_len =  strlen($ws['buffer']);
                if($ws['length'] == $message_len)
                {
                    //需要使用MaskN来解析
                    $ws['message'] = $this->parseMessage($ws);
                    $this->opcodeSwitch($client_id, $ws);
                    unset($this->ws_list[$client_id]);
                }
                //数据过多，可能带有另外一帧的数据
                else if($ws['length'] < $message_len)
                {
                    //这一帧的数据已完结
                    $ws['message'] = substr($ws['message'], 0, $ws['length']);
                    //$data是下一帧的数据了
                    $data = substr($ws['message'], $ws['length']);
                    $this->opcodeSwitch($client_id, $ws);
                    unset($this->ws_list[$client_id]);
                    //继续解析帧
                    continue;
                }
                //等待数据
            }
            break;
        } while(true);
    }
    function parseFrame(&$data)
    {
        //websocket
        $ws  = array();
        $data_offset = 0;
        $data_length = strlen($data);

        //fin:1 rsv1:1 rsv2:1 rsv3:1 opcode:4
        $handle        = ord($data[$data_offset]);
        $ws['fin']    = ($handle >> 7) & 0x1;
        $ws['rsv1']   = ($handle >> 6) & 0x1;
        $ws['rsv2']   = ($handle >> 5) & 0x1;
        $ws['rsv3']   = ($handle >> 4) & 0x1;
        $ws['opcode'] =  $handle       & 0xf;
        $data_offset++;

        //mask:1 length:7
        $handle        = ord($data[$data_offset]);
        $ws['mask']   = ($handle >> 7) & 0x1;
        //0-125
        $ws['length'] =  $handle       & 0x7f;
        $length        = &$ws['length'];
        $data_offset++;

        if(0x0 !== $ws['rsv1'] || 0x0 !== $ws['rsv2'] || 0x0 !== $ws['rsv3'])
        {
            $this->close(self::CLOSE_PROTOCOL_ERROR);
            return false;
        }
        if(0 === $length)
        {
            $ws['message'] = '';
            return $ws;
        }
        //126 short
        elseif(0x7e === $length)
        {
            //2
            $handle = unpack('nl', substr($data, $data_offset, 2));
            $data_offset += 2;
            $length = $handle['l'];
        }
        //127 int64
        elseif(0x7f === $length)
        {
            //8
            $handle = unpack('N*l', substr($data, $data_offset, 8));
            $data_offset += 8;
            $length = $handle['l2'];
            if($length > 0x7fffffffffffffff)
            {
                $this->log('Message is too long.');
                return false;
            }
        }

        if(0x0 !== $ws['mask'])
        {
            //int32
            $ws['mask'] = array_map('ord', str_split(substr($data, $data_offset, 4)));
            $data_offset += 4;
        }

        $frame_length = $data_offset + $length;
        //设置buffer区
        $ws['buffer'] = substr($data, $data_offset, $length);
        //帧长度等于$data长度，说明这份数据是单独的一帧
        if ($frame_length == $data_length)
        {
            $data = "";
        }
        //帧长度小于数据长度，可能还有下一帧
        else if($frame_length < $data_length)
        {
            $data = substr($data, $frame_length);
        }
        //需要继续等待数据
        else
        {
            $ws['finish'] = false;
            $data = "";
            return $ws;
        }
        $ws['finish'] = true;
        $ws['message'] = $this->parseMessage($ws);
        return $ws;
    }

    protected function parseMessage(&$ws)
    {
        $buffer = $ws['buffer'];
        //没有mask
        if(0x0 !== $ws['mask'])
        {
            $maskC = 0;
            for($j = 0, $_length = $ws['length']; $j < $_length; ++$j)
            {
                $buffer[$j] = chr(ord($buffer[$j]) ^ $ws['mask'][$maskC]);
                $maskC       = ($maskC + 1) % 4;
            }
            $ws['message'] = $buffer;
        }
        return $buffer;
    }
    /**
     * Write a frame.
     *
     * @access  public
     * @param   string  $message    Message.
     * @param   int     $opcode     Opcode.
     * @param   bool    $end        Whether it is the last frame of the message.
     * @return  int
     */
    public function newFrame ($message,  $opcode = self::OPCODE_TEXT_FRAME, $end = true )
    {
        $fin    = true === $end ? 0x1 : 0x0;
        $rsv1   = 0x0;
        $rsv2   = 0x0;
        $rsv3   = 0x0;
        $mask   = 0x1;
        $length = strlen($message);
        $out    = chr(
            ($fin  << 7)
            | ($rsv1 << 6)
            | ($rsv2 << 5)
            | ($rsv3 << 4)
            | $opcode
        );

        if(0xffff < $length)
            $out .= chr(0x7f) . pack('NN', 0, $length);
        elseif(0x7d < $length)
            $out .= chr(0x7e) . pack('n', $length);
        else
            $out .= chr($length);

        $out .= $message;
        return $out;
    }

    /**
     * Send a message.
     *
     * @access  public
     * @param   string  $message    Message.
     * @param   int     $opcode     Opcode.
     * @param   bool    $end        Whether it is the last frame of the message.
     * @return  void
     */
    public function send($client_id, $message, $opcode = self::OPCODE_TEXT_FRAME, $end = true)
    {
        if((self::OPCODE_TEXT_FRAME  === $opcode or self::OPCODE_CONTINUATION_FRAME === $opcode) and false === (bool) preg_match('//u', $message))
        {
            $this->log('Message [%s] is not in UTF-8, cannot send it.', 2, 32 > strlen($message) ? substr($message, 0, 32) . ' ' : $message);
        }
        else
        {
            $out = $this->newFrame($message, $opcode, $end);
            return $this->server->send($client_id, $out);
        }
    }
    function opcodeSwitch($client_id, $ws)
    {
        switch($ws['opcode'])
        {
            case self::OPCODE_BINARY_FRAME:
            case self::OPCODE_TEXT_FRAME:
                //if(0x1 === $ws['fin'])
                {
                    $this->onMessage($client_id, $ws);
                }
//                else
//                {
//                    $this->ws_list[$client_id] = &$ws;
//                }
                break;
            case self::OPCODE_PING:
                $message = &$ws['message'];
                if(0x0  === $ws['fin'] or 0x7d  <  $ws['length'])
                {
                    $this->close($client_id, self::CLOSE_PROTOCOL_ERROR);
                    break;
                }
                $this->connections[$client_id]['time'] = time();
                $this->send($client_id, $message, self::OPCODE_PONG, true);
                break;
            case self::OPCODE_PONG:
                if(0 === $ws['fin'])
                {
                    $this->close($client_id, self::CLOSE_PROTOCOL_ERROR);
                }
                break;
            case self::OPCODE_CONNECTION_CLOSE:
                $length = &$frame['length'];
                if(1    === $length || 0x7d < $length)
                {
                    $this->close($client_id, self::CLOSE_PROTOCOL_ERROR);
                    break;
                }
                $code   = self::CLOSE_NORMAL;
                $reason = null;
                if(0 < $length)
                {
                    $message = &$frame['message'];
                    $_code   = unpack('nc', substr($message, 0, 2));
                    $code    = &$_code['c'];

                    if(1000 > $code || (1004 <= $code && $code <= 1006) || (1012 <= $code && $code <= 1016) || 5000  <= $code)
                    {
                        $this->close($client_id, self::CLOSE_PROTOCOL_ERROR);
                        break;
                    }

                    if(2 < $length)
                    {
                        $reason = substr($message, 2);
                        if(false === (bool) preg_match('//u', $reason)) {
                            $this->close($client_id, self::CLOSE_MESSAGE_ERROR);

                            break;
                        }
                    }
                }
                $this->close($client_id, self::CLOSE_NORMAL);
                break;
            default:
                $this->close($client_id, self::CLOSE_PROTOCOL_ERROR);
        }
    }
    function onConnect($serv, $client_id, $from_id)
    {
        $this->log("connected client_id = $client_id");
    }
    function onClose($serv, $client_id, $from_id)
    {
        $this->log("close client_id = $client_id");
        unset($this->ws_list[$client_id], $this->connections[$client_id], $this->requests[$client_id]);
    }
    /**
     * Close a connection.
     *
     * @access  public
     * @param   int     $code
     * @param   string  $reason    Reason.
     * @return  void
     */
    public function close($client_id, $code = self::CLOSE_NORMAL, $reason = '')
    {
        $this->send($client_id, pack('n', $code).$reason, self::OPCODE_CONNECTION_CLOSE);
        $this->server->close($client_id);
    }
}

