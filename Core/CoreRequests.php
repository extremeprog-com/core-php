<?php
namespace _OS;

class CoreRequests {

    static $requestsMap = array();
    static $requestsMapIsActual = false;

    const REQUESTS_MAP_DIR = './';
    const REQUESTS_MAP_FILE = 'tmp/php_request_map.json';

    static $LastCatchedRequests = array();

    static function preinit() {
        self::$requestsMap = json_decode(file_get_contents(self::REQUESTS_MAP_DIR. "/". self::REQUESTS_MAP_FILE), true);
    }

    /**
     * @param Request $Request
     * @param null $target
     * @return mixed
     * @throws \Exception
     */
    static function dispatchRequest(\_OS\Request $Request, $target = null) {

        if(isset($target)) {
            self::$LastCatchedRequests[] = $Request;
            $result = call_user_func($target);
            array_pop(self::$LastCatchedRequests);
            if(isset($result)) {
                return $result;
            } else {
                throw new \Exception("Cannot execute request ".get_class($Request)."(".\JSON::encode($Request).") - there is no valid handlers");
            }
        }

        if ($Request->isJustCreated() && $WorkSession = \WorkSession::get()) {
            $WorkSession->resources['actions'][] = $Request;
        }

        $contexts = Contexts();
        self::$LastCatchedRequests[] = $Request;
        foreach(self::getListenersFor($contexts, $Request) as $method) {
            $result = call_user_func($method);
            if(!is_null($result)) {
                $Request->result = $result;
            }
            if(!is_null($Request->result)){
                break;
            }
        }
        array_pop(self::$LastCatchedRequests);

        if (isset($Request->result)) {

            $event = get_class($Request) . '_Success';
            $Event = new $event;
            if (isset($Request->_reqid)) {
                $Event->_reqid = $Request->_reqid;
            }
            /* cheatty cheat to iterate object with protected vars */
            foreach ((array) (is_object($Request->result) ? get_object_vars($Request->result) : $Request->result) as $key => $val) {
                $Event->$key = $val;
            }

            FireEvent($Event);
            return $Request->result;
        } else {
            $event = get_class($Request) . '_Fail';
            FireEvent(new $event);
            throw new \Exception("Cannot execute request ".get_class($Request)." - there is no valid handlers");
        }
    }

    static function getListenersFor($contexts, \_OS\Request $Request, $delivery = 'runtime') {
        $classname = get_class($Request);
        if(!isset(self::$requestsMap[$classname])){
            return [];
        }
        $contexts = is_array($contexts)?$contexts:[$contexts];
        foreach($contexts as &$context) {
            if(is_object($context)) {
                $context = get_class($context);
            }
        }
        $contexts_re = "/^$delivery".implode('', array_map(function($context) { return "(:$context)?";}, $contexts))."$/";
        $listeners = [];
        foreach(self::$requestsMap[$classname] as $delivery_method => $methods) {
            if(preg_match($contexts_re, $delivery_method)) {
                foreach($methods as $method => $data) {
                    if($data) {
                        foreach($data as $key => $val) {
                            if($Request->$key != $val) {
                                continue 2;
                            }
                        }
                    }
                    $listeners[] = $method;
                }
            }
        }

        usort($listeners, function($a,$b) {
            list(,$a) = explode('::',$a);
            list(,$b) = explode('::',$b);
            if($a>$b) return 1;
            if($a<$b) return -1;
            if($a==$b) return 0;
        });

        return $listeners;
    }

    static function generateMap() {

        $request2listeners = array();

        $files = explode("\n", trim(`find -L ./ | grep .php`));
        sort($files);

        foreach($files as $file) {
            $parts = explode('/', substr($file,0,-4));
            $class = end($parts);
            $file_contents = file_get_contents($file);

            if(preg_match("/namespace +([a-zA-Z0-9\\\\]+)/", $file_contents, $matches)) {
                $class = "\\".$matches[1]."\\".$class;
            } else {
                $class = "\\".$class;
            }

            if(class_exists($class)) {
                $rc = new \ReflectionClass($class);
                if($rc->isAbstract()) {
                    continue;
                }
                foreach($rc->getMethods(\ReflectionMethod::IS_STATIC) as $rm) {
                    if($rm->getDeclaringClass()->isAbstract()) {
                        if($rc->getParentClass()->getName() != $rm->getDeclaringClass()->getName()) {
                            continue;
                        }
                    } else {
                        if($rc->getName() != $rm->getDeclaringClass()->getName()) {
                            continue;
                        }
                    }
                    $strings = explode("\n", file_get_contents($rm->getFileName()));
                    $strings = array_slice($strings, $rm->getStartLine(), $rm->getEndLine() - $rm->getStartLine());
                    $strings = implode("\n",$strings);
                    preg_match('/CatchRequest\\(([^;]+)\\);/', $strings, $matches);
                    if(!isset($matches[1])){
                        continue;
                    }
//                    echo "$class\n";
//                    echo "    ::".$rm->getName()."\n";

                    $requests = trim($matches[1],"\n\r ,");
                    $requests = strtr($requests, [
                        '__CLASS__' => $rc->getName()."::class",
                        '__TRAIT__' => $rm->getNamespaceName()."\\".basename($rm->getFileName(),'.php')."::class",
                        'static::'  => $rc->getName()."::",
                    ]);

                    /**
                     * @var $requests array
                     */
                    eval('$requests_raw = array('.$requests.');');
                    if(!$requests_raw){
                        continue;
                    }
                    $requests = [];
                    $delivery_method = 'runtime';
                    foreach($requests_raw as $request) {
//                        if($request == DELIVERY_QUEUE) {
//                            $delivery_method = 'queue';
//                        } else {
                            call_user_func_array($closure = function($request, $context = '', $data = null) use (&$closure, &$requests) {
                                if(is_string($request)/* && is_subclass_of($request, \_OS\Request::class)*/) {
                                    $requests[$context.' '.$request] = $data;
                                }
                                elseif(is_array($request)) {
                                    if(array_keys($request) === range(0, count($request) - 1)) {
                                        foreach($request as $_e) {
                                            $closure($_e, $context, $data);
                                        }
                                    } else {
                                        foreach($request as $_c => $_e) {
                                            if(is_subclass_of($_c, \_OS\Request::class)) {
                                                $closure($_c, $context, $_e);
                                            } else {
                                                $closure($_e, $context.":".$_c);
                                            }
                                        }
                                    }
                                } else {
//                                    throw new \Exception('Error: type of request is not string or array');
                                }
                            }, [$request]);
//                        }
                    }
                    $method_name = $rc->getName()."::".$rm->getName();
                    foreach($requests as $c_request => $data) {
                        list($context, $request) = explode(" ", $c_request);
                        if(class_exists($request) && !is_subclass_of($request, \_OS\Request::class)) {
                            throw new \Exception("Shit happens: $request is not Request class for method ".$method_name);
                        }
                        if(!isset($request2listeners[$request])) {
                            $request2listeners[$request] = array();
                        }
                        if(!isset($request2listeners[$request][$delivery_method.$context])) {
                            $request2listeners[$request][$delivery_method.$context] = array();
                        }
                        $request2listeners[$request][$delivery_method.$context][$method_name] = $data;
                    }
                }
            }
        }

        foreach($request2listeners as &$delivery) {
            foreach($delivery as &$listeners) {
                uksort($listeners, function($a,$b) {
                    list(,$a) = explode('::',$a);
                    list(,$b) = explode('::',$b);
                    if($a>$b) return 1;
                    if($a<$b) return -1;
                    if($a==$b) return 0;
                });
            }
            ksort($delivery);
        }

        file_put_contents(self::REQUESTS_MAP_DIR. "/". self::REQUESTS_MAP_FILE, json_encode($request2listeners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

}