<?php

namespace Request\_OS\WebServer;

class RPCAccessCheck extends \_OS\Request {

    const _name = __CLASS__;

    protected $pattern;
    protected $params;

    function __construct($pattern, $params) {
        $this->pattern = $pattern;
        $this->params = $params;
    }

    function check($pattern, $cb) {
        $i = 0;
        $params = array();
        $params_list = $this->params;
        if(preg_replace("/\\$([a-zA-Z0-9_]+)/", '$_', $pattern) != $this->pattern) {
            return;
        }
        if(preg_replace_callback("/\\$([a-zA-Z0-9_]+)/", function($matches) use (&$params, &$i, $params_list) {
                $name = $matches[1];
                $params[$name] = $params_list[$i];
                $i++;
                return '$_';
            }, $pattern) == $this->pattern) {
        }
        $params_list = array();
        $rf = new \ReflectionFunction($cb);
        foreach($rf->getParameters() as $i => $rp) {
            $params_list[$i] = $params[$rp->getName()];
        }
        $this->result = call_user_func_array($cb, $params_list);
    }

}