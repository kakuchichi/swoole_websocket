<?php
namespace Swoole;
/**
 * 模型加载器
 * 产生一个模型的接口对象
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage MVC
 */
class ModelLoader
{
	private $swoole = null;
	public $_models = array();

	function __construct($swoole)
	{
		$this->swoole = $swoole;
	}

	function __get($model_name)
	{
		if(isset($this->_models[$model_name]))
		return $this->_models[$model_name];
		else return $this->load($model_name);
	}

	function load($model_name)
	{
		$m = explode('/', $model_name, 2);
		if(count($m) > 1)
		{
			$model_file = \Swoole::$app_path."/{$m[0]}/models/{$m[1]}.model.php";
			$model_class = $m[1];
		}
		else
		{
			$model_file = \Swoole::$app_path.'/models/'.$m[0].'.model.php';
			$model_class = $m[0];
		}
		if(!is_file($model_file)) throw new Error("不存在的模型, <b>$model_name</b>");
		require_once($model_file);
		$this->_models[$model_name] = new $model_class($this->swoole);
		return $this->_models[$model_name];
	}
}
?>