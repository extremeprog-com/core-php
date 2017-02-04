<?php

class Log_Message extends _OS\Event {

    const SEND_TO_LOGGER = false;

    public $message;

    function __construct($message) {
        $this->message = $message;
    }

} 
