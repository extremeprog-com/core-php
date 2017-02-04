<?php

/**
 * Class Etcd
 * <code>
 * echo Etcd::instance()->set('key', 'value')->get('key');
 * </code>
 */
class Etcd {
    /** @var EtcdClient $Instance */
    protected static $Instance = null;

    /**
     * @return EtcdClient
     */
    public static function instance() {
        if (!isset(self::$Instance)) {
            self::$Instance = new EtcdClient();
            if ($_etcd_servers = glob(getenv('HOME') . "/_cluster*.data/etcd-servers.json")) {
                self::$Instance->setServers(FileAPI::getJSON(array_shift($_etcd_servers)));
            }
        }
        return self::$Instance;
    }

    public static function enabled() {
        static $enabled = false, $evaluated;
//        if(!$evaluated) {
            if ($file_glob = glob(getenv('HOME') . "/_cluster*.data/etcd-servers.json")) {
                $enabled    = (bool) FileAPI::getJSON($file_glob[0]);
            }
            $evaluated  = true;
//        }
        return $enabled;
    }

}
