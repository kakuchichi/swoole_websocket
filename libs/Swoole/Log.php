<?php
namespace Swoole;

class Log
{
    function info($msg)
    {
    	$this->put('INFO', $msg);
    }
    function error($msg)
    {
    	$this->put('ERROR', $msg);
    }
    function warn($msg)
    {
        $this->put('WARNNING', $msg);
    }
}