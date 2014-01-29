<?php
namespace Swoole\Log;
/**
 * Created by JetBrains PhpStorm.
 * User: htf
 * Date: 13-7-17
 * Time: ����9:49
 * To change this template use File | Settings | File Templates.
 */

class EchoLog extends \Swoole\Log implements \Swoole\IFace\Log
{
    static $formart = "[Y-m-d H:i:s]";

    function __construct($display = true)
    {
        $this->display = $display;
    }
    function put($type, $msg)
    {
        if($this->display)
            echo date(self::$formart)."\t$type\t$msg\n";
    }
}