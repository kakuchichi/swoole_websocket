<?php
namespace Swoole;
class Console
{
    static function getOpt($cmd)
    {
        $cmd = trim($cmd);
        $args = explode(' ',$cmd);
        foreach($args as &$arg)
        {
            $arg = trim($arg);
            if(empty($arg)) unset($arg);
            if($arg{0}==='\\' or $arg{0}==='-')  $return['opt'][] = substr($arg,1);
            else $return['args'][] = $arg;
        }
        return $return;
    }
}
?>