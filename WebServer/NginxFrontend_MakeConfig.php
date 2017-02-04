<?php

abstract class NginxFrontend_MakeConfig extends \_OS\Event {

    const SEND_TO_LOGGER = false;

    public $NginxConfig;

    function __construct(\NginxConfig $NginxConfig) {
        $this->NginxConfig = $NginxConfig;
    }

}