<?php

class Rsync {
    const secret = '8e9695c85368c374a167436194f67a86';
    static function rsyncFrom($host, $dir, $share = 'data') {
        $port = Taskman::getPortFor(static::class, null, $host);
        passthru("rsync -rR --password-file=" . PROJECTENV . "/etc/rsync/rsync.password --port=$port $host::$share/$dir ".PATH_DATA);
    }

    static function rsyncTo($host, $dir, $share = 'data') {
        $port = Taskman::getPortFor(static::class, null, $host);
        chdir(PATH_DATA);
        passthru("rsync -rR --password-file=" . PROJECTENV . "/etc/rsync/rsync.password --port=$port $dir $host::$share");
        chdir(PATH_WORKDIR);
    }
    
        
    public static function configure() {
        CatchEvent(\_OS\Core\System_InitConfigs::class);
        if (!is_dir($dir = PROJECTENV . '/etc/rsync')) {
            mkdir($dir, 0755, true);
        }
        Taskman::prepareConfigs(__DIR__ . '/etc', PROJECTENV . '/etc/rsync/', [
            '%USER%'         => getenv('USER'),
            '%PASSWORD%'     => sha1(PROJECT . getenv('USER') . static::secret),
        ]);
    }
    
    public static function daemonize() {
        CatchEvent(\_OS\Core\System_InitConfigs::class);
        $PROJECTENV = PROJECTENV;
        
        $port = Taskman::getPortFor(static::class);
        Taskman::installDaemonUnderTaskman(
            "rsync",
            "bash -c 'cat /dev/null | rsync --daemon --address=0.0.0.0 --port=$port --no-detach --config=$PROJECTENV/etc/rsync/rsync.conf'"
        );
        
        `chmod 0600 $PROJECTENV/etc/rsync/rsync.secrets $PROJECTENV/etc/rsync/rsync.password`;
    }

}
