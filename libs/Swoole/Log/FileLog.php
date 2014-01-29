<?php
namespace Swoole\Log;
/**
 * 文件日志类
 * @author Tianfeng.Han
 *
 */
class FileLog extends \Swoole\Log implements \Swoole\IFace\Log
{
    private $log_file;
    static $date_format = 'Y-m-d H:i:s';

	function __construct($log_file)
    {
    	$this->log_file = $log_file;
    }
	/**
	 * 写入日志
	 * @param $type 事件类型
	 * @param $msg  信息
	 * @return bool
	 */
    function put($type,$msg)
    {
    	$msg = date(self::$date_format).' '.$type.' '.$msg.NL;
    	return file_put_contents($this->log_file, $msg, FILE_APPEND);
    }
}
