<?php

class WorkSession_End extends \_OS\Event {

    const SEND_TO_LOGGER = false;

    /** @var WorkSession */
    public $WorkSession;

    function __construct(WorkSession $WorkSession) {
        $this->WorkSession = $WorkSession;
    }

} 