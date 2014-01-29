<?php
namespace Swoole;

class Factory
{
    protected static $pool;

	static function __callStatic($func, $params)
	{
        $id = $params[0];
        if(empty(self::$pool[$id]))
        {
            $objectType = substr($func, 3);
            $config = \Swoole::getInstance()->config[strtolower($objectType)][$id];
            if(empty($objectType) or empty($config['type'])){
                throw new \Exception("config error.$objectType/$id not found");
            }
            $class = '\\Swoole\\'.$objectType.'\\'.$config['type'];
            self::$pool[$id] = new $class($config);
        }
        return self::$pool[$id];
	}
}