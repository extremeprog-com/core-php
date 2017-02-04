<?php

class Log {

    use ZeroMQPushPull;

    const ZMQ_DAEMON_OFF = true;
    const ZMQ_DAEMON_BIND_ON_PULL = 1;
    const ZMQ_DAEMON_MAX_ONE_TIME_MESSAGES = 1000;
    const ZMQ_DAEMON_RESTART_INTERVAL = 60;
    const ZMQ_DAEMON_USLEEP_AFTER_EMPTY_READ = 200000;

    public static function info($msg, $source = 'common') {
        self::write($msg, $source, __FUNCTION__);
    }

    public static function warn($msg, $source = 'common') {
        self::write($msg, $source, __FUNCTION__);
    }

    public static function error($msg, $source = 'common') {
        self::write($msg, $source, __FUNCTION__);
    }

    public static function security($msg, $source = 'common') {
        self::write($msg, $source, __FUNCTION__);
    }

    /**
     * @param array|string $msg
     * @param string $source
     * @param string $type
     */
    public static function write($msg, $source = 'common', $type = 'info') {
        static $hostname;

        if($msg instanceof Exception) {
            $msg = [
                'message'      => $msg->getMessage(),
                'system_trace' => $msg->getTraceAsString()
            ];
        }

        if (!is_array($msg)) {
            $msg = [
                'message' => $msg
            ];
        }

        if (!isset($msg['project'])) {
            $msg['project'] = PROJECT;
        }

        if (!isset($msg['rev'])) {
            $msg['rev'] = PROJECTREV;
        }

        FireEvent($Message = new Log_Message(
            array_merge(
                [
                    '@timestamp' => self::getTimestamp(),
                    'source'     => $source,
                    'type'       => $type,
                    'host'       => $hostname ?: $hostname = Taskman::getHostname(),
                ],
                $msg

            )
        ));

        $json_message = JSON::encode($Message->message);

        file_put_contents( PATH_LOG.'/'.date('Ymd').'-'.$type.'.log', $json_message."\n", FILE_APPEND);

        if(self::ZMQGetDsnFile()) {
            self::ZMQPush($json_message);
        }

    }

    /**
     * @param \_OS\Event $Event
     */
    public static function event(\_OS\Event $Event) {
        $json_message = JSON::encode([
            "@timestamp" => self::getTimestamp(),
            "type"       => 'event',
            "source"     => get_class(Context()),
            "event"      => get_class($Event),
        ] + (array)$Event);

        file_put_contents(PATH_LOG.'/'.date('Ymd').'-info-event.log', $json_message."\n", FILE_APPEND);

        // Push to log stream
        if(self::ZMQGetDsnFile()) {
            self::ZMQPush($json_message);
        }

        // Push to event stream for analyze via DB
        if (EventLog::ZMQGetDsnFile()) {
            EventLog::write($Event);
        }
    }

    /**
     * @return void
     */
    public static function jsLog() {
        foreach($_REQUEST['ls'] as $log_string) {
            self::ZMQPush($log_string);
        }
    }

    /**
     * @return bool|string
     */
    protected static function getTimestamp() {
        $time = microtime(true);
        return date('Y-m-d\TH:i:s.' . substr($time, 11, 3) . 'O', $time);
    }

    /**
     * @param string|null $grok_pattern
     * @param array $add_keys
     * @param resource|string $stream
     * @param string $delimiter
     * @throws Exception
     */
    public static function fromStdin($grok_pattern = null, array $add_keys = [], $stream = STDIN, $delimiter = PHP_EOL) {
        $Grok       = new Grok;
        $chunk_size = 10000;

        if (is_string($stream) && is_file($stream)) {
            $stream = fopen($stream, 'rb');
        }

        if (!is_resource($stream)) {
            throw new Exception('Bad input stream');
        }

        fseek($stream, 0, SEEK_END);
        while (true) {
            $log_string = stream_get_line($stream, $chunk_size, $delimiter);

            // No new line, wait a bit
            if ($log_string === false) {
                sleep(5);
                continue;
            }

            $msg = ['message' => $log_string];
            if ($grok_pattern) {
                $msg = $Grok->parse($grok_pattern, $log_string) + $msg;
            }

            self::write($add_keys + $msg);
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public static function ZMQGetDsn() {
        return 'ipc://' .self::ZMQGetDsnFile();
    }


    /**
     * @return string
     * @throws Exception
     */
    public static function ZMQGetDsnFile() {
        $cluster = glob(getenv('HOME') . '/_cluster*.env/var');
        $cluster = end($cluster);
        if (Project::getConfigKey('clustered')) {
            return null;
        }
        $file = $cluster . '/' . __CLASS__ . '.sock';
        return $file;
    }

    static function filterCoreTraceStrings($strings) {
        $returning = [];
        foreach(explode("\n", $strings) as $string) {
            if(!preg_match('{_OS\\\(Core)?(Request|Event)|call_user_func|internal function}', $string)) {
                $returning[] = $string;
            }
        }
        return implode("\n", $returning);
    }
}
