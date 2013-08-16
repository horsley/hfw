<?php
/**
 * Created by JetBrains PhpStorm.
 * User: horsley
 * Date: 12-11-21
 * Time: 下午3:33
 * To change this template use File | Settings | File Templates.
 */
class Template {
    private $_tpl_data = array();
    private $_tpl_name;

    function __construct($tpl_name = '') {
        $this->_tpl_name = $tpl_name;
    }

    /**
     * 模板数据赋值 key会渲染成变量名  value就是变量值
     * @access public
     * @param array $data
     */
    public function assign_array($data = array()) {
        $this->_tpl_data = array_merge($this->_tpl_data, $data);
    }

    /**
     * 模板变量赋值，单值
     * @access public
     * @param string $key 变量名
     * @param any $value 变量值
     */
    public function assign($key, $value) {
        $this->_tpl_data[$key] = $value;
    }

    /**
     * 清除所有模版变量赋值
     * @access public
     */
    public function clean() {
        $this->_tpl_data = array();
    }

    /**
     * 返回模版渲染之后的内容，失败返回假
     * @param string $tpl_name
     * @return bool|string
     */
    public function fetch($tpl_name = '') {
        if(!empty($tpl_name)) {
            $this->_tpl_name = $tpl_name;
        }

        $tpl_file = TPL_ROOT . '/' . $this->_tpl_name . TPL_EXT;
        if (file_exists($tpl_file)) {
            extract($this->_tpl_data, EXTR_SKIP);

            ob_start();
            include $tpl_file;
            $content = ob_get_contents();
            ob_end_clean();

            return $content;
        } else {
            trigger_error('模板文件不存在！');
            return FALSE;
        }
    }

    /**
     * 输出模板
     * @param string $tpl_name
     */
    public function show($tpl_name = '') {
        if($content = $this->fetch($tpl_name)) {
            echo $content;
        }
    }

}