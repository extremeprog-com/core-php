<?php

abstract class EtcdKey_Changed extends \_OS\Event {

    function __construct($from, $to) {
        $this->from = $from;
        $this->to = $to;
    }
} 