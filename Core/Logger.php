<?php

class Logger extends LoggerBase {
    const LEVEL_ERROR = 1;
    
    const LEVEL_WARNING = 2;
    
    const LEVEL_NOTICE = 3;
    
    const LEVEL_DEBUG = 4;
    
    public function logUnhandledException($e) {
        $this->exception($e);
    }
    
    public function logException($e) {
        $this->exception($e);
    }
    
    public function logEvent($level, $message) {
        switch ($level) {
            case self::LEVEL_ERROR:
            case 'Error':
                return $this->error($message);
            
            case self::LEVEL_WARNING:
            case 'Warning':
                return $this->warn($message);
            
            case self::LEVEL_NOTICE:
            case 'Notice':
                return $this->info($message);
            
            case self::LEVEL_DEBUG:
            case 'Debug':
                return $this->debug($message);
            
            case 'Security':
                return $this->security($message);

            default:
                return $this->warn($message);
        }
    }
    
    public function logToFilename($filename, $message, $isAddDate = false ) {
        $this->info($message, $filename);
    }

    public function logToFile($message) {
        $this->info($message);
    }
    
    public function logToMail($message) { }

    public function logTo($destination, $message) {
        $method = 'logTo' . $destination;
        $this->$method($message);
    }
    
    public function removePasswordsFromTrace($str) {
        return self::removePasswords($str);
    }
}

