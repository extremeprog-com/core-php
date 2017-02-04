<?php

class WorkSession_Start extends \_OS\Event {

    const SEND_TO_LOGGER = false;

    /** @var WorkSession */
    public $WorkSession;

    function __construct(WorkSession $WorkSession) {
        $this->WorkSession = $WorkSession;
    }

} 