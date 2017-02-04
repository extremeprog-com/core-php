<?php

class DB {

    const secret = "mcwo98y4hbkczkwowjlroj39r83";

    static $connection = [];

    static $adapter = 'pgsql';
    static $host = 'localhost';
    static $db_name = 'karaoke_widget';
    static $user = 'karaoke_widget';
    static $pass = '123321';

    /** @var PDO */
    protected $db_link;

    /**
     * Получение коннекта к базе в виде PDO объекта
     * @param	string|null	$dsn
     * @return	PDO		
     */
    static function connect($dsn = null) {
        $connection = &static::$connection[$dsn];
        if (!$connection) {
            $connection = new self();
            $connection->db_link = self::storage($dsn);
        }
        return $connection->db_link;
    }

    /**
     * создание объекта PDO
     * @param	string|null	$dsn
     * @return	PDO	
     */
    static function storage($dsn = null) {     
        $pdo = new PDO($dsn ? $dsn : static::getDsn());
        
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }
    
    /**
     * Получение строки для коннекта
     * @param   stinrg      Host role for db
     * @return	string		DSN to connect
     */
    public static function getDsn($pg_role = PostgresStateDaemonHost::class) {
        if (is_file($file = Deploy::REPOS_CONFIG . '/' . PROJECT . '.db.conf')) {
            $conf = parse_ini_file($file);
            extract($conf);
        } elseif ($host = HostRole::getRoleHosts($pg_role, true, true)) {
            $password = md5(DB::secret.__PROJECT__);
            $host .= '.ext';
            $port = Taskman::getPortFor($pg_role, null, HostRole::getRoleHosts($pg_role, true, true));
            $user = __PROJECT__;
            $dbname = __PROJECT__;
        } else {
            throw new Exception("Cant find host for role $pg_role");
        }

        return self::$adapter .
            ':host=' . $host .
            ';port=' . $port .
            ';dbname=' . $dbname .
            ';user=' . $user .
            ';password=' . $password;
    }

//    function __call($method, $args) {
//        $result = call_user_func_array([$this->db_link, $method], $args);
////        $error_info = $this->db_link->errorInfo()[2];
////        if($error_info) {
////            throw new Exception($error_info);
////        }
////        if($result instanceof PDOStatement) {
////            $result = new UtilDecorator($result, function($method, $args) {
////                /** @var PDOStatement $this */
////                $result = call_user_func_array([dbg::$i->sdij = $this, $method], $args);
//////                dbg::$i->sjo = $this->errorInfo();
//////                $error_info = $this->errorInfo()[2];
//////                if($error_info) {
//////                    throw new Exception($error_info);
//////                }
////                return $result;
////            });
////        }
//        return $result;
//    }

}