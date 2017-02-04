<?php

try {

if(isset($_GET['Content-type']))
    header('Content-type: '.$_GET['Content-type']);

$call = $_GET['__call'];

$params = array();
preg_replace_callback("/\\$([a-zA-Z0-9_]+)/", function($matches) use(&$params) {
    $name = $matches[1];
    if(in_array($name, array(
            "GLOBALS",
            "_COOKIE",
            "_ENV",
            "_FILES",
            "_GET",
            "_POST",
            "_REQUEST",
            "_SERVER",
            "_SESSION",
            "HTTP_ENV_VARS",
            "HTTP_POST_FILES",
            "HTTP_GET_VARS",
            "HTTP_POST_VARS",
            "HTTP_SERVER_VARS",
            "HTTP_SESSION_VARS",
            "HTTP_RAW_POST_DATA",
            "http_response_header",
            "argc",
            "argv",
            "php_errormsg",
        )
    )){
        throw new Exception("This request seems like attack. Are you h@x0r?");
    } elseif(array_key_exists($name, $_POST)) {
        $params[$name] = $_POST[$name];
    } elseif(array_key_exists($name, $_GET)) {
        $params[$name] = $_GET[$name];
    } else {
        throw new Exception("Cannot find value for name $name");
    }
}, $call);

$params_list = array();
$pattern = preg_replace_callback("/\\$([a-zA-Z0-9_]+)/", function ($matches) use ($params, &$params_list) {
    $name = $matches[1];
    $params_list[] = $params[$name];
    return '$_';
}, $call);

if(!(new \Request\_OS\WebServer\RPCAccessCheck($pattern, $params_list))->dispatch()) {
    throw new Exception("Access check failed");
}

$_ = array("return $call;", $params);

$closure = function() use ($_) {
    extract($_[1]);
    return eval($_[0]);
};

$json = JSON::hencode($closure());

} catch(Exception $e) {
    $json = JSON::hencode(array(
        '__type' => 'exception',
        'exception.message' => $e->getMessage()
    ));
    Logger()->error($e, __CLASS__);
}

if(isset($_GET['jsonp_cb'])){
    echo "{$_GET['jsonp_cb']}($json)";
} else {
    echo $json;
}
