<?php
require LIBPATH.'/Swoole/Log.php';
if(LOGTYPE=='FileLog')
{
    $params = LOGPUT;
}
elseif(LOGTYPE=='DBLog')
{
    global $php;
    $params['db'] = $php->db;
    $params['table'] = LOGPUT;
}
elseif(LOGTYPE=='PHPLog')
{
    $params['logput'] = LOGPUT;
    $params['type'] = LOGPUT_TYPE;
}
$log = new Log($params,LOGTYPE);