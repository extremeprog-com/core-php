<?php

class Deploy {
    const REPOS_CONFIG = '/repos/_config';
    
    static function deployBase($user_host) {

        $from_scratch = getenv('from_scratch')?'from_scratch=1 ':'';

        $Project = Project::get($project);

        // проверяем коннект к базе
        passthru("ssh -x -o StrictHostKeyChecking=no {$Project->base} 'echo connected to base'", $retval);

        if($retval != 0) {
            throw new Exception("ssh {$Project->base} returned $retval for project \"{$Project->name}\"");
        }

        // копируем .projectsrc
        passthru("rsync ~/_core/.projectsrc {$Project->base}:./", $retval);
        passthru("ssh -x -o StrictHostKeyChecking=no {$Project->base} 'grep projectsrc .bashrc || echo source ~/_core/.projectsrc >> .bashrc'");

        // получаем номер ревизии (инкрементим). если папочки проекта и project.env нет, создаём
        passthru("ssh -x {$Project->base} 'mkdir -p ~/{$Project->name} ~/{$Project->name}.env/{var,etc,bin} ~/{$Project->name}.data'", $retval);
        echo 'oldrev=';
        $oldrev = system("ssh -x {$Project->base} 'cd ~/{$Project->name}; for i in */; do rev=\$i; done; if [ \"\$i\" != \"*/\" ]; then echo -n \${i:0:-1}; fi '");
        echo "\n";
        $newrev = date("md") . (substr($oldrev, 0, 4) != date("md") ? '000' : substr(1000 + floor(substr($oldrev, 4))+1, 1));

        echo "newrev=$newrev\n";

        if($oldrev) {
            // если есть старые файлы, скопируем их в папку новой ревизии
            passthru("ssh -x {$Project->base} 'cp -r ~/{$Project->name}/$oldrev ~/{$Project->name}/$newrev'", $retval);
        } else {
            // если нет, просто создадим папку
            passthru("ssh -x {$Project->base} 'mkdir -p ~/{$Project->name}/$newrev'", $retval);
        }

        // делаем rsync в эту папку
        passthru("rsync -Rr --copy-links --delete --delete-excluded --exclude='tmp/*' --exclude='.git' --exclude='git' --exclude='.idea' --exclude='.gitignore'  --exclude='.gitmodules' --exclude='cowork/*' ./ {$Project->base}:{$Project->name}/$newrev");

        if($from_scratch) {
            // удаляем все папки с данными
            passthru("ssh -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; rm -rf \$PROJECTENV \$PROJECTDATA'", $retval);
        }

        // запустим ./init_configs с ролью base
        passthru("ssh -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; ROLES=\"BaseHost\" php core/_OS/Core/generate_maps.php'", $retval);
        passthru("ssh -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; ROLES=\"BaseHost\" php-r \"System::InitFiles();\"'", $retval);
        passthru("ssh -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; ROLES=\"BaseHost\" php core/_OS/Core/generate_maps.php'", $retval);
        passthru("ssh -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; ROLES=\"BaseHost\" php-r \"System::InitFiles();\"'", $retval);
        passthru("ssh -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; ROLES=\"BaseHost\" php core/_OS/Core/generate_maps.php'", $retval);

        if(Project::getConfigKey($project, 'multirole')) {
            passthru("ssh -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; ROLES=\"BaseHost\" php core/_OS/Core/init_configs.php'", $retval);
        }

        // запушим всё это в git-ветку для контроля выкладок
//        $email = trim(`/usr/bin/git config --global user.email`);
//        $name  = trim(`/usr/bin/git config --global user.name`);
//        $repo  = trim(`grep 'url = ' .git/config | grep -oE '[a-z.]*@.*'`);
//        passthru("ssh-agent bash -c \"ssh-add; ssh -o ForwardAgent=yes -x {$Project->base} 'source ~/_core/.projectsrc; cd {$Project->name} && git fetch $repo || git clone $repo ~/{$Project->name}.git; cdproject {$Project->name} $newrev ; ln -s ~/{$Project->name}.git/.git .git; /usr/bin/git config --global user.email $email; /usr/bin/git config --global user.name \\\"$name\\\"; /usr/bin/git add -A && /usr/bin/git commit -a -m \\\"deploy revision $newrev\\\" && git push'\"", $retval);

        // ищем сигнальный файл
        passthru("ssh-agent bash -c \"ssh-add; ssh -o ForwardAgent=yes -x {$Project->base} 'test -e ~/{$Project->name}/project-revision'\"", $retval);

        if ($retval !== 0) {
            // сигнального файла нет - значит, это первая установка. создадим taskman.conf

            passthru("ssh-agent bash -c \"ssh-add; ssh -o ForwardAgent=yes -x {$Project->base} 'test -e ~/{$Project->name}/project-revision'\"", $retval);
            if(strpos(__PROJECT__,'_cluster') !== false) {
                // кластер - развернем etcd и moosefs

                passthru("ssh-agent bash -c \"ssh-add; ssh -o ForwardAgent=yes -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; curl https://discovery.etcd.io/new > \\\$PROJECTDATA/etcd-discovery.url; ROLES=\\\"EtcdServerHostRole MooseFSMasterServerHostRole MooseFSChunkServerHostRole\\\" php-r \\\"System::InitConfigs(); System::initDaemons(); EtcdServerHostRole::addToHost(Taskman::getHostname()); MooseFSMasterServerHostRole::addToHost(Taskman::getHostname()); MooseFSChunkServerHostRole::addToHost(Taskman::getHostname()); ClusterAPIFrontendNginxBalancer::addToHost(Taskman::getHostname()); ClusterAPIFrontendNginxApplication::addToHost(Taskman::getHostname());\\\"' \"", $retval);
            } else {
                $retval = 0;
            }
        }

        // выкладываемся с помощью nodejs на все перечисленные хосты
        passthru("ssh-agent bash -c \"ssh-add; ssh -o ForwardAgent=yes -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name} $newrev; ROLES=\\\"BaseHost\\\" $from_scratch node-runner core/_OS/Deploy/Deploy.js'\"", $retval);

//        if($retval === 0) {
//            // создадим project-revision
//            passthru("ssh -x {$Project->base} 'echo -n $newrev > ~/{$Project->name}/project-revision'", $retval);
//        }

        // выложим скрипты мониторинга в _cluster
//        self::deployMonitor($project);
    }

    /**
     * @param string $host
     * @param array $roles
     * @throws Exception
     */
    static function deployFromBase($host, $roles = null) {
        if(!$roles) {
            $roles = HostRole::getRolesByHost($host);
        }
        sort($roles);
        echo "ROLES=\"".implode(" ", $roles)."\"\n";

        // проверяем коннект к хосту
        passthru("ssh -x -o StrictHostKeyChecking=no $host 'echo connected to $host'", $retval);
        passthru("ssh -x -o StrictHostKeyChecking=no $host 'grep projectsrc .bashrc || echo source ~/_core/.projectsrc >> .bashrc'");

        if($retval != 0) {
            throw new Exception("ssh $host returned $retval for project \"".PROJECT."\"");
        }

        // копируем .projectsrc
        passthru("rsync ~/_core/.projectsrc $host:./", $retval);

        passthru("ssh -x $host 'mkdir -p ~/".PROJECT." ~/".PROJECT.".env ~/".PROJECT.".data ~/".PROJECT.".log'", $retval);
        passthru("ssh -x $host 'mkdir -p ~/".PROJECT.".env/var/ ~/".PROJECT.".env/etc/ ~/".PROJECT.".env/etc/nginx'", $retval);

        // получаем номер ревизии (инкрементим). если папочки проекта и project.env нет, создаём
        echo "oldrev=";
        $oldrev = system("ssh -x $host 'cd ~/".PROJECT."; for i in */; do rev=\$i; done; if [ \"\$i\" != \"*/\" ]; then echo -n \${i:0:-1}; fi '");
        echo "\n";
        $newrev = REVISION;
        echo "newrev=$newrev";
        echo "\n";

        if($oldrev) {
            if($newrev != $oldrev) {
                // если есть старые файлы, скопируем их в папку новой ревизии
                passthru("ssh -x $host 'cp -r ~/".PROJECT."/$oldrev ~/".PROJECT."/$newrev'", $retval);
            }
        } else {
            // если нет, просто создадим папку
            passthru("ssh -x $host 'mkdir -p ~/".PROJECT."/$newrev'", $retval);
        }
        // делаем rsync в эту папку
        passthru("rsync -Rr --delete ./ $host:".PROJECT."/$newrev");

        // копируем файл с ролями
        passthru("rsync " . PROJECTDATA . "/* $host:".PROJECT.".data/");
        
        // копируем название своего хоста, как в конфиге
        passthru("ssh -x $host 'source ~/_core/.projectsrc; cdproject ".PROJECT." $newrev; echo -n $host > \$PROJECTENV/my.host'", $retval);

        if(getopt('', ['from_scratch'])) {
            // удаляем все папки с данными
            passthru("ssh -x $host 'source ~/_core/.projectsrc; cdproject $host $newrev; rm -rf \$PROJECTENV \$PROJECTDATA'", $retval);
        }
        // сохраним роли и запустим ./init_configs
        passthru("ssh -x $host 'source ~/_core/.projectsrc; cdproject ".PROJECT." $newrev; ROLES=\"".implode(" ", $roles)."\" php-r \"System::InitConfigs();\"'", $retval);
        $retval === 0 && passthru("ssh -x $host 'source ~/_core/.projectsrc; cdproject ".PROJECT." $newrev; ROLES=\"".implode(" ", $roles)."\" php-r \"System::InitDaemons();\"'", $retval);

        // создадим project-revision
        $retval === 0 && passthru("ssh -x $host 'echo -n $newrev > ~/".PROJECT."/project-revision'", $retval);

        // скопируем к себе ports config
        $retval === 0 && passthru("rsync $host:".PATH_DATA."/ports-$host.json ".PATH_DATA."/ports-$host.json");

    }

    /**
     * @param string $host
     * @param array $roles
     */
    static function initData($host, $roles = null) {
        if(!$roles) {
            $roles = HostRole::getRolesByHost($host);
        }
        passthru("rsync ".PATH_DATA."/* $host:".PATH_DATA."/");
        passthru("ssh -x $host 'source ~/_core/.projectsrc; cdproject ".PROJECT."; ROLES=\"".implode(" ", $roles)."\" php-r \"System::InitData();\"'", $retval);
    }

    /**
     * @param string $string
     */
    static function deployFile($string) {
        list($project, $files) = explode(" ", $string, 2);
        $files       = explode(" ", $files);
        $Project     = Project::get($project);
        // Мы не можем получить хосты через метод, потому что там юзается
        // глобальный конфиг и возращается массив с локалхостами при мультироли
        $roles2hosts = JSON::decode(`ssh -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name}; php-r "echo JSON::encode(HostRole::getRoles2Hosts())"'`);

        $hosts = [];
        foreach($roles2hosts as $hs) {
            foreach($hs as $host) {
                if(!in_array($host, $hosts)) {
                    $hosts[] = $host;
                }
            }
        }
        $rev = trim(`ssh {$Project->base} 'cat {$Project->name}/project-revision'`);

        list($user) = explode("@", $Project->base);

        foreach($files as $file) {
            foreach($hosts as $host) {
                $host = strtr($host, ["dev0" => "dev"]);
                $cmd = "scp $file $user@$host:$project/$rev/$file";
                echo $cmd."\n";
                passthru($cmd);
            }
        }
        // запушим всё это в git-ветку для контроля выкладок
        $email = trim(`/usr/bin/git config --global user.email`);
        $name  = trim(`/usr/bin/git config --global user.name`);
        passthru("ssh-agent bash -c \"ssh-add; ssh -o ForwardAgent=yes -x {$Project->base} 'source ~/_core/.projectsrc; cdproject {$Project->name}; ln -s ~/{$Project->name}.git/.git .git; /usr/bin/git config --global user.email $email; /usr/bin/git config --global user.name \\\"$name\\\"; /usr/bin/git add -A; /usr/bin/git commit -a -m \\\"update files\\\"; git push'\"", $retval);
    }

    /**
     * @param string $project
     */
    public static function deployMonitor($project) {
        $config  = Project::getConfig($project);

        // Если указан мониторинг сервер, деплоим скрипты
        if (isset($config['monitor'])) {
            // Скрипты мониторинга
            // Обрабатываем шаблонные скрипты и переносим в папочку данных
            foreach(glob(PROJECTPATH . '/monitor/*') as $file) {
                echo 'Install monitor script ' . $file . PHP_EOL;
                passthru("chmod +x $file");
                passthru("scp $file {$config['monitor']}:_clusterprod.data/monitor/" . $project . '-' . basename($file));
            }
        }

        // Если существует конфиг адресатов нотификаций, то деплоим его на все нотификационные сервисы
        $config_file = PROJECTPATH . '/domain/monitor.json';
        if (isset($config['notifiers']) && file_exists($config_file)) {
            $config['notifiers'] = (array) $config['notifiers'];
            foreach ($config['notifiers'] as $notifier) {
                passthru("scp $config_file $notifier:_clusterprod.data/delivery/" . $project . ".json");
            }
        }
    }
}