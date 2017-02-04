<?php

define('TASKMAN_CONFIG_FILE', getenv('PROJECTENV') . "/etc/taskman.conf");
class Taskman
{

    const port = 4545;

    const CONFIG_FILE = TASKMAN_CONFIG_FILE;
    
    public static function _initConfigFile() {
        CatchEvent(\_OS\Core\System_InitFiles::class);
        
        $taskman_file = Taskman::CONFIG_FILE;
        `mkdir -p \$PROJECTENV/etc`;        
        `touch $taskman_file`;
    }
    
    /**
     * Установка таскмана в крон
     */
    static function addTaskmanInCron()
    {
        CatchEvent(\_OS\Core\System_InitDaemons::class);
        self::installCronTask("* * * * * source ~/_core/.projectsrc; HOME=".getenv("HOME")." cdproject " . PROJECT . " " . PROJECTREV . "; HOME=".getenv("HOME")." /usr/bin/node " . PATH_WORKDIR . "/core/_OS/Taskman/Taskman.js &", __METHOD__);
#        self::installCronTask("* * * * * source ~/.projectsrc; cdproject " . PROJECT . "; node " . PATH_WORKDIR . "/core/_OS/Taskman/Taskman.js --config=".PATH_ENV."/etc/taskman.conf --log=" . PATH_LOG . "/taskman_daemon.log --vardir=".PATH_ENV."/var/taskman.conf", __METHOD__);
        for($i = 0; $i < 120; $i += 3) {
            if (file_exists(__PROJECTENV__."/var/taskman.pid")) {
                if(posix_kill(file_get_contents(__PROJECTENV__."/var/taskman.pid"), 0)) {
                    return ;
                }
            }
            sleep(3);
            echo date('[Y-m-d H:i:s]')." Waiting for taskman starts...\n";
        }
        throw new Exception("Error: Taskman not started for 2 minutes");
    }

    /**
     * Установка кроновой задачи
     *
     * @param string $task Задача для установки в крон
     * @param $id string Идентификатор задачи
     */
    public static function installCronTask($task, $id) {
        $id_sign        = '# ' . PROJECT . '/' . $id;
        $crontab        = `crontab -l`;
        $lines          = explode("\n", $crontab);
        $installed      = false;
        $install_string = $task . ' ' . $id_sign;
        $bash_installed = false;
        foreach ($lines as $i => $line) {
            if (false !== strpos($line, $id_sign)) {
                if (!$installed) {
                    $lines[$i] = ($lines[$i][0] == '#'?"#":'') . $install_string;
                    $installed = true;
                } else {
                    $lines[$i] = '';
                }
            }
            if (preg_match("/SHELL=/", $line)) {
                if (trim($line) == 'SHELL=/bin/bash') {
                    $bash_installed = true;
                }
            }
        }
        if (!$installed) {
            $lines[] = $install_string;
        }
        if (!$bash_installed) {
            array_unshift($lines, "SHELL=/bin/bash");
        }
        $file_contents = preg_replace("/\n+/", "\n", implode("\n", $lines) . "\n");
        if ($crontab != $file_contents) {
            `mkdir -p  \$PROJECTENV/var/`;
            $tmp_file = PATH_ENV . "/var/crontab.bak";
            FileAPI::put($tmp_file, $file_contents);
            passthru("crontab " . $tmp_file);
            unlink($tmp_file);
        }
    }


    /**
     * Install task to run under Taskman
     *
     * @param string $daemon_name
     * @param string $cmd
     * @param bool $wait Нужно ли ожидать запуска демона или нет
     * @return void
     */
    static function installDaemonUnderTaskman($daemon_name, $cmd, $wait = false)
    {
        $crontab = FileAPI::get(Taskman::CONFIG_FILE);
        $lines = explode("\n", $crontab);
        $installed = false;
        $install_string = '* \'' . $daemon_name . '\' ' . $cmd;
        foreach ($lines as $i => $line) {
            if (preg_match("/[ \t]\'" . $daemon_name . "\'[ \t]/", $line)) {
                if ($installed)
                    continue;

                $lines[$i] = ($line[0] == '#' ? '#' : '') . $install_string;
                $installed = true;
            }
        }
        if (!$installed)
            $lines[] = $install_string;

        natcasesort($lines);

        if ($crontab != implode("\n", $lines))
            FileAPI::put(Taskman::CONFIG_FILE, implode("\n", $lines));
        
        // @todo sleep until daemon starts
        if ($wait) {
            sleep(1);
        }
    }

    /**
     * Delete task from config file
     *
     * @static
     * @param string $daemon_name
     * @return void
     */
    static function deleteDaemonUnderTaskman($daemon_name)
    {
        $crontab = FileAPI::get(Taskman::CONFIG_FILE);
        $lines = explode("\n", $crontab);

        foreach ($lines as $i => $line) {
            if (0 === strpos($line, '* \'' . $daemon_name . '\'') || !$line)
                unset($lines[$i]);
        }

        if ($crontab != implode("\n", $lines))
            FileAPI::put(Taskman::CONFIG_FILE, implode("\n", $lines));
    }

    static function getRunningTasksByPattern($pattern)
    {
        $crontab = FileAPI::get(Taskman::CONFIG_FILE);
        $lines = explode("\n", $crontab);

        $tasks = [];

        foreach ($lines as $i => $line) {
            if (strpos($line, $pattern)) {
                list(,$tasks[]) = preg_split("/( |\t|')+/", $lines[$i], 3);
            }
        }
        return $tasks;
    }

    /**
     * Получение текущего хоста
     * @return	string		
     */
    public static function getHostname() {
        static $host = null;
        
        if (!isset($host)) {
            $config = Project::getConfig();
            $host = (isset($config['multirole']) && $config['multirole'])
                ? 'localhost'
                : (FileAPI::get(PATH_ENV . "/my.host")
                   ? FileAPI::get(PATH_ENV . "/my.host")
                   : gethostname());
        }
        
        return $host;
    }
    
    /**
     * Перенос порта с одной роли на другую на текущем хосте
     * @param	string	$role		Роль, которую переименовываем с сохранением порта
     * @param	string	$new_role	Новая роль
     * @return	bool				Результат выполнения
     */
    public static function renameRole($role, $new_role) {
        $name     = PROJECTUSER . '-' . PROJECT . "-" . $role. ':';
        $new_name = PROJECTUSER . '-' . PROJECT . "-" . $new_role . ':';
        
        $host = Taskman::getHostname();
        $ports = TaskmanPortEtcdKey::get();
        if (!isset($ports[$host])) {
            return false;
        }
        
        // Если такой порт имеется в конфиге
        if (isset($ports[$host][$name])) {
            $ports[$host][$new_name] = $ports[$host][$name];
            unset($ports[$host][$name]);
        }
        
        TaskmanPortEtcdKey::set($ports);

        return true;
    }
    
    /**
     * Получение порта для роли
     * @param	string	$role		Имя роли
     * @param	null	$instance	Инстанс
     * @param	string	$host		Хост, на котором получаем порт
     * @return	int				    Доступный порт
     */
    public static function getPortFor($role, $instance = null, $host = 'localhost') {
        $name = PROJECTUSER . '-' . PROJECT . "-" . $role.(!is_null($instance)?":".$instance:'');

        $port_file = PROJECTDATA . '/ports-'.Taskman::getHostname().".json";
        if($host == 'localhost' || $host == Taskman::getHostname() ) {
        
            $all_ports = [];
            
            foreach (glob("/home/*/*.data/ports-".Taskman::getHostname().".json") as $file) {
                $all_ports += FileAPI::getJSON($file);
            }

            if (!isset($all_ports[$name])) {
                $port = 44000;
                while (array_search($port, $all_ports)) $port++;

                $just_created = true;
            } else {
                $port = $all_ports[$name];
            }

            if (isset($just_created)) {
                $ports = FileAPI::getJSON($port_file);
                $ports[$name] = $port;
                FileAPI::putJSON($port_file, $ports);
            }

            if (Etcd::enabled() && ( !isset(TaskmanPortEtcdKey::get()[ Taskman::getHostname()]) || TaskmanPortEtcdKey::get()[ Taskman::getHostname() ] != FileAPI::getJSON($port_file) ) ) {
                TaskmanPortEtcdKey::modify(Taskman::getHostname(), FileAPI::getJSON($port_file));
            }
        } else {
            if (Etcd::enabled()) {
                $port = TaskmanPortEtcdKey::get()[$host][$name];
            } else {
                $port = FileAPI::getJSON(PROJECTDATA."/ports-$host.json")[$name];
            }
        }

        return $port;

    }

    static function prepareConfigs($templates_dir, $dest_dir, $vars = []) {
        $vars += [
            "%PROJECT%"          => PROJECT,
            "%PROJECTENV%"       => PROJECTENV,
            "%PROJECTLOG%"       => PROJECTLOG,
            "%PROJECTDATA%"      => PROJECTDATA,
            "%PROJECTUSER%"      => PROJECTUSER,
            "%HOSTNAME%"         => Taskman::getHostname(),
            "%HOST_LOCAL_IP%"    => gethostbyname('localhost.int'),
            "%Ymd%"              => date('Ymd'),
            "%HOST_EXTERNAL_IP%" => gethostbyname('localhost.ext'),
            "%HOST_INTERNAL_IP%" => gethostbyname('localhost.int'),
        ];
        `mkdir -p $dest_dir`;
        foreach(glob($templates_dir.'/*.template') as $file) {
            $filename = basename($file, ".template");
            FileAPI::put( $dest_dir . "/" . $filename, strtr(FileAPI::get($file), $vars) );
        }

    }

}
