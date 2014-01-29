<?php
define("WEBPATH",{WEBPATH});
define("WEBROOT",dirname($_SERVER['SCRIPT_URI']));
//Database Driver，可以选择PdoDB , MySQL, MySQL2(MySQLi) , AdoDb(需要安装adodb插件)
define('DBTYPE','{DBTYPE}');
define('DBENGINE','MyISAM');
define("DBMS","mysql");
define("DBHOST","{DBHOST}");
define("DBUSER","{DBUSER}");
define("DBPASSWORD","{DBPASSWORD}");
define("DBNAME","{DBNAME}");
define("DBCHARSET","utf8");

//字典数据目录
define("DICTPATH",WEBPATH.'/dict');

//应用程序的位置
define("APPSPATH",WEBPATH.'/apps');
define('HTML',WEBPATH.'/html');
define('HTML_URL_BASE','/html');
define('HTML_FILE_EXT','.html');


//上传文件的位置
define('UPLOAD_DIR','/static/uploads');
define('FILECACHE_DIR',WEBPATH.'/cache/filecache');

//缓存系统
//define('CACHE_URL','memcache://localhost:11211');
define('CACHE_URL','file://localhost#site_cache');

//SESSION设置
//define('SESSION_CACHE','memcache://localhost:11211');
//define('KDB_CACHE','memcache://127.0.0.1:1978');
//efine('KDB_CACHE','file://localhost#item_cache');
//define('KDB_ROOT','cms,user');

require('libs/lib_config.php');
$php->autoload('db','tpl','cache');
//$php->plugin->load('kdb');
$php->loadConfig();
mb_internal_encoding('utf-8');

//内容压缩
$php->gzip();