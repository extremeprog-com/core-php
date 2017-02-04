<?php

class JSON {

    static function encode($val) {
        return json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    static function decode($val, &$error = false, $validate_checks = [], &$exception = null) {
        try {
            $result = json_decode($val, true);
            if(!$result && $msg = json_last_error_msg()) {
                throw new Exception($msg);
            }
            foreach($validate_checks as $id => $check) {
                /** @var check $check */
                if(!$check->check($result)) {
                    throw new Exception("Check $id is invalid");
                }
            }
        } catch(Exception $e) {
            $exception = $e;
            $error = 1;
        }
        return isset($result)?$result:$val;
    }

    static function hencode($val) {
        return json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    static function prettify($val) {
        try {
            $result = JSON::decode($val);
        } catch(Exception $e) {
            // пропускаем - это не нужно логгировать
        }
        return isset($result) ? JSON::encode( $result ) : $val;
    }

    static function hprettify($val) {
        return JSON::hencode( JSON::decode($val) );
    }

    static function check($val) {
        try {
            JSON::decode($val);
            return true;
        } catch(Exception $e) {
            return false;
        }
    }

}