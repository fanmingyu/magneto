<?php
/**
 * 逆波兰表达式及计算器
 * 支持加、减、乘、除、括号
 *
 * $calc = new Calc('(1+9)/2');
 * echo $calc->getExpression();
 * echo $calc->calculate();
 */

namespace Group\Magneto\Plugins;

class RPNCalc
{

    protected $_stackOperator = array('@');
    protected $_stackOut = array();
    protected $_operator = array('@', '(', ')', '+', '-', '*', '/');
    protected $_priority = array('@' => 0, '(' => 10, ')' => 10, '+' => 20, '-' => 20, '*' => 30, '/' => 30);

    public function __construct($expression)
    {
        $expression = str_replace(' ', '', $expression);
        $this->convert($expression);
    }

    /**
     * 解析字符串表达式
     * 解析字符串表达式，将数字和运算符分离，用数组存储
     * @param string $expression
     * @return array
     */
    protected function expressionParase($expression) {
        $arr = str_split($expression);
        $data = $tmp = array();
        do {
            $item = array_shift($arr);
            if (in_array($item, $this->_operator)) {
                if ($tmp) {
                    array_push($data, implode('', $tmp));
                    $tmp = array();
                }
                array_push($data, $item);
            } else {
                array_push($tmp, $item);
            }
        } while(count($arr));
        array_push($data, implode('', $tmp));
        return $data;
    }

    /**
     * 生成逆波兰式
     * @param string $expression
     */
    protected function convert($expression) {
        foreach ($this->expressionParase($expression) as $char) {
            //如果是操作符
            if (in_array($char, $this->_operator)) {
                if ('(' == $char) {
                    array_push($this->_stackOperator, $char);
                } else if (')' == $char) {
                    while (count($this->_stackOperator) > 1) {
                        $drop = array_pop($this->_stackOperator);
                        if ('(' == $drop) {
                            break;
                        } else {
                            array_push($this->_stackOut, $drop);
                        }
                    }
                } else {
                    while (count($this->_stackOperator)) {
                        $oTop = end($this->_stackOperator);
                        if ($this->_priority[$char] > $this->_priority[$oTop]) {
                            array_push($this->_stackOperator, $char);
                            break;
                        } else {
                           $drop = array_pop($this->_stackOperator);
                            array_push($this->_stackOut, $drop);
                        }
                    }
                }
            //变量
            } else {
                array_push($this->_stackOut, $char);
            }
        }

        while (count($this->_stackOperator)) {
            $drop = array_pop($this->_stackOperator);
            if ('@' == $drop) {
                break;
            } else {
                array_push($this->_stackOut, $drop);
            }
        }
    }

    /**
     * 获取逆波兰式
     */
    public function getExpression()
    {
        return $this->_stackOut;
    }

    /**
     * 获取第一个变量名
     */
    public function getFirstVar()
    {
        foreach ($this->_stackOut as $var) {
            if (!in_array($var, $this->_operator) && !is_numeric($var)) {
                return $var;
            }
        }

        return '';
    }

    private $getVarValueFunction = '';

    /**
     * 获取变量值的方法
     */
    public function setGetVarValueFunction($function)
    {
        $this->getVarValueFunction = $function;
    }

    private $operatorFunction = '';

    /**
     * 计算方法设置
     */
    public function setOperatorFunction($function)
    {
        $this->operatorFunction = $function;
    }

    /**
     * 计算逆波兰式
     * @return int
     */
    public function calculate()
    {
        $stack = array();
        foreach ($this->_stackOut as $char) {
            if ($char === '') {
                continue;
            }
            //如果操作符，则取出两个值进行计算
            if (in_array($char, $this->_operator)) {
                $b = call_user_func($this->getVarValueFunction, array_pop($stack));
                $a = call_user_func($this->getVarValueFunction, array_pop($stack));

                array_push($stack, call_user_func($this->operatorFunction, $a, $b, $char));
            } else {
                array_push($stack, call_user_func($this->getVarValueFunction, $char));
            }
        }

        return end($stack);
    }

    protected function operator($a, $b, $o) {
        switch ($o) {
            case '+':
                return $a + $b;
                break;
            case '-':
                return $a - $b;
                break;
            case '*':
                return $a * $b;
                break;
            case '/':
                return $a / $b;
                break;
        }
    }

}
