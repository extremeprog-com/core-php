<?php

trait SphinxStorage
{

    static function generateSphinxConfig() {
        CatchEvent(System_InitConfigs);
        $daemon_name = __CLASS__.__TRAIT__."Host";
        $port = Taskman::getPortFor(__CLASS__.__TRAIT__."Host");

        `mkdir -p \$PROJECTDATA/$daemon_name`;

        $indexes_str = '';
        foreach (self::$indexes as $index_name => $index_request) {
            $indexes_str .= "\n".strtr(
                file_get_contents(__DIR__."/index.conf.template"),
                [
                    '%INDEX_NAME%'      =>  $index_name,
                    '%PROJECTDATA%'     =>  PROJECTDATA,
                    '%ROLE%'            =>  $daemon_name,
                    '%INDEX_REQUEST%'   =>  $index_request,
                ]
            );
        }

        Taskman::prepareConfigs(__DIR__."/etc/", PROJECTENV."/etc/sphinx/$daemon_name/", [
            "%INDEXES%"         => $indexes_str,
            "%PORT%"            => $port,
            "%ROLE%"            => $daemon_name,
            "%HOST%"            => HostRole::getRoleHosts(__CLASS__.__TRAIT__."Host", true, true),
            "%PATH_WORKDIR%"    => PATH_WORKDIR,
        ]);

        $cmd = 'searchd --nodetach --config '.PROJECTENV."/etc/sphinx/$daemon_name/sphinx.conf -p $port";
        Taskman::installDaemonUnderTaskman($daemon_name, $cmd);
    }

    static function SphinxStorage()
    {
        static $SphinxConnection;
        if (!$SphinxConnection) {
            $SphinxConnection = new SphinxClient();
            $SphinxConnection->SetServer(
                $host = HostRole::getRoleHosts(__CLASS__.__TRAIT__."Host", true, true),
                $port = Taskman::getPortFor(__CLASS__.__TRAIT__."Host", null, $host));
        }
        return $SphinxConnection;
    }

    static function SphinxIndexer() {
        CatchEvent([__CLASS__.__TRAIT__."Host" => SphinxRotateIndex::class]);
        $daemon_name = __CLASS__.__TRAIT__."Host";
        passthru("indexer --all --rotate --config ".PROJECTENV."/etc/sphinx/$daemon_name/sphinx.conf");
    }

    static function _SphinxStorageMakeRole() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        $RoleClass = __CLASS__.__TRAIT__."Host";

        $template = <<<EOF
<?php

class $RoleClass extends HostRole {

}

EOF;

        file_put_contents(PATH_TMP."/".$RoleClass.".php", $template);
    }

}