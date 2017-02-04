<?php

class CommandLineContext extends Context {

    public $cmd;

    function init() {
        if(getenv('bash_interactive')) {
            register_shutdown_function(function() {
                echo "\n";
            });
        }
        if($this->cmd && function_exists('cli_set_process_title')) {
            cli_set_process_title($this->cmd);
            while(cli_get_process_title() != $this->cmd) {
                usleep(100000);
                cli_set_process_title($this->cmd);
            }
        }
    }

} 