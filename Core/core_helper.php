<?php
/**
 * @param $e
 * @return \_OS\Event
 */
function CatchEvent($e) {
    return end(\_OS\CoreEvents::$LastCatchedEvents);
}
/**
 * @param $e
 * @return \_OS\Request
 */
function CatchRequest($e) {
    return end(\_OS\CoreRequests::$LastCatchedRequests);
}

function FireEvent(\_OS\Event $Event, $target = null) {
    $Event->dispatch($target);
    return $Event;
}

function FireRequest(\_OS\Request $Request, $target = null) {
    return $Request->dispatch($target);
}

function Monitor() {
    return Monitor::getInstance();
}

/**
 * @return Logger
 */
function Logger() {
    static $Logger;
    return $Logger ?: $Logger = new Logger;
}


preg_match('@^(/(home|data|Users)/[^/]+/[^/]+)/([^/]*)@', getenv("PWD")."/", $matches);
list(, $root, , $revision) = $matches;

$revision = $revision?:getenv('PROJECTREV');

define('PATH_ROOT', $root);
define('PATH_WORKDIR', $root.($revision?'/'.$revision:''));
define('PROJECT', basename($root));
define('PATH_DATA', $root.".data");
define('PATH_ENV', $root.".env");
define('PATH_TMP', PATH_WORKDIR."/tmp");
define('PATH_CONFIG', $root.".config");
define('REVISION', $revision);

define('PROJECTROOT', PATH_ROOT);
define('PROJECTENV', PATH_ENV);
define('PROJECTPATH', PATH_WORKDIR);
define('PROJECTTMP', PATH_TMP);
define('PROJECTDATA', PATH_DATA);
define('PROJECTCONFIG', PATH_CONFIG);
define('PROJECTREV', REVISION);
define('PROJECTUSER', posix_getpwuid(posix_geteuid())["name"]);

define('__PROJECT__',       PROJECT);
define('__PROJECTROOT__',   PATH_ROOT);
define('__PROJECTENV__',    PATH_ENV);
define('__PROJECTPATH__',   PATH_WORKDIR);
define('__PROJECTTMP__',    PATH_TMP);
define('__PROJECTDATA__',   PATH_DATA);
define('__PROJECTCONFIG__', PATH_CONFIG);
define('__PROJECTREV__',    REVISION);

$log_path = $root.'.log/';
if(!is_dir($log_path)) {
    `mkdir -p $log_path`;
}

define("PATH_LOG", $log_path);
define('PROJECTLOG', PATH_LOG);
define('__PROJECTLOG__',    PATH_LOG);

define(
'System_InitConfigs'
,
 \_OS\Core\System_InitConfigs::class
 );

