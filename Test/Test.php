<?php

class Test {
    /** @var Test */
    static $instance;

    public $passed = null, $name = '', $exceptions = array(), $checked = 0, $ok = 0;

    static function preinit() {
        function Test() {
            return Test::getInstance();
        }
    }
    
    function __construct($name) {
        $this->name = $name;
    }
    
    static function runTest($method) {
        if (!Project::getConfigKey('testable')) {
            throw new Exception('Not testable. Add testable true to project.json');
        }
        self::$instance = new self($method);
        $ok = false;
        file_put_contents("php://stderr", "$method...");
        
        try {
            WorkSession::start();
            call_user_func_array($method, array(self::$instance));
            if (is_null(self::$instance->passed) && self::$instance->checked) {
                self::$instance->passed = true;
            }
        }
        catch(Exception $e) {
            foreach(self::$instance->exceptions as $stored) {
                if($e == $stored)
                    unset($e);
            }
            if(isset($e)) {
                self::$instance->exceptions[] = $e;
            }
        }
        finally {
            WorkSession::end();
        }
        
        if (self::$instance->passed) {
            if(self::$instance->ok == self::$instance->checked) {
                file_put_contents("php://stderr", " \033[32mpassed ".self::$instance->ok."/".self::$instance->checked."\033[0m\n");
                $ok = true;
            } else {
                file_put_contents("php://stderr", " \033[31mpassed ".self::$instance->ok."/".self::$instance->checked."\033[0m\n");
            }
        } else {
            if(self::$instance->checked) {
                file_put_contents("php://stderr", " \033[31merror ".self::$instance->ok."/".self::$instance->checked."\033[0m\n");
            } else {
                file_put_contents("php://stderr", " \033[31mno checks were run ".self::$instance->ok."/".self::$instance->checked."\033[0m\n");
            }
        }
        
        foreach(self::$instance->exceptions as $e) {
            echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
        }
        self::$instance = null;
        return $ok;
    }
    
    static function runTests($one_test = null) {
        if (!Project::getConfigKey('testable')) {
            throw new Exception('Not testable. Add testable true to project.json');
        }

        \_OS\Autoloader::loadAll();
        foreach(get_declared_classes() as $class) {
            if (class_exists($class) && $class != __CLASS__) {
                $rc = new ReflectionClass($class);
                foreach($rc->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC) as $rm) {
                    if(substr($rm->getName(), 0, 4) == 'test' && substr($rm->getName(), 4) && $rm->getParameters()[0] && $rm->getParameters()[0]->getClass()->getName() == 'Test') {
                        if(!$one_test || $one_test == $rm->getName() || $one_test == $class."::".$rm->getName()) {
                            $ok = self::runTest($class."::".$rm->getName());
                            if(!$ok) {
                                file_put_contents("php://stderr", " \033[31mTesting breaks due errors\033[0m\n");
                                return;
                            }
                        }
                    }
                }
            }
        }
    }
    
    static function getInstance() {
        return self::$instance;
    }
    
    function check($title, $value, $check = null) {

        $this->checked++;

        if(is_scalar($check)) {
            $check = check::eq($check);
        }

        if ($check instanceof check) {
            if ($check->check($value)) {
                $this->ok++;
            } else {
                $e = new Exception("Check '$title' failed, checked for ".json_encode($value, JSON_UNESCAPED_UNICODE).' '.$check->method." ".json_encode($check->value, JSON_UNESCAPED_UNICODE));
                $this->exceptions[] = $e;
            }
        } elseif(is_null($check)) {
            if($value) {
                $this->ok++;
            } else {
                $e = new Exception("Check '$title' failed");
                $this->exceptions[] = $e;
            }
        } else {
            $caller = debug_backtrace()[0];
            throw new ErrorException("Wrong check",0 , E_PARSE, $caller['file'], $caller['line']);
        }
    }
    
    function passed() {
        $this->passed = true;
    }

    function fail() {
        $this->passed = false;
    }

    private $mocks = [];

    function setMock($name, $value) {
        $this->mocks[$name] = $value;
    }

    function getMock($name) {
        return $this->mocks[$name];
    }

    function hasMock($name) {
        return array_key_exists($name, $this->mocks);
    }

    function clearMocks() {
        $this->mocks = [];
    }

    function fireEvent(\_OS\Event $Event, callable $callable) {
        \_OS\CoreEvents::$LastCatchedEvents[] = $Event;
        call_user_func($callable);
        array_pop(\_OS\CoreEvents::$LastCatchedEvents);
    }

    function fireRequest(\_OS\Request $Request, callable $callable) {
        \_OS\CoreRequests::$LastCatchedRequests[] = $Request;
        $Request->result = call_user_func($callable);
        array_pop(\_OS\CoreRequests::$LastCatchedRequests);
    }

}
