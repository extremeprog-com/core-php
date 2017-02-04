<?php

namespace _OS;


abstract class Request {

    public $result;

    protected $_just_created = true;

    function isJustCreated() {
        return (bool)$this->_just_created;
    }

    function dispatch($target = null) {
        return CoreRequests::dispatchRequest($this, $target);
    }

    static function generateRequestEventClasses() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        $class = static::class . '_Success';
    
        if (file_exists(PATH_WORKDIR."/tmp/$class.php") || !class_exists($class)) {
            file_put_contents(PATH_WORKDIR."/tmp/$class.php",
                <<<EOF
<?php

class $class extends API_Success {

}

EOF
            );
        }

        $class = static::class . '_Fail';

        if (file_exists(PATH_WORKDIR."/tmp/$class.php") || !class_exists($class)) {
            file_put_contents(PATH_WORKDIR."/tmp/$class.php",
                <<<EOF
<?php

class $class extends API_Fail {

}

EOF
            );
        }

    }
}