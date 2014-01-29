<?php
namespace Swoole\Database;
use Swoole;
/**
 * MySQL数据库封装类
 * @package SwooleExtend
 * @author Tianfeng.Han
 *
 */
class MySQL implements \Swoole\IDatabase
{
	public $debug = false;
	public $conn = null;
	public $config;
	const DEFAULT_PORT = 3306;

	function __construct($db_config)
	{
		if(empty($db_config['port']))
		{
			$db_config['port'] = self::DEFAULT_PORT;
		}
		$this->config = $db_config;
	}
	/**
	 * 连接数据库
	 * @see Swoole.IDatabase::connect()
	 */
	function connect()
	{
		$db_config = $this->config;
		if(isset($db_config['persistent']) and $db_config['persistent'])
        {
            $this->conn = \mysql_pconnect($db_config['host'].':'.$db_config['port'], $db_config['user'],$db_config['passwd']);
        }
        else
        {
            $this->conn = \mysql_connect($db_config['host'].':'.$db_config['port'], $db_config['user'],$db_config['passwd']);
        }
        if(!$this->conn)
        {
            Swoole\Error::info("SQL Error", \mysql_error($this->conn));
        }
		\mysql_select_db($db_config['name'],$this->conn) or Swoole\Error::info("SQL Error", \mysql_error($this->conn));
		if($db_config['setname'])
        {
            \mysql_query('set names '.$db_config['charset'],$this->conn) or Swoole\Error::info("SQL Error", \mysql_error($this->conn));
        }
	}
	/**
	 * 执行一个SQL语句
	 * @param $sql 执行的SQL语句
	 */
	function query($sql)
	{
		\mysql_real_escape_string($sql, $this->conn);
		$res = \mysql_query($sql,$this->conn);
		if(!$res) echo Swoole\Error::info("SQL Error", \mysql_error($this->conn)."<hr />$sql");
		return new MySQLRecord($res);
	}
	/**
	 * 返回上一个Insert语句的自增主键ID
	 * @return $ID
	 */
	function lastInsertId()
	{
		return \mysql_insert_id($this->conn);
	}

    function quote($value)
    {
        return mysql_real_escape_string($value, $this->conn);
    }

	function ping()
	{
		if(!\mysql_ping($this->conn)) return false;
		else return true;
	}
	/**
	 * 获取上一次操作影响的行数
	 * @return int
	 */
	function affected_rows()
	{
		return \mysql_affected_rows($this->conn);
	}
	/**
	 * 关闭连接
	 * @see libs/system/IDatabase#close()
	 */
	function close()
	{
		\mysql_close($this->conn);
	}
}
class MySQLRecord implements \Swoole\IDbRecord
{
	public $result;
	function __construct($result)
	{
		$this->result = $result;
	}

	function fetch()
	{
		return \mysql_fetch_assoc($this->result);
	}

	function fetchall()
	{
		$data = array();
		while($record = \mysql_fetch_assoc($this->result))
		{
			$data[] = $record;
		}
		return $data;
	}
	function free()
	{
		\mysql_free_result($this->result);
	}
}
