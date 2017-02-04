<?php

class StrictMode {
    static function preinit() {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $e = new ErrorException($errstr, $errno, 0, $errfile, $errline);
            throw $e;
        });

        // Catch uncatchable fatal errors
        register_shutdown_function(function() {
            chdir(getenv('PROJECTPATH'));

            if (!$error = error_get_last())
                return;

            Log::error([
                'message' => "Fatal error '{$error['message']}' in file '{$error['file']}' on line {$error['line']}",
            ], __CLASS__);
        });
    }
}
