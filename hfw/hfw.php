<?php
/**
 * Horsleyli 的简易PHP框架
 * @author horsleyli
 * @version 2013-08-12 1.0
 */

$SYS_CONF = array(
	'DB_HOST' => 'localhost',
	'DB_USER' => 'root',
	'DB_PASS' => '123456',
	'DB_NAME' => 'brain_os',
);


//////////////////////////////////////////////////////
//				楼下的东西一般不要动				//
//////////////////////////////////////////////////////

ini_set("date.timezone", 'Asia/Shanghai'); // 系统时区

define('HFW_ROOT', dirname(__FILE__));
define('TPL_ROOT', HFW_ROOT.'/tpl');
define('ACTION_ROOT', HFW_ROOT.'/action');
define('TPL_EXT', '.php');
define('ACTION_EXT', '_action.class.php');
define('DEFAULT_METHOD', 'index');

//functions
require_once HFW_ROOT. '/core/hfw_functions.php';
//mysql
require_once HFW_ROOT. '/core/hfw_mysql.php';
//template
require_once HFW_ROOT. '/core/hfw_template.php';

/**
* HFW核心
*/
class HFW {
	public static $TPL = ''; 
	public static $DB = '';
	public static function init() {
		global $SYS_CONF;
		self::$TPL = new Template();
		self::$DB = new MySQL($SYS_CONF['DB_HOST'], $SYS_CONF['DB_USER'], $SYS_CONF['DB_PASS'], $SYS_CONF['DB_NAME'], 'utf8');
	}

	public static function dispatch() {
		$url = array_filter(explode('/', $_SERVER['PATH_INFO']));
		$action_file = array_shift($url) . ACTION_EXT;
		require_once ACTION_ROOT .'/'. $action_file;

		$action = ucfirst(array_shift(explode('.', $action_file)));
		$method = array_shift($url);
		$method = empty($method) ? DEFAULT_METHOD : $method;

		$instance = new $action();
		$handler = array($instance, $method);
		if (is_callable($handler)) call_user_func_array($handler, $url);
	}
}

HFW::init();
HFW::dispatch();