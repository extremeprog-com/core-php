<?php

class HostRole extends Context {
    static $roles = [];
    static function getAllDeclaredRoles() {
        CatchEvent(\_OS\Core\System_InitConfigs::class);
        $roles = [];

        \_OS\Autoloader::loadAll();
        foreach(get_declared_classes() as $class) {
            if(is_subclass_of($class, HostRole::class)) {
                $roles[] = $class;
            }
        }

        return $roles;
    }

    /**
     * @return array
     * @throws Exception
     */
    static function getRoles2Hosts() {
        $config = Project::getConfig();
        if(!static::$roles) {
            $host = Taskman::getHostname();
            if(isset($config['multirole']) && $config['multirole']) {
                foreach(self::getAllDeclaredRoles() as $role) {
                    static::$roles[$role] = [$host];
                }
            } else {
                static::$roles = Roles2HostsEtcdKey::get();

                if (!static::$roles) {
                    return [];
//                    throw new Exception('No host roles for project ' . PROJECT);
                }
            }
        }
        return static::$roles;
    }

    /**
     * @param string $role
     * @param bool $return_once
     * @param bool $trim_username
     * @return array
     */
    static function getRoleHosts($role, $return_once = false, $trim_username = false) {
        $roles = self::getRoles2Hosts();
        $returning = isset($roles[$role])?$roles[$role]:[];
        if($trim_username) {
            foreach($returning as &$host) {
                if($pos = strpos($host, "@")) {
                    $host = substr($host, $pos + 1);
                }
            }
        }

        if ($return_once) {
            return isset($returning[0]) ? $returning[0] : '';
        }

        return $returning;
    }

    /**
     * Добавление нового хоста для роли
     * @param	string	$role	Роль
     * @param	string	$host	Новый хост
     * @return	void
     */
    public static function addToHost($host) {
        $hosts = (array) static::getRoleHosts($role = static::class);
        if (in_array($host, $hosts)) {
            throw new Exception("Trying to add duplicated host '$host' to role '$role'");
        }
        $hosts = array_merge($hosts, [$host]);
        if (Etcd::enabled()) {
            Roles2HostsEtcdKey::modify($role, $hosts);
        }
        static::$roles[$role] = $hosts;
    }
    
    /**
     * Summary
     * @return	object		Description
     */
    public static function addToHosts() {
        foreach (func_get_args() as $arg) {
            static::addToHost($arg);
        }
    }

    /**
     * Удаление хоста у роли
     * @param	string	$role	Роль
     * @param	string	$host	Хост для удаления
     * @return	void			
     */
    public static function removeFromHost($host) {
        $hosts = static::getRoleHosts($role = static::class);
        if (false !== $key = array_search($host, $hosts)) {
            unset($hosts[$key]);
            // Для правильного кодирования jsoи работы деплолки
            // нужно, чтобы знаения в массиве был последовательны
            // Если удалием нулевой элемент, все сломается
            $hosts = array_values($hosts);
        }
        if (Etcd::enabled()) {
            Roles2HostsEtcdKey::modify($role, $hosts);
        }
        static::$roles[$role] = $hosts;
    }
    
    public static function removeFromHosts() {
        foreach (func_get_args() as $arg) {
            static::removeFromHost($arg);
        }
    }    
    
    /**
     * Сохранение ролей в хранилище
     */
    static function _writeAllRoles2Hosts() {
        CatchEvent(\_OS\Core\System_InitConfigs::class);

        $roles2hosts = Roles2HostsEtcdKey::get();

        foreach(self::getAllDeclaredRoles() as $role) {
            if(!isset($roles2hosts[$role])) {
                $roles2hosts[$role] = [];
            }
        }
        
        if (Etcd::enabled()) {
            Roles2HostsEtcdKey::set($roles2hosts);
        }
    }


    /**
     * @param null|string $host
     * @return array
     */
    public static function getRolesByHost($host = null) {
        if (is_null($host)) {
            if (file_exists(PATH_ENV . '/my.host')) {
                $host = trim(file_get_contents(PATH_ENV . '/my.host'));
            } else {
                $host = Taskman::getHostname();
            }
        }
        $roles = [];
        $roles2hosts = self::getRoles2Hosts();
        foreach ($roles2hosts as $role => $hosts) {
            if (in_array($host, $hosts)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }

    /**
     * Генераций классов добавления/удаления роли
     */
    public static function _generateRoleChangeEvents() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        foreach (self::getAllDeclaredRoles() as $role) {
            self::generateHostRoleEventClass($role . '_Add');
            self::generateHostRoleEventClass($role . '_Remove');
        }
    }

    /**
     * @param string $class
     * @return void
     */
    protected static function generateHostRoleEventClass($class) {
        if (class_exists($class)) {
            return;
        }

        $template = <<<EOF
<?php

class {$class} extends \_OS\Event {
    public \$host = null;
    
    public function __construct(\$host) {
        \$this->host = \$host;
    }
}

EOF;
        file_put_contents(PATH_TMP . "/{$class}.php", $template);
    }
}
