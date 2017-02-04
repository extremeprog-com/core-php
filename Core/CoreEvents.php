<?php
namespace _OS;

define('DELIVERY_QUEUE', 100001);

class CoreEvents {

    static $eventsMap = null;

    const EVENTS_MAP_DIR = './';
    const EVENTS_MAP_FILE = 'tmp/php_event_map.json';

    static $LastCatchedEvents = array();

    static function preinit() {
        self::$eventsMap = json_decode(file_get_contents(self::EVENTS_MAP_DIR. "/". self::EVENTS_MAP_FILE), true);
    }

    static function dispatchEvent($Event, $target = null) {

        if(isset($target)) {
            self::$LastCatchedEvents[] = $Event;
            $result = call_user_func($target);
            array_pop(self::$LastCatchedEvents);
            if(isset($result)) {
                return $result;
            } else {
                throw new \Exception("Cannot execute event ".get_class($Event)."(".\JSON::encode($Event).") - not valid handler");
            }
        }

        if ($Event->isJustCreated() && $WorkSession = \WorkSession::get()) {
            $WorkSession->resources['actions'][] = $Event;
        }
        $contexts = Contexts();
        self::$LastCatchedEvents[] = $Event;
        foreach(array_unique(self::getListenersFor($contexts, $Event)) as $method) {
            call_user_func($method);
        }
        array_pop(self::$LastCatchedEvents);

        if(self::getListenersFor($contexts, $Event, 'queue')) {
            \EventBroker::sendToBroker($Event);
        }

        if($Event::SEND_TO_LOGGER) {
            \Log::event($Event);
        }
    }

    static function getListenersFor($contexts, \_OS\Event $Event, $delivery = 'runtime') {
        $classname = get_class($Event);
        if(!isset(self::$eventsMap[$classname])){
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
        foreach(self::$eventsMap[$classname] as $delivery_method => $methods) {
            if(preg_match($contexts_re, $delivery_method)) {
                foreach($methods as $method => $data) {
                    if($data) {
                        foreach($data as $key => $val) {
                            if($Event->$key != $val) {
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

    static function runInEventContext(\_OS\Event $Event, $func) {
        self::$LastCatchedEvents[] = $Event;
        call_user_func($func);
        array_pop(self::$LastCatchedEvents);
    }

    static function generateMap() {

        $event2listeners = array();

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
                        /* That magic does not work
                        if($rc->getParentClass()->getName() != $rm->getDeclaringClass()->getName()) {
                            continue;
                        }
                        */
                    } else {
                        if($rc->getName() != $rm->getDeclaringClass()->getName()) {
                            continue;
                        }
                    }
                    $strings = explode("\n", file_get_contents($rm->getFileName()));
                    $strings = array_slice($strings, $rm->getStartLine(), $rm->getEndLine() - $rm->getStartLine());
                    $strings = implode("\n",$strings);
                    preg_match('/CatchEvent\\(([^;]+)\\);/', $strings, $matches);
                    if(!isset($matches[1])){
                        continue;
                    }
//                    echo "$class\n";
//                    echo "    ::".$rm->getName()."\n";

                    $events = trim($matches[1],"\n\r ,");
                    $events = strtr($events, [
                        '__CLASS__' => $rc->getName()."::class",
                        '__TRAIT__' => $rm->getNamespaceName()."\\".basename($rm->getFileName(),'.php')."::class",
                        'static::'  => $rc->getName()."::",
                    ]);

                    /**
                     * @var $events array
                     */
                    eval('$events_raw = array('.$events.');');
                    if(!$events_raw){
                        continue;
                    }
                    $events = [];
                    $delivery_method = 'runtime';
                    foreach($events_raw as $event) {
                        if($event == DELIVERY_QUEUE) {
                            $delivery_method = 'queue';
                        } else {
                            call_user_func_array($closure = function($event, $context = '', $data = null) use (&$closure, &$events) {
                                if(is_string($event)) {
                                    $events[$context.' '.$event] = $data;
                                }
                                elseif(is_array($event)) {
                                    if(array_keys($event) === range(0, count($event) - 1)) {
                                        foreach($event as $_e) {
                                            $closure($_e, $context, $data);
                                        }
                                    } else {
                                        foreach($event as $_c => $_e) {
                                            if(is_subclass_of($_c, \_OS\Event::class)) {
                                                $closure($_c, $context, $_e);
                                            } else {
                                                $closure($_e, $context.":".$_c);
                                            }
                                        }
                                    }
                                } else {
                                    throw new \Exception('Error: type of event is not string or array');
                                }
                            }, [$event]);
                        }
                    }
                    $method_name = $rc->getName()."::".$rm->getName();
                    foreach($events as $c_event => $data) {
                        list($context, $event) = explode(" ", $c_event);
//                        if(!is_subclass_of($event, \_OS\Event::class)) {
//                            throw new \Exception("Shit happens: $event is not Event class for method ".$method_name);
//                        }

                        if(!isset($event2listeners[$event])) {
                            $event2listeners[$event] = array();
                        }
                        if(!isset($event2listeners[$event][$delivery_method.$context])) {
                            $event2listeners[$event][$delivery_method.$context] = array();
                        }
                        $event2listeners[$event][$delivery_method.$context][$method_name] = $data;
                    }
                }
            }
        }

        foreach($event2listeners as &$delivery) {
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

        file_put_contents(self::EVENTS_MAP_DIR. "/". self::EVENTS_MAP_FILE, json_encode($event2listeners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
//        echo  json_encode($event2listeners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

}
