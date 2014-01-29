<?php
namespace Swoole\Cache;
/**
 * Memcache封装类，支持memcache和memcached两种扩展
 * @author Tianfeng.Han
 * @package Swoole
 * @subpackage cache
 */
class CMemcache implements \Swoole\IFace\Cache
{
    /**
     * memcached扩展采用libmemcache，支持更多特性，更标准通用
     */
    public $memcached = false;
    public $multi = false;
    //启用压缩
    static $compress = MEMCACHE_COMPRESSED;
    public $cache;

    function __construct($configs)
    {
        $this->cache = $this->memcached?new \Memcached:new \Memcache;
        //多服务器
        if($this->multi)
        {
            foreach($configs as $cf) $this->addServer($cf);
        }
        else $this->addServer($configs);
    }
    /**
     * 格式化配置
     * @param $cf
     * @return unknown_type
     */
    function format_config(&$cf)
    {
        if(empty($cf['host'])) $cf['host'] = 'localhost';
        if(empty($cf['port'])) $cf['port'] = 11211;
        if(empty($cf['weight'])) $cf['weight'] = 1;
        if(empty($cf['persistent'])) $cf['persistent'] = false;
    }
    /**
     * 增加节点服务器
     * @param $cf
     * @return unknown_type
     */
    private function addServer($cf)
    {
        $this->format_config($cf);
        if($this->memcached) $this->cache->addServer($cf['host'],$cf['port'],$cf['weight']);
        else $this->cache->addServer($cf['host'],$cf['port'],$cf['persistent'],$cf['weight']);
    }
    /**
     * 获取数据
     * @see libs/system/ICache#get($key)
     */
    function get($key)
    {
        return $this->memcached?$this->cache->getMulti($key):$this->cache->get($key);
    }
    function set($key,$value,$expire=0)
    {
        return $this->memcached?$this->cache->set($key,$value,$expire):$this->cache->set($key,$value,self::$compress,$expire);;
    }
    function delete($key)
    {
        return $this->cache->delete($key);
    }
    function flush()
    {
        return $this->cache->flush();
    }
    function __call($method,$params)
    {
        return call_user_func_array(array($this->cache,$method),$params);
    }
}