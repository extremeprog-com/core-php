<?php

trait API {

    /*
     *
     * You must define $filter_fields in your child class
     *
     * static $filter_fields = ['Session', 'version'];
     *
     */

    static function publishAPIUrl() {
        $Event = CatchEvent(__CLASS__."_MakeConfig");

        $Event->NginxConfig->sections['headers'] = "
            add_header Access-Control-Allow-Origin *;
            add_header Access-Control-Allow-Headers \"Origin, X-Requested-With, Content-Type, Accept\";
        ";

        $Event->NginxConfig->sections['location /api/'] = "
            location /api/ {
                fastcgi_pass \$php_fpm;
            }
        ";
    }

    static function getObjectsAPIRequest() {
        /** @var NginxFrontend_Request $Request */
        $Request = CatchRequest([static::class."_Request" => ['uri' => '/api/_get']]);

        $result = [];

        foreach($Request->params as $key => $val) {
            if(is_object($val) && class_uses($val)[DataModel::class]) {
                if(check::loadable()->check($val)) {
                    $result[$key] = $val;
                } else {
                    $result[$key] = null;
                }
            } else {
                $result[$key] = $val;
            }
        }

        $API_Success = new API_Success($result);

        $securityCheckClass = static::class."_SecurityCheck";

        $result = [];

        // Load
//        static::loadObjects($fields, $result, array_map('trim', explode(',', $Request->param('_recurse'))));
        
        foreach($result as $Object) {
            $SecurityRequest = new $securityCheckClass($Object);
            $SecurityRequest->WebRequest = $Request;
            if(FireRequest($SecurityRequest)) {
                $Request->result[] = $Object;
            }
        }

        $Request->addObjectToResult($API_Success);

        FireEvent($API_Success);
    }


    public static function loadObjects(array $fields, array &$result, $recurse = ['*'], $prefix = '') {
        static $cache = [];
        foreach ($fields as $key => $item) {
            try {

                if (!in_array(gettype($item), ['array', 'string', 'object'])) {
                    continue;
                }


                if (is_array($item)) {
                    $cur_key = ($prefix ? $prefix . '.' : '') . $key;
                    return static::loadObjects($item, $result, $recurse, $cur_key);
                }

                if (is_object($item) && isset(class_uses($item)['DataModel'])) {
                    $Object = $item;
                    $Object->_load();
                    if (!isset($cache[$Object->getSelf()])) {
                        $result[] = $Object;
                        $cache[$Object->getSelf()] = 1;
                    }


                    foreach ($Object->getData() as $k => $val) {
                        if (preg_match('|^[A-Z]|s', $k) && $val) {
                            $cur_key = ($prefix ? $prefix . '.' : '') . $k;
                            if ($recurse === ['*'] || in_array($cur_key, $recurse)) {
                                static::loadObjects(
                                    array_map(
                                        function ($self) {
                                            return FireRequest(new DataModel_ResolveObject($self));
                                        },
                                        is_string($val) ? [$val] : $val
                                    ),
                                    $result,
                                    $recurse,
                                    $cur_key
                                );
                            }
                        }
                    }
                }


            } catch(Exception $e) {

                Log::error($e);

            }

        }
    }

    static function save() {
        /** @var NginxFrontend_Request $Request */
        $Request = CatchRequest([DataXAPI_Request::class => ['uri' => '/api/_save' ]]);

        try {
            $input = self::deserializeProtocol($Request->raw_post?:$Request->param('_raw'));

            foreach($input as $Object) {
                FireRequest(new DataModel_SaveRequest($Object));
            }

        } catch (Exception $E) {
            $event_class = isset($Object) && $Object instanceof \_OS\Request ? get_class($Object) . '_Fail' : API_Fail::class;
            $Event = new $event_class;
//            if (isset($Object->_reqid)) {
//                $Event->_reqid = $Object->_reqid;
//            }
            $Event->errmsg = $E->getMessage();
            $Event->trace = explode(PHP_EOL, $E->getTraceAsString());
            // Double event on fail event
//            if ($Object instanceof \_OS\Event) {
//                FireEvent($Event);
//            }
            $Request->addObjectToResult($Event);
        }

        $API_Success = new API_Success();
        $API_Success->Saved = $input;

        $securityCheckClass = static::class."_SecurityCheck";

        $result = [];

        foreach($result as $Object) {
            $SecurityRequest = new $securityCheckClass($Object);
            $SecurityRequest->WebRequest = $Request;
            if(FireRequest($SecurityRequest)) {
                $Request->result[] = $Object;
            }
        }

        $Request->addObjectToResult($API_Success);

        FireEvent($API_Success);
    }

    public static function call() {
        /**  @var NginxFrontend_Request $Request */
        $Request = CatchRequest([static::class . "_Request" => ['uri' => '/api/_call']]);

        try {
            $input = self::deserializeProtocol(file_get_contents('php://input'));

            foreach ($input as $Object) {
                if ($Object instanceof \_OS\Request) {
                    FireRequest($Object);
                }
                if ($Object instanceof \_OS\Event) {
                    FireEvent($Object);
                }
            }

        } catch (Exception $E) {
            $event_class = isset($Object) && $Object instanceof \_OS\Request ? get_class($Object) . '_Fail' : API_Fail::class;
            $Event = new $event_class;
            if (isset($Object->_reqid)) {
                $Event->_reqid = $Object->_reqid;
            }
            $Event->errmsg = $E->getMessage();
            $Event->trace = explode(PHP_EOL, $E->getTraceAsString());
            // Double event on fail event
            if ($Object instanceof \_OS\Event) {
                FireEvent($Event);
            }
            $Request->addObjectToResult($Event);
        } finally {
            foreach(WorkSession::get()->resources['actions'] as $Object) {
                if ($Object instanceof \_OS\Event) {
                    $Request->addObjectToResult($Object);
                }
            }

            // Log input request
            // Display: cat /home/dk/billing.log/20141208-info-api-access.log | xargs -l echo -e 
            $log_message = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1')
                         . ' ' . $_SERVER['HTTP_HOST']
                         . ' "' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' ' . $_SERVER['SERVER_PROTOCOL'] . '"'
                         . ' "' . $_SERVER['HTTP_USER_AGENT'] . '"'
                         . ' INPUT: ' . file_get_contents('php://input')
                         . ' RESPONSE: ' . json_encode($Request->result, JSON_UNESCAPED_UNICODE)
                         . PHP_EOL

            ;
            Logger()->logToFilename('api-access.log', $log_message);
        }
    }

    static function deserializeProtocol($input) {

        if(is_string($input)) {
            $input =  json_decode($input, true);
        }

        foreach ($input as $key => $params) {
            if (isset($params['_request'])) {
                $request_class = $params['_request'];
                if (!class_exists($request_class)) {
                    throw new Exception('Request ' . $request_class . ' does not exist');
                }

                $R = unserialize('O:'.strlen($request_class).':"'.$request_class.'":1:{s:6:"_reqid";s:'.strlen($params['_reqid']).':"'.$params['_reqid'].'";}');
                $R->params = $params;

                $input[$key] = $R;
            }
            if (isset($params['_event'])) {
                $event_class = $params['_event'];
                if (!class_exists($event_class)) {
                    throw new Exception('Event ' . $event_class . ' does not exist');
                }

                $E = unserialize('O:'.strlen($event_class).':"'.$event_class.'":0:{}');

                foreach(get_class_vars($event_class) as $var => $default ) {
                    if (!array_key_exists($var, $params)) {
                        throw new Exception('Expected field ' . $var . ' not found');
                    }
                    if (preg_match('|^[A-Z]|is', $var)) {
                        $params[$var] = FireRequest(new DataModel_ResolveObject($params[$var]));
                        $params[$var]->_load();
                    }

                    $E->$var = $params[$var];
                }

                $input[$key] = $E;
            }
            if (isset($params['_class']) && class_exists($params['_class'])) {
                $class = $params['_class'];
                if (!class_exists($class)) {
                    throw new Exception('Class ' . $class . ' does not exist');
                }

                if(isset($params['_self'])) {
                    $Object = FireRequest(new DataModel_ResolveObject($params['_self']));
                    $Object->_load();
                } else {
                    $Object = $class::create();
                }

                foreach($class::$fields as $field => $default) {
                    if(isset($params[$field])) {
                        $value = $params[$field];
                        $Object->set($field, $value);
                    }
                }

                $input[$key] = $Object;
            }
        }

        return $input;
    }
    
    static function generateSecurityCheckRequest() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        $class = static::class."_SecurityCheck";
        $base  = __TRAIT__ ."_SecurityCheck";

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