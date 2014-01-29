<?php
/**
 * 用于读取配置文件
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage base
 *
 */
class SwooleConfig implements ArrayAccess
{
    private $_data = array();

    function load($key)
    {
        $f = APPSPATH.'/configs/'.$key.'.inc.php';
        if(is_file($f))
        {
            require $f;
            $this->_data[$key] = $$key;
        }
        else return new Error("Config file {$f} not found!".NL);
    }
    /**
     * 键值对应数组的配置编码
     * @param $array
     * @return unknown_type
     */
    static function encode($array)
    {
        $return = '';
        foreach($array as $k=>$v)
        {
            $return .= $k.':'.$v."\n";
        }
        return $return;
    }
    /**
     * 字符串配置解码
     * @param $array
     * @return unknown_type
     */
    static function decode($str)
    {
        $lines = explode("\n",$str);
        foreach($lines as $li)
        {
            list($k,$v) = explode(":",$li);
            $return[$k] = $v;
        }
        return $return;
    }

    function save($key)
    {
        $f = APPSPATH.'/configs/'.$key.'.inc.php';
        file_put_contents($f,var_export($this->_data,true));
    }

    function offsetExists($keyname)
    {
        return isset($this->_data[$keyname]);
    }

    function offsetGet($keyname)
    {
        if(!isset($this->_data[$keyname])) $this->load($keyname);
        return $this->_data[$keyname];
    }

    function offsetSet($keyname,$value)
    {
        $this->_data[$keyname] = $value;
    }

    function offsetUnset($keyname)
    {
        unset($this->_data[$keyname]);
    }
}