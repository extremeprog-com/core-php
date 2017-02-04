<?php

class API_Success extends \_OS\Event {

    public function __construct(array $data = []) {
        foreach($data as $key => $val) {
            $this->$key = $val;
        }
    }

}