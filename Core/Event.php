<?php

namespace _OS;


abstract class Event implements \JsonSerializable {
    use \JsonSerializer;

    const SEND_TO_LOGGER = true;

    protected $_just_created = true;

    function isJustCreated() {
        return (bool)$this->_just_created;
    }

    function dispatch($target = null) {
        \_OS\CoreEvents::dispatchEvent($this, $target);
    }
}