<?php

abstract class NginxFrontend {
    static $_jslog_enable = false;

    static function getSiteNames() {
        throw new Exception('You need to create method '.get_called_class().'::'.__FUNCTION__);
    }

    static function generateEvents() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        foreach(['MakeConfig', 'Request', 'Reconfigure'] as $event) {
            $class = static::class . "_" . $event;
            $base  = __CLASS__     . "_" . $event;
            if (!class_exists($base)) {
                $base = '\_OS\Event';
            }
            if(file_exists(PATH_WORKDIR."/tmp/$class.php") || !class_exists($class)) {
                file_put_contents(PATH_WORKDIR."/tmp/$class.php",
                    <<<EOF
<?php

class $class extends $base {

}

EOF
                );
            }
        }
    }

    static function generateRoles() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        foreach(['NginxBalancer', 'NginxApplication'] as $role) {
            $class = static::class.$role;
            file_put_contents(PATH_WORKDIR."/tmp/$class.php",
                <<<EOF
<?php

class $class extends HostRole {

}


EOF
            );
        }
    }


    /**
     * Конфигурируем балансер на всех хостах для удоблного переключения
     */
    public static function configureNginxBalancer() {
        CatchEvent([static::class.'NginxApplication' => \_OS\Core\System_InitConfigs::class], [static::class . '_Reconfigure']);

        $NginxConfig   = new NginxConfig();
        $upstreams     = HostRole::getRoleHosts(static::class . 'NginxApplication', false, true);
        $upstream_name = PROJECTUSER . '-' . PROJECT . '-' . static::class . 'NginxApplication';

        $NginxConfig->definitions['upstream'] = "
            upstream $upstream_name {
        ";

        foreach($upstreams as $upstream) {
            $NginxConfig->definitions['upstream'] .= "
                server $upstream:80 max_fails=5 fail_timeout=10s;
            ";
        }

        $NginxConfig->definitions['upstream'] .= "
            }
        ";

        $rev = REVISION ?: "000";

        $NginxConfig->sections['location /'] = "
            location / {
                proxy_pass http://$upstream_name;
                proxy_set_header Host $rev.\$http_host;

                set \$origin_host        \$http_origin_host;
                set \$origin_server_name \$http_origin_server_name;
                set \$origin_remote_addr \$http_origin_remote_addr;

                if ( \$origin_host = '') {
                    set \$origin_host \$http_host;
                }
                if ( \$origin_server_name = '' ) {
                    set \$origin_server_name \$server_name;
                }
                if ( \$origin_remote_addr = '' ) {
                    set \$origin_remote_addr \$remote_addr;
                }

                proxy_set_header Origin-Host        \$origin_host;
                proxy_set_header Origin-Server-Name \$origin_server_name;
                proxy_set_header Origin-Remote-Addr \$origin_remote_addr;

                proxy_http_version 1.1;
                proxy_set_header Upgrade \$http_upgrade;
                proxy_set_header Connection \"upgrade\";

            }
            
            location = /nginx-ping {
                add_header Content-type text/html;
                return 200 OK;
            }
        ";

        $sites = static::getSiteNames();

        $nginx_config = strtr(file_get_contents(__DIR__ . "/nginx.conf.template"), array(
            '%PROJECT%'             => PROJECT,
            '%ROLE%'                => static::class . '-balancer',
            '%PROJECTPATH%'         => PATH_WORKDIR,
            '%PATH_ENV%'            => PATH_ENV,
            '%PATH_LOG%'            => PATH_LOG,
            '%__DIR__%'             => __DIR__,
            '%Ymd%'                 => date("Ymd"),
            '%USER%'                => PROJECTUSER,
            '%DEFINITIONS%'         => implode("\n", $NginxConfig->definitions),
            '%SECTIONS%'            => implode("\n", $NginxConfig->sections),
            '%SERVER_NAME%'         => implode(" ", array_map(function ($domain) { return idn_to_ascii($domain); }, $sites)),
        ));

        $nginx_config = strtr($nginx_config, array(
            '%PHP_FPM%'             => ''
        ));

        file_put_contents(PATH_ENV . '/etc/nginx/nginx-' . static::class . 'Balancer.conf', static::formatConfig($nginx_config));
//        Taskman::installDaemonUnderTaskman('nginx-logger',         './php-r "Log::fromStdin(null, [\\"source\\" => \\"nginx\\"     , \\"type\\" => \\"info\\"],  \\"$PROJECTLOG/nginx-access.log\\");"');
//        Taskman::installDaemonUnderTaskman('nginx-logger-error',   './php-r "Log::fromStdin(null, [\\"source\\" => \\"nginx\\"     , \\"type\\" => \\"error\\"], \\"$PROJECTLOG/'.date("Ymd").'-error-nginx.log\\");"');

    }


    public static function configureNginxApplication() {
        CatchEvent([static::class.'NginxApplication' => \_OS\Core\System_InitConfigs::class], [static::class . '_Reconfigure']);

        passthru('mkdir -p $PROJECTENV/etc/php $PROJECTENV/etc/nginx');

        $NginxConfig = new NginxConfig();

        $NginxConfig->sections['location /core'] = "
            location /core {
                root ".PATH_WORKDIR.";
            }
        ";

        $NginxConfig->sections['location /favicon.ico'] = "
            location = /favicon.ico {
                root ".__DIR__.";
            }
        ";

        if(static::$_jslog_enable) {
            $NginxConfig->sections['location /_jslog'] = "
            location /_jsLog {
                set \$fpm_request_type    CMD;
                set \$fpm_request_handler Log::jsLog;
                fastcgi_pass \$php_fpm;
            }
        ";

        }

        $event_class = static::class.'_MakeConfig';

        FireEvent(new $event_class($NginxConfig));

        $rev = REVISION ?: "000";

        $sites = static::getSiteNames();

        $nginx_config = strtr(file_get_contents(__DIR__."/nginx.conf.template"), array(
            '%PROJECT%'             => PROJECT,
            '%ROLE%'                => static::class . '-' . $rev,
            '%PROJECTPATH%'         => PATH_WORKDIR,
            '%PATH_ENV%'            => PATH_ENV,
            '%PATH_LOG%'            => PATH_LOG,
            '%__DIR__%'             => __DIR__,
            '%Ymd%'                 => date("Ymd"),
            '%USER%'                => posix_getpwuid(posix_geteuid())["name"],
            '%DEFINITIONS%'         => implode("\n", $NginxConfig->definitions),
            '%SECTIONS%'            => implode("\n", $NginxConfig->sections),
            '%SERVER_NAME%'         => implode(" ", array_map(function($domain) use ($rev) { return $rev.".".idn_to_ascii($domain);}, $sites)),
        ));

        $nginx_config = strtr($nginx_config, array(
            '%PHP_FPM%'             => '
                set $fpm_script_name ' . __DIR__ . '/frontend.php;
                fastcgi_param   QUERY_STRING            $query_string;
                fastcgi_param   REQUEST_METHOD          $request_method;
                fastcgi_param   CONTENT_TYPE            $content_type;
                fastcgi_param   CONTENT_LENGTH          $content_length;
                fastcgi_param   SCRIPT_FILENAME         $request_filename;
                fastcgi_param   SCRIPT_NAME             $fastcgi_script_name;
                fastcgi_param   REQUEST_URI             $request_uri;
                fastcgi_param   DOCUMENT_URI            $document_uri;
                fastcgi_param   DOCUMENT_ROOT           $document_root;
                fastcgi_param   SERVER_PROTOCOL         $server_protocol;
                fastcgi_param   GATEWAY_INTERFACE       CGI/1.1;
                fastcgi_param   SERVER_SOFTWARE         nginx/$nginx_version;
                fastcgi_param   REMOTE_ADDR             $http_origin_remote_addr;
                fastcgi_param   REMOTE_PORT             $remote_port;
                fastcgi_param   SERVER_ADDR             $server_addr;
                fastcgi_param   SERVER_PORT             $server_port;
                fastcgi_param   SERVER_NAME             $server_name;
                fastcgi_param   HTTP_HOST               $http_origin_host;
                fastcgi_param   HTTPS                   $https;

                fastcgi_param   PROJECTPATH             ' . PROJECTPATH . ';
                fastcgi_param   FRONTEND                ' . static::class . ';
                fastcgi_param   TYPE                    $fpm_request_type;
                fastcgi_param   HANDLER                 $fpm_request_handler;

                # PHP only, required if PHP was built with --enable-force-cgi-redirect
                fastcgi_param   REDIRECT_STATUS         200;
                fastcgi_param   SCRIPT_FILENAME         $fpm_script_name;
                fastcgi_param   SCRIPT_NAME             $fpm_script_name;

                set $php_fpm unix:'.PATH_ENV.'/var/php-fpm.sock;
            '
        ));

        file_put_contents(PATH_ENV.'/etc/nginx/nginx-'.static::class.'.'.$rev.'.conf', self::formatConfig($nginx_config));
    }

    static function formatConfig($nginx_config) {
        $level = 1;
        $formatted_config = [];

        foreach(explode("\n", $nginx_config) as $line) {
            $line = trim($line);
            if(strpos($line, '}') !== false) $level--;
            $formatted_config[] = str_pad("", 4*$level, " ").$line;
            if(strpos($line, '{') !== false) $level++;
        }

        return join("\n", $formatted_config);
    }

    static function configureFpm() {
        CatchEvent([static::class.'NginxApplication' => \_OS\Core\System_InitConfigs::class]);

        $sites = static::getSiteNames();

        $fpm_config = strtr(file_get_contents(__DIR__."/php-fpm.conf.template"), array(
            '%PROJECTPATH%'         => PATH_WORKDIR,
            '%PATH_ENV%'            => PATH_ENV,
            '%PATH_LOG%'            => PATH_LOG,
            '%__DIR__%'             => __DIR__,
            '%Ymd%'                 => date("Ymd"),
            '%SERVER_NAME%'         => implode(" ", array_map(function($domain){ return idn_to_ascii($domain); }, $sites)),
        ));

        `mkdir -p \$PROJECTENV/etc/php/`;
        file_put_contents(PATH_ENV."/etc/php/php-fpm.conf", $fpm_config);

        $fpm_config = strtr(file_get_contents(__DIR__."/php-fpm.pool.conf.template"), array(
            '%PROJECT%'             => getenv('PROJECT'),
            '%PROJECTENV%'          => getenv('PROJECTENV'),
            '%PROJECTLOG%'          => getenv('PROJECTLOG'),
            '%PROJECTPATH%'         => getenv('PROJECTPATH'),
            '%PROJECTREV%'          => getenv('PROJECTREV'),
            '%REVCOMMENT%'          => getenv('PROJECTREV')?'':';',
            '%PATH%'                => getenv('PATH'),
            '%PATH_ENV%'            => PATH_ENV,
            '%PATH_LOG%'            => PATH_LOG,
            '%get_current_user()%'  => get_current_user(),
            '%get_current_group()%' => '',
            '%__DIR__%'             => __DIR__,
            '%SERVER_NAME%'         => implode(" ", array_map(function($domain){ return idn_to_ascii($domain);}, $sites)),
        ));

        file_put_contents(PATH_ENV."/etc/php/php-fpm.pool.conf", $fpm_config);

        $fpm_config = strtr(file_get_contents(__DIR__."/php.ini.template"), array(
            '%PROJECTPATH%'         => PATH_WORKDIR,
            '%PROJECTENV%'          => PATH_ENV,
            '%PATH_ENV%'            => PATH_ENV,
            '%PATH_LOG%'            => PATH_LOG,
            '%__DIR__%'             => __DIR__,
            '%SERVER_NAME%'         => implode(" ", array_map(function($domain){ return idn_to_ascii($domain);}, $sites)),
        ));

        file_put_contents(PATH_ENV."/etc/php/php.ini", $fpm_config);

        passthru('killall -HUP php5-fpm -u $USER');
    }


    static function restartNginx() {
        CatchEvent([static::class.'NginxApplication' => \_OS\Core\System_InitDaemons::class], [static::class.'NginxBalancer' => \_OS\Core\System_InitDaemons::class], [static::class . 'NginxApplication' => static::class . '_Reconfigure']);

        _OSTask::run_inline(array(
            'is_possible' => function(Context $Ctx) {
                passthru('/usr/sbin/nginx -v', $ret);
                return $ret == 0;
            },
            'make_possible' => function(Context $Ctx) {
                passthru('echo PLEASE, INSTALL nginx AND MAKE POSSIBLE sudo /usr/sbin/nginx TO DEPLOY SCRIPT; sleep 60', $ret);
            },
            'been_run' => function(Context $Ctx) {
                return isset($Ctx->been_run);
            },
            'run' => function(Context $Ctx) {
                if (file_exists('/etc/init.d/nginx')) {
                    passthru('sudo /etc/init.d/nginx configtest', $ret);

                    if ($ret == 0) {
                        passthru('sudo /etc/init.d/nginx status || sudo /etc/init.d/nginx start ; sudo /etc/init.d/nginx reload');
                    } else {
                        throw new Exception("Nginx test failed");
                    }
                    $Ctx->been_run = true;
                } else {
                    passthru('sudo /usr/sbin/nginx -t', $ret);

                    if ($ret == 0) {
                        passthru('sudo /usr/sbin/nginx -s reload || sudo /usr/sbin/nginx');
                    } else {
                        throw new Exception("Nginx test failed");
                    }
                    $Ctx->been_run = true;
                }
            }
        ));
    }

    static function restartForChangeLog() {
        Taskman::installCronTask("00 00 * * * source ~/.projectsrc; cdproject ".__PROJECT__."; ./init_configs", __METHOD__);
    }

    static function installPhpFpm() {
        CatchEvent([static::class.'NginxApplication' => \_OS\Core\System_InitDaemons::class]);
        Taskman::installDaemonUnderTaskman('php-fpm', '/usr/sbin/php5-fpm -F -g $PROJECTENV/var/php-fpm.pid -y $PROJECTENV/etc/php/php-fpm.conf');
        Taskman::installDaemonUnderTaskman('php-fpm-logger-error', './php-r "Log::fromStdin(null, [\\"source\\" => \\"fpm-error\\" , \\"type\\" => \\"error\\"], \\"$PROJECTLOG/'.date("Ymd").'-info-fpm.log\\", \\"\n\n\\");"');
        Taskman::installDaemonUnderTaskman('php-fpm-logger-slow',  './php-r "Log::fromStdin(null, [\\"source\\" => \\"fpm-slow\\"  , \\"type\\" => \\"warn\\" ], \\"$PROJECTLOG/fpm-slow.log\\");"');
    }

    static function handleRequest() {
        $requestClass = static::class."_Request";

        /** @var NginxFrontend_Request $Request */
        $Request = new $requestClass();
        $Request->host   = $_SERVER['HTTP_HOST'];
        $Request->uri    = $_SERVER['DOCUMENT_URI'];
        $Request->method = $_SERVER['REQUEST_METHOD'];
        foreach ($_FILES as $key => $file) {
            $Request->upload[$key] = $file['tmp_name'];
        }

        $Request->_GET     = $_GET;
        $Request->_POST    = $_POST;
        $Request->params   = $_POST + $_GET;
        $Request->raw_post = file_get_contents('php://input');

        if(isset($_SERVER['TYPE'], $_SERVER['HANDLER'])) {
            $Request->type    = $_SERVER['TYPE'];
            $Request->handler = $_SERVER['HANDLER'];
        } elseif (isset($_SERVER['DOCUMENT_URI']) && file_exists($file = $_SERVER['DOCUMENT_ROOT'] . "/" . $_SERVER['DOCUMENT_URI'])) {
            $Request->type = 'FILE';
            $Request->handler = $file;
        } else {
            throw new Exception("Don't know how to handle request, globals: " . JSON::hencode($GLOBALS));
        }
        $Context = new WebContext;
        $Context->Request = $Request;
        $Context->run(function () use($Request) {
            try {
                WorkSession::start();
                $result = FireRequest($Request);
                if(is_string($result)) {
                    echo $result;
                } else {
                    header('Content-type: text/json;charset=utf-8');
                    echo JSON::hencode($result);
                }
            } finally {
                WorkSession::end();
            }
        });
    }

    static function handlePhpCmd() {
        $Request = CatchRequest([static::class.'_Request' => ['type' => 'PHP_CMD']]); /** @var NginxFrontend_Request $Request */
        call_user_func($Request->handler, $Request);
        return true;
    }

    static function handlePhpFile() {
        $Request = CatchRequest([static::class.'_Request' => ['type' => 'PHP_FILE']]); /** @var NginxFrontend_Request $Request */
        require_once $Request->handler;
        return true;
    }

}