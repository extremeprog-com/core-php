<?php

class Context {

    /** @var Context */
    protected $ParentContext;
    protected $objects = array();
    protected $name;

    function __construct(Context $ParentContext = null, $name = null) {

        if($name) {
            $this->name = $name;
        } else {
            $callstack = debug_backtrace();
            $this->name = basename($callstack[0]['file']).":".$callstack[0]['line'];
        }

        if($ParentContext) {
            $this->name = $ParentContext->name.'/'.$this->name;
            $this->ParentContext = $ParentContext;
            $this->objects = $ParentContext->objects;
        }

    }

    static $instances = array();

    /**
     * @static
     * @return static
     * @throws Exception
     */
    static function getInstance($throw_exception = true) {
        foreach(self::$instances as $Context) {
            if($Context instanceof static) {
                return $Context;
            }
        }
        if($throw_exception) {
            throw new Exception(__METHOD__.' failed: Context not exists');
        }
    }

    static function getAll() {
        static $roles;
        if(!$roles) {
            $roles = explode(" ", getenv("ROLES")?:'');
            if($roles == ['']) {
                $project_config = Project::getConfig();

                if(isset($project_config['multirole']) && $project_config['multirole']) {
                    $roles = HostRole::getAllDeclaredRoles();
                } elseif($roles = HostRole::getRolesByHost()) {
                    // it's ok
                } else {
                    $roles = [];
//                    throw new Exception('Current roles is undefined. Define Roles environment variable, define roles in host config on base server for this host or use multirole=true for in your projects.json for project '.PROJECT);
                }
            }
            sort($roles);
        }
        $contexts = [];
        for($Context = Context::getInstance(false); isset($Context->ParentContext); $Context = $Context->ParentContext) {
            array_unshift($contexts, $Context);
        }
        $contexts = array_merge($roles, $contexts);
        return $contexts;
    }

    static function preinit() {
        function Context() {
            return Context::getInstance();
        }
        function Contexts() {
            return Context::getAll();
        }
    }

    function add($object, $tags = array()) {
        $this->objects[] = array($object, is_array($tags)?$tags:array($tags));
    }

    function get($class, $name = null, $throw_exception = true) {
        foreach($this->objects as $object_array) {
            if($object_array[0] instanceof $class && in_array($name, $object_array[1])) {
                return $object_array[0];
            }
        }

        foreach($this->objects as $object_array) {
            if($object_array[0] instanceof $class) {
                return $object_array[0];
            }
        }

        if($throw_exception)
            throw new Exception("Object $name with class $class not found in context");
    }


    /**
     * @return User
     */
    function getUser($name = null, $throw_exception = true) {
        return self::get('User', $name, $throw_exception);
    }

    function run($callback) {
        $rf = new ReflectionFunction($callback);
        $args = array();
        foreach($rf->getParameters() as $rp) {
            /** @var ReflectionParameter $rp */
            preg_match('/\[\s\<\w+?>\s([\w]+)/s', $rp->__toString(), $matches);
            $class = isset($matches[1]) ? $matches[1] : null;
            $name = $rp->getName();

            foreach($this->objects as $object_array) {
                if($object_array[0] instanceof $class && in_array($name, $object_array[1])) {
                    $args[] = $object_array[0];
                    continue 2;
                }
            }

            foreach($this->objects as $object_array) {
                if($object_array[0] instanceof $class) {
                    $args[] = $object_array[0];
                    continue 2;
                }
            }

            throw new Exception("Object $class $$name not found in context ".$this->name);
        }

        array_unshift(self::$instances, $this);

        if(method_exists($this, 'init')) {
            $this->init();
        }

        try {
            $result = call_user_func_array($callback, $args);
        } catch(Exception $e) { }

        array_shift(self::$instances);

        if(isset($e))
            throw $e;

        if(isset($result))
            return $result;
    }

    static function inContext($context_class = null) {
        if(is_null($context_class)) {
            $context_class = get_called_class();
        }
        foreach(self::getAll() as $Context) {
            if($Context instanceof $context_class || $context_class == $Context) {
                return true;
            }
        }
        return false;
    }

}
