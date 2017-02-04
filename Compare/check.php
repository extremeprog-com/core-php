<?php

class check {

    static function exists()            { return new self(__FUNCTION__, null); }
    static function not_exists()        { return new self(__FUNCTION__, null); }
    static function more($val)          { return new self(__FUNCTION__, $val); }
    static function moreOrEq($val)      { return new self(__FUNCTION__, $val); }
    static function less($val)          { return new self(__FUNCTION__, $val); }
    static function lessOrEq($val)      { return new self(__FUNCTION__, $val); }
    static function eq($val)            { return new self(__FUNCTION__, $val); }
    static function not_eq($val)        { return new self(__FUNCTION__, $val); }
    static function startsWith($val)    { return new self(__FUNCTION__, $val); }
    static function type($val)          { return new self(__FUNCTION__, $val); }
    static function fields($val)        { return new self(__FUNCTION__, $val); }
    static function loadable()          { return new self(__FUNCTION__, null); }
    static function callback($val)      { return new self(__FUNCTION__, $val); }
    static function oneOf(array $val)   { return new self(__FUNCTION__, $val); }
    static function match($val)         { return new self(__FUNCTION__, $val); }

    var $method;
    var $value;

    function __construct($method, $value) {
        $this->method = $method;
        $this->value  = $value;
    }

    function check($sample) {
        switch($this->method) {
            case 'exists':
                return !empty($sample);
            case 'not_exists':
                return empty($sample);
            case 'more':
                return $this->value > $sample;
            case 'moreOrEq':
                return $this->value >= $sample;
            case 'less':
                return $this->value < $sample;
            case 'lessOrEq':
                return $this->value <= $sample;
            case 'eq':
                return $this->value == $sample;
            case 'not_eq':
                return $this->value != $sample;
            case 'startsWith':
                return is_string($sample) && substr($sample, 0, strlen($this->value)) == $this->value;
            case 'fields':
                if(method_exists($sample, "getData")) {
                    $sample = $sample->getData();
                }
                foreach($this->value as $field => $value) {
                    if(!($value instanceof check)) {
                        $value = check::eq($value);
                    }
                    if(!$value->check(is_object($sample)?$sample->$field:$sample[$field])) {
                        return false;
                    }
                }
                return true;
            case 'type':
                if($this->value == 'numeric')
                    return is_numeric($sample);
                if($this->value == 'int')
                    return is_int($sample);
                if($this->value == 'string')
                    return is_string($sample);
                if($this->value == 'array')
                    return is_array($sample);
                if($sample instanceof $this->value)
                    return true;
                return false;
            case 'oneOf':
                return in_array($sample, $this->value);
            case 'match':
                return preg_match($this->value, $sample) > 0;
            case 'loadable':
                if(!$sample->isVirtual())
                    return true;
                $sample->_load();
                return !$sample->isVirtual();
            case 'callback':
                return call_user_func($this->value, $sample);
        }
    }

}