<?php

abstract class _OSTask implements _OSTaskIf {

    static function run_inline(array $methods, $retry_possible_count = 3, $retry_run_count = 3) {
        $Context = new Context(Context());
        while(!$methods['is_possible']($Context) && --$retry_possible_count) {
            $methods['make_possible']($Context);
        }
        if(!$retry_possible_count) {
            throw new Exception("retry_possible_count exceeded");
        }
        while(!$methods['been_run']($Context) && --$retry_run_count) {
            $methods['run']($Context);
        }
        if(!$retry_run_count) {
            throw new Exception("retry_run_count exceeded");
        }
    }

}