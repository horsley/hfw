<?php
/**
 * 简单的MySQL包装类
 * @author horsley
 * @version 2013-08-14
 */
class MySQL {
	const ERR_CONNECT = 'Error establishing mySQL database connection.';
	const ERR_SELECTDB = 'Error selecting specific database.';
	const ERR_MAPROW = 'Error mapping row to null or duplicate key.';

	const FETCH_OBJ = 1;
	const FETCH_ASS = 2;
	const FETCH_NUM = 3;


	private $_hostname = 'localhost';
	private $_username = 'root';
	private $_password = '';
	private $_database = '';
	private $_encoding = 'utf8';

	private $_link_id = 0;
	private $_resource_id = 0;
	private $_last_sql = '';
	private $_showerr = false;

	/**
	 * 初始化
	 * @access public
	 * @param string $dbhost 数据库主机，可空默认为localhost
	 * @param string $dbuser 数据库用户名，可空默认为root
	 * @param string $dbpass 数据库密码
	 * @param string $dbname 数据库名
	 * @param string $encoding 数据库编码，默认为utf8
	 */
	public function __construct($dbhost = '', $dbuser = '', $dbpass = '', $dbname = '', $encoding = '') {
		if (!empty($dbhost)) $this->_hostname = $dbhost;
		if (!empty($dbuser)) $this->_username = $dbuser;
		if (!empty($dbpass)) $this->_password = $dbpass;
		if (!empty($dbname)) $this->_database = $dbname;
		if (!empty($encoding)) $this->_encoding = $encoding;
	}

	/**
	 * 连接数据库，设定编码环境
	 * @access public
	 * @return bool
	 */
	public function connect() {
		$this->_link_id = mysql_connect($this->_hostname, $this->_username, $this->_password);
		$this->_check_err();
		mysql_set_charset($this->_encoding, $this->_link_id);
		$this->select_db();
	}

	/**
	 * 关闭数据库连接，重置类内环境
	 * @access public
	 * @return bool
	 */
	public function close() {
		if (mysql_close($this->_link_id)) {
			$this->_link_id = 0;
			$this->_resource_id = 0;
			$this->_last_sql = '';
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 插入数据
	 * @access public
	 * @param string $table 操作的表名 
	 * @param array $data = array('field_name' => 'field_value') 内部已做escape
	 * @return bool | int 返回insert_id
	 */
	public function insert($table, $data) {
		if (empty($table) || empty($data)) {
			return false;
		}
		$sql = "INSERT INTO `{$table}` SET ";
		foreach ($data as $key => $value) {
			$value = $this->escape($value);
			if (strcasecmp($value, 'null') == 0 || strcasecmp($value, 'now()') == 0) {				
				$sql .= "`{$key}` = {$value}, ";
			} else {
				$sql .= "`{$key}` = '{$value}', ";
			}
		}
		$sql = rtrim($sql, ', ');

		if ($this->query($sql)) {
			return $this->insert_id();
		} else {
			return false;
		}
	}

	/**
	 * 删除数据
	 * @access public
	 * @param string $table 操作的表名 
	 * @param array $where = array('field_name' => 'field_value') 多个条件时条件间关系为and, 内部已做escape
	 * @param int $limit
	 * @return bool
	 */
	public function delete($table, $where = '', $limit = '') {
		if (empty($table)) {
			return false;
		}
		$sql = "DELETE FROM `{$table}` WHERE ";
		if (!empty($where)) {
			foreach ($where as $key => $value) {
				$value = $this->escape($value);
				$sql .= "`{$key}` = '{$value}' AND ";
			}
			$sql = rtrim($sql, ' AND ');
		} else {
			$sql .= '1';
		}
		if (!empty($limit)) {
			$sql .= ' LIMIT ' . $limit;
		}		
		return $this->query($sql);
	}

	/**
	 * 删除数据
	 * @access public
	 * @param string $table 操作的表名
	 * @param array $data = array('field_name' => 'field_value') 内部已做escape 
	 * 		  可赋值field_value 为"{$self} + x" （或-x）来做增量操作，内部会处理sql 设别标志为{$self}
	 * @param array $where = array('field_name' => 'field_value') 多个条件时条件间关系为and, 内部已做escape
	 * @param string $limit
	 * @return bool
	 */
	public function update($table, $data, $where = '') {
		if (empty($table) || empty($data)) {
			return false;
		}
		$sql = "UPDATE `{$table}` SET ";
		foreach ($data as $key => $value) {
			$value = $this->escape($value);
			if(strpos($value, '{$self}') !== false) {
				$sql .= "`{$key}` = ".str_replace('{$self}', "`{$key}`", $value).", ";
			} elseif (strcasecmp($value, 'null') == 0 || strcasecmp($value, 'now()') == 0) {				
				$sql .= "`{$key}` = {$value}, ";
			} else {
				$sql .= "`{$key}` = '{$value}', ";
			}
		}
		$sql = rtrim($sql, ', ') . ' WHERE ';
		if (!empty($where)) {
			foreach ($where as $key => $value) {
				$value = $this->escape($value);
				$sql .= "`{$key}` = '{$value}' AND ";
			}
			$sql = rtrim($sql, ' AND ');
		} else {
			$sql .= '1';
		}
		return $this->query($sql);
	}

	/**
	 * 执行select查询
	 * @access public
	 * @param string $table 操作的表名
	 * @param array $where = array('field_name' => 'field_value') 多个条件时条件间关系为and, 内部已做escape，可空
	 * @param string $field 查询字段，可空默认为*
	 * @param string $order 排序 如 `id` ASC
	 * @param string $limit
	 * @param bool $like 是否用like操作符，对于where的条件，可以自行使用_和@等通配符
	 */
	public function find($table, $where = '1', $field = '*', $order = '', $limit = '', $like = false) {
		if (empty($table)) {
			return false;
		}
		$sql = "SELECT {$field} FROM {$table} WHERE ";
		if (!empty($where)) {
			foreach ($where as $key => $value) {
				$value = $this->escape($value);
				$opr = empty($like) ? '=' : 'LIKE';
				$sql .= "`{$key}` {$opr} '{$value}' AND ";
			}
			$sql = rtrim($sql, ' AND ');
		} else {
			$sql .= '1';
		}
		if (!empty($order)) {
			$sql .= ' ORDER BY ' . $order;
		}
		if (!empty($limit)) {
			$sql .= ' LIMIT ' . $limit;
		}
		return $this->query($sql);
	}

	/**
	 * 结果集数据单行获取
	 * @access public
	 * @param enum $type 可以FETCH_ASS FETCH_NUM FETCH_OBJ 三种返回格式
	 * @return bool | obj | array
	 */
	public function get_data($type = self::FETCH_ASS) {
		if ($type == self::FETCH_ASS) {
			return mysql_fetch_assoc($this->_resource_id);
		} elseif ($type == self::FETCH_OBJ) {
			return mysql_fetch_object($this->_resource_id);
		} elseif ($type == self::FETCH_NUM) {
			return mysql_fetch_row($this->_resource_id);
		}
		return false;
	}

	/**
	 * 获取单一个结果记录里面的一个字段名
	 * 注意不能重复调用本方法获取一条记录里面的多个字段，因为每次操作都会消耗掉结果集里面的一条记录
	 * 获取多字段应该用普通的get_data()方法
	 * @access public
	 * @param string $field 获取的字段名
	 * @return any
	 */
	public function get_field($field) {
		$row = $this->get_data();
		return $row[$field];
	}

	/**
	 * 批量获取结果集全部数据数组
	 * @access public
	 * @param string | bool $map 是否map数组索引，可空默认为假，返回数字索引数组
	 * 			可以设定一个字段名如id，返回的数组会是id到记录行的关联数组，该字段不能有空值或者有重复值
	 */
	public function get_all_data($map = false) {
		$return_arr = array();
		while ($row = $this->get_data()) {
			if (!empty($map)) {
				if (empty($row[$map]) || !empty($return_arr[$row[$map]])) { //记录行中没有该字段或者字段值出现重复
					$this->_showerr AND trigger_error(self::ERR_MAPROW, E_USER_WARNING);
				}
				$return_arr[$row[$map]] = $row;
			} else {
				$return_arr[] = $row;
			}			
		}
		return $return_arr;
	}

	/**
	 * 释放记录集
	 * @access public
	 * @return bool
	 */
	public function free_result() {
		return mysql_free_result($this->_resource_id);
	}

	/**
	 * 底层query接口
	 * @access public
	 * @param string $sql sql语句
	 * @return bool | resource id
	 */
	public function query($sql) {
		if (empty($sql)) {
			return false;
		}
		$this->_last_sql = $sql;
		$this->_check_err();
		$this->_resource_id = mysql_query($sql, $this->_link_id);
		return $this->_resource_id;
	}

	/**
	 * 选定使用的数据库
	 * @access public
	 * @return bool
	 */
	public function select_db() {
		if (!mysql_select_db($this->_database, $this->_link_id) && $this->_showerr) {
			trigger_error(self::ERR_SELECTDB, E_USER_WARNING);
		}
		return true;
	}

	/**
	 * 取得插入更新和删除影响的记录行数 
	 * @access public
	 * @return int
	 */
	public function affected_rows() {
		return mysql_affected_rows($this->_link_id);
	}

	/**
	 * 取得最后插入的自增id
	 * @access public
	 * @return int
	 */
	public function insert_id() {
		return mysql_insert_id($this->_link_id);
	}

	/**
	 * 取得最后错误信息码
	 * @access public
	 * @return int
	 */
	public function errno() {
		return mysql_errno($this->_link_id);
	}

	/**
	 * 取得最后错误信息
	 * @access public
	 * @return string
	 */
	public function error() {
		return mysql_error($this->_link_id);
	}

	/**
	 * 取得上一次查询的sql语句
	 * @access public
	 * @return string
	 */
	public function get_last_sql() {
		return $this->_last_sql;
	}

	/**
	 * 允许输出错误信息
	 * @access public
	 */
	public function show_error() {
		$this->_showerr = true;
	}

	/**
	 * 隐藏错误信息
	 * @access public
	 */
	public function hide_error() {
		$this->_showerr = false;
	}

	public function escape($str) {
		$this->_check_err();
		return mysql_real_escape_string(stripslashes($str), $this->_link_id);
	}

	/**
	 * 检查数据库链接错误
	 * @access private
	 */
	private function _check_err() {
		if (empty($this->_link_id)) {
			$this->connect();
		}
		if (!$this->_link_id && $this->_showerr) {
			trigger_error(self::ERR_CONNECT, E_USER_WARNING);
		}
		return true;
	}
}