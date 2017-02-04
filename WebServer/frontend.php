<?php

chdir($_SERVER['PROJECTPATH']);
putenv("PWD=".$_SERVER['PROJECTPATH']);
require_once(__DIR__.'/../Core/preinit.php');

putenv('PROJECT=' . PROJECT);
putenv('PROJECTENV=' . PATH_ENV);
putenv('PROJECTLOG=' . PATH_LOG);
putenv('PROJECTPATH=' . PATH_WORKDIR);
putenv('PROJECTREV=' . REVISION);
putenv('PATH=' . PATH_WORKDIR . ':' . PATH_ENV . '/bin:' . getenv('PATH'));
putenv('IS_FPM_MODE=1');

$_SERVER['FRONTEND']::handleRequest();

