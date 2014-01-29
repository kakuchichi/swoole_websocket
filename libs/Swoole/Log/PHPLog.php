<?php
namespace Swoole\Log;
/**
 * 使用PHP的error_log记录日志
 * @author Tianfeng.Han
 *
 */
class PHPLog extends \Swoole\Log implements \Swoole\IFace\Log
{
    private $logput;
    private $type;
    private $put_type = array('file'=>3,'sys'=>0,'email'=>1);
    static $date_format = 'Y-m-d H:i:s';

    function __construct($params)
    {
        if(isset($params['logput'])) $this->logput = $params['logput'];
        if(isset($params['type'])) $this->type = $this->put_type[$params['type']];
    }

    function put($type,$msg)
    {
        $msg = date(self::$date_format).' '.$type.' '.$msg.NL;
        error_log($msg,$this->type,$this->logput);
    }

}