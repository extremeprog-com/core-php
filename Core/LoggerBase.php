<?php

class LoggerBase {
    const DEBUG    = 10;
    const INFO     = 20;
    const WARN     = 30;
    const ERROR    = 40;
    const SECURITY = 50;

    static $levels = [
        self::DEBUG    => 'debug',
        self::INFO     => 'info',
        self::WARN     => 'warn',
        self::ERROR    => 'error',
        self::SECURITY => 'security',
    ];

    protected $time_format = 'H:i:s';
    
    protected $date_format = 'Y-m-d';
    
    protected $config = array(
        self::DEBUG => array(
            'file'   => 'debug',
            'format' => "[{date} {time}] @{rev} {msg}",
        ),
        self::INFO => array(
            'file'   => 'info',
            'format' => "[{date} {time}] @{rev} {msg}",
        ),
        self::WARN => array(
            'file'   => 'warn',
            'format' => "[{date} {time}] @{rev} {msg}",
        ),
        self::ERROR => array(
            'file'   => 'error',
            'format' => "[{date} {time}] @{rev} {msg}",
        ),
        self::SECURITY => array(
            'file'   => 'error',
            'format' => "[{date} {time}] @{rev} {msg}",
        ),
    );
    
    protected $level;
    
    public function __construct() {
        $this->level = self::DEBUG;
    }
    
    public function debug($msg, $file = null) {
        $this->log($msg, self::DEBUG, $file);
    }
    
    public function info($msg, $file = null) {
        $this->log($msg, self::INFO, $file);
    }
    
    public function warn($msg, $file = null) {
        $this->log($msg, self::WARN, $file);
    }
    
    public function error($msg, $file = null) {
        $this->log($msg, self::ERROR, $file);
    }
    
    public function security($msg, $file = null) {
        $this->log($msg, self::SECURITY, $file);
    }

    public function exception(Exception $E, $file = null) {
        $this->error($E, $file);
    }
    
    /**
     * @param  Exception $E
     * @return string
     */
    public static function formatException(Exception $e) {

        $traceStrings = array();
        $lines = array_reverse($e->getTrace());
        foreach($lines as $key => $line) {
            if(isset($line['class'])) {
                $traceStrings[] = "->".$line['class']."::".$line['function']."():".(isset($line['line'])?$line['line']:null);
            } elseif(isset($line['file']) && isset($line['function'])) {
                $traceStrings[] = "->".basename($line['file']).":".$line['function']."():".$line['line'];
            } elseif(isset($line['function']) && $line['function'] == '{closure}') {
                $rf = new ReflectionFunction($lines[$key-1]['args'][0]);
                $traceStrings[] = "->".$rf->getFileName().":".$rf->getStartLine();
            } else {
                $traceStrings[] = "->".json_encode($line);
            }
        }

        $str = $e->getMessage()."\n".implode("\n", $traceStrings);
//        $str = $e->getMessage()."\n".$e->getTraceAsString();
//        $str = get_class($E) . " with message '{$E->getMessage()}' on {$E->getFile()}:{$E->getLine()}\n"
//             . $E->getTraceAsString();
        
        $str = self::removePasswords($str);
        
        return $str;
    }
    
    public static function removePasswords($str) {
        if (class_exists('Config')) {
            $config = Config::get('database');
            $replace = array();
            foreach ($config as $key => $value) {
                if (isset($value['password'])) {
                    $replace[$value['password']] = 'PASSWORD';
                }
            }
            if (isset($config->queue)) {
                foreach ($config->queue as $c) {
                    $replace[$c['password']] = 'PASSWORD';
                }
            }
            $str = strtr($str, $replace);
        }
        return $str;
    }
    
    /**
     * @param string|Exception $msg
     * @param int              $level
     */
    public function log($msg, $level, $file = null) {
        if ($file === false) return;
        
        if (!is_int($level)) {
            throw new InvalidArgumentException("Level must be int");
        }
        
        if ($level < $this->level) {
            return;
        }
        
        if (is_object($msg) && $msg instanceof Exception) {
            $msg = self::formatException($msg);
        }
        
        if (!isset($this->config[$level])) {
            return $this->error("Do not know how to handle level '$level'");
        }

        Log::write($msg, $file, self::$levels[$level]);

        $params = array(
            '{rev}'  => PROJECTREV,
            '{date}' => date($this->date_format),
            '{time}' => date($this->time_format),
            '{msg}'  => trim($msg) . "\n",
        );
        
        $file = date("Ymd-").strtr($this->config[$level]['file'], $params) . ($file === null ? '' : '-' . $file);
        $msg  = strtr($this->config[$level]['format'], $params);
        
        if (substr($file, -4) != '.log') { $file .= '.log'; }

        @error_log($msg, 3, PATH_LOG . $file);
    }
}