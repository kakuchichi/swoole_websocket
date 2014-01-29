<?php
namespace Swoole\Log;
/**
 * 数据库日志记录类
 * @author Tianfeng.Han
 */
class DBLog extends \Swoole\Log implements \Swoole\IFace\Log
{
    public $db;
    public $table;

    function __construct($params)
    {
        $this->table = $params['table'];
        $this->db = $params['db'];
    }
    function put($type, $msg)
    {
        $put['logtype'] = $type;
        $put['msg'] = $msg;
        return $this->db->insert($put,$this->table);
    }
    function create()
    {
        return $this->db->query("CREATE TABLE `{$this->table}` (
`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`addtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
`logtype` VARCHAR( 32 ) NOT NULL ,
`msg` VARCHAR( 255 ) NOT NULL
) ENGINE = Innodb");
    }
}
