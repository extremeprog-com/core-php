<?php
/**
 * Created by JetBrains PhpStorm.
 * User: osergienko
 * Date: 20.09.13
 * Time: 5:24
 * To change this template use File | Settings | File Templates.
 */

class UtilUrl {
    static public function change_get_params(){

        $string = $_SERVER['REQUEST_URI'];
        $path = parse_url($string,PHP_URL_PATH);
        $params = array();
        foreach(explode("&",parse_url($string,PHP_URL_QUERY)) as $f){
            if(!$f)continue;
            list($key,$val) = explode("=",$f,2);
            $params[$key] = $val;
        }

        $input_params = func_get_args();

        foreach(array_chunk($input_params, 2) as $pair){
            list($key,$val) = $pair;
            $params[$key] = $val;
        }

        return $path."?".http_build_query($params);
    }

}