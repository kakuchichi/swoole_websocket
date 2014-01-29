<?php
namespace Swoole\IFace;

interface Log
{
    /**
     * 写入日志
     * @param $type 类型
     * @param $msg  内容
     * @return unknown_type
     */
    function put($type, $msg);
}