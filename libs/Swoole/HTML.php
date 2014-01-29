<?php
/**
 * HTML DOM处理器
 * 用于处理HTML的内容，提供类似于javascript DOM一样的操作
 * 例如getElementById getElementsByTagName createElement等
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage HTML
 *
 */
class HTML
{
	static function deleteComment($content)
	{
	    return preg_replace('#<!--[^>]*-->#','',$content);
	}
}
?>