<?php

trait MongoStorage
{

    static $instances = 1;

    static function addMongoServer()
    {
        CatchEvent([__CLASS__.__TRAIT__."Host" => System_InitConfigs]);
        for ($i = 0; $i < static::$instances; $i++) {
            $RoleClass = __CLASS__.__TRAIT__."Host";
            $port = Taskman::getPortFor($RoleClass, $i);
            `mkdir -p \$PROJECTDATA/mongo$i`;
            Taskman::installDaemonUnderTaskman(__TRAIT__. $i,
                'mongod --dbpath=' . PATH_DATA . '/mongo' . $i . '/ '.
                '--noprealloc --smallfiles --port '.$port.(self::$instances>1?' --replSet rs0':'')
            );
        }
    }

    static $mongo_connection;

    static function MongoStorage()
    {
        if (!self::$mongo_connection) {
            $connection_string = 'mongodb://';
            for($i = 0; $i < static::$instances; $i++) {
                $RoleClass = __CLASS__.__TRAIT__."Host";
                $host = HostRole::getRoleHosts($RoleClass, true, true);
                $port = Taskman::getPortFor($RoleClass, $i, $host);
                $connection_string .= ($i?',':'').$host.':'.$port;
            }

            self::$mongo_connection = (new MongoClient($connection_string, [] +
                    (self::$instances>1?['replicaSet' => 'rs0']:[])
            ))->selectDB(static::class);
            self::$mongo_connection->setReadPreference(MongoClient::RP_NEAREST);
        }
        return self::$mongo_connection;
    }

    static function _MongoStorageMakeRole() {
        CatchEvent(System_InitConfigs);

        $RoleClass = __CLASS__.__TRAIT__."Host";

        $template = <<<EOF
<?php

class $RoleClass extends HostRole {
    static function installCronBackup() {
        CatchEvent([$RoleClass::class => System_InitConfigs]);

        Taskman::installCronTask('10 04 * * * source ~/.projectsrc; cdproject ' . PROJECT . '; php-r \'Backup::make("' . PROJECTDATA . '/mongo");\'', $RoleClass::class . 'Backup');
    }
}

EOF;

        file_put_contents(PATH_TMP."/".$RoleClass.".php", $template);
    }

}