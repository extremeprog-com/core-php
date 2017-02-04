<?php

trait JsonSerializer {

    function jsonSerialize() {
        try {
            $json = [
                $this instanceof _OS\Event ? '_event' : '_class' => get_class($this)
            ];

            if(isset($this->id)) {
                $json['_self'] = $this->getSelf();
            }

            // @todo может быть, когда-нибудь переделать рекурсивно, если это вообще будет нужно
            foreach(isset(class_uses($this)['DataModel']) && ( is_array($data = $this->getData()) || is_object($data) ) ? $data : $this as $key => $val) {
                if(!isset($data) && !(new ReflectionProperty($this, $key))->isPublic()) continue;
                if(preg_match('/^[A-Z]/', $key)) {
                    // object or object list
                    if(is_array($val)) {
                        $json[$key] = [];
                        foreach($val as $i => $o) {
                            if(is_object($o)) {
                                $class = get_class($o);
                                $json[$key][$i] = $class::_prefix.'-'.$o->id;
                            } else {
                                $json[$key][$i] = $o;
                            }
                        }
                    } elseif(is_object($val)) {
                        $class = get_class($val);
                        $json[$key] = $class::_prefix.'-'.$val->id;
                    } else {
                        $json[$key] = $val;
                    }
                } else {
                    $json[$key] = $val;
                }
            }
            return $json;
        } catch(Exception $e) {
            Log::error($e);
            return [
                '_event' => 'System_SerializeException',
                'errmsg' => $e->getMessage(),
                'trace'  => explode("\n", Log::filterCoreTraceStrings($e->getTraceAsString()))
            ];
        }
    }

    public function getSelf() {
        return static::_prefix . '-' . $this->id;
    }
} 