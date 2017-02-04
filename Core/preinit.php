<?php

date_default_timezone_set('Europe/Moscow');

include "functions.php";

include "Autoloader.php";

\_OS\Autoloader::preinit();
\_OS\Core::preinit();
\_OS\CoreEvents::preinit();
\_OS\CoreRequests::preinit();

Test::preinit();
StrictMode::preinit();
Context::preinit();
