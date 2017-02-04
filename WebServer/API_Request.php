<?php

trait API_Request {

    public function addObjectToResult()
    {
        if (!isset($this->result)) {
            $this->result = [];
        }
        foreach (func_get_args() as $arg) {
            $this->result[] = $arg;

            foreach ($arg as $field => $value) {
                if (preg_match('/^[A-Z]/', $field)) {
                    if (is_array($value)) {
                        foreach ($value as $o) {
                            if(!in_array($o, $this->result)) {
                                $this->result[] = $o;
                            }
                        }
                    } elseif (is_object($value)) {
                        if(!in_array($value, $this->result)) {
                            $this->result[] = $value;
                        }
                    }
                }
            }
        }
        
    }

}