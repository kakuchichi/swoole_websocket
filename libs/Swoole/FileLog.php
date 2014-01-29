<?php
class FileLog
{
    private $log_file;
    private $date_format;
    private $type;

	function __construct($log_file,$date_format='Y-m-d H:i:s',$type=3)
    {
    	$this->log_file = $log_file;
    	$this->date_format = $date_format;
    	$this->type = $type;
    }
	/**
	 * 写入日志
	 * @param $type 事件类型
	 * @param $msg  信息
	 * @return bool
	 */
    function put($type,$msg)
    {
    	$msg = date($this->date_format).' '.$type.' '.$msg.NL;
    	return error_log($msg,$this->type,$this->log_file);
    }

    function info($msg)
    {
    	$this->put('INFO',$msg);
    }

    function error($msg)
    {
    	$this->put('ERROR',$msg);
    }

    function warn($msg)
    {
        $this->put('WARNNING',$msg);
    }
}
?>