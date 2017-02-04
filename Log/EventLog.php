<?php
class EventLog {
    use ZeroMQPushPull;

    const ZMQ_DAEMON_OFF = true;
    const ZMQ_DAEMON_BIND_ON_PULL = 1;
    const ZMQ_DAEMON_MAX_ONE_TIME_MESSAGES = 1000;
    const ZMQ_DAEMON_RESTART_INTERVAL = 60;
    const ZMQ_DAEMON_USLEEP_AFTER_EMPTY_READ = 200000;

    /**
    * Push triggered event via Message Queue
    *
    * @param \_OS\Event $event
    * @return void
    */
    static function write(\_OS\Event $Event) {
        $Event->_project = PROJECT;
        self::ZMQPush(self::serialize($Event));
    }


    /**
    * @param Object $object
    * @return string
    */
    static function serialize($object) {
        return serialize($object);
    }

    /**
    * @param string $string
    * @return Object
    */
    static function unserialize($string) {
        return unserialize($string);
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

        $file = $cluster . '/' .  __CLASS__ . '.sock';
        return $file;
    }

    public static function ZMQPullMessageHandler($event_strings) {
        // Not to be implemented
    }
}
