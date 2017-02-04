<?php
/**
 * Created by JetBrains PhpStorm.
 * User: developer
 * Date: 22.02.12
 * Time: 17:16
 * To change this template use File | Settings | File Templates.
 */
 
class SysConfig {


    const FILE_NAME_CONFIG = '/_config/config.php';

    protected static $cache = array();
    protected static $loaded = false;


    static function get($section, $key, $type = 'string'){
        self::loadConfigIfNeed();
        
        if(isset(self::$cache[$section][$key]))
            $data = self::$cache[$section][$key];
        else
            $data = null;
        
        if($type == 'array')
            return (array)$data?:array();
        
        if($type == 'int')
            return (int)$data?:0;
        
        if($type == 'string')
            return (string)$data?:'';
        
        if($type == 'html')
            return (string)$data?:'';
        
        if($type == 'bool')
            return (bool)$data?:false;
        
    }

    protected static function loadConfigIfNeed() {

        if(self::$loaded)
            return;

        if(!file_exists(self::getConfigFilename()))
            return;

        include self::getConfigFilename();
        if(isset($config))
            self::$cache = $config;
        else
            self::$cache = array();
    }

    public static function getConfig() {
        self::loadConfigIfNeed();
        return self::$cache;
    }

    public static function getConfigFilename() {
        return realpath(PATH_ROOT.'/..').self::FILE_NAME_CONFIG;
    }

    public static function set($section, $key, $val) {
        self::loadConfigIfNeed();
        self::$cache[$section][$key] = $val;
        self::PublishConfigOnAllServers(self::$cache);
    }

    public static function setSection($section, $data) {
        self::loadConfigIfNeed();
        self::$cache[$section] = $data;
        self::PublishConfigOnAllServers(self::$cache);
    }

    public static function PublishConfigOnAllServers($data){

        if(PLATFORM_ID == PLATFORM_DEV){
            SysConfig::saveConfigFile($data);
            return;
        }

        /** @var array $config */
        include(PATH_CONFIG.'/roles.php');
        $hosts = array_keys($config[PLATFORM_ID]);
        foreach($hosts as $host) {
            MessageBus::push_message('SysConfig::saveConfigFile',array($data),array('server'=>$host));
        }
    }

    public static function saveConfigFile($config) {

        $file = self::getConfigFilename();

        if(!file_exists($dir = dirname($file)))
            mkdir($dir);

        file_put_contents( $file, "<?php \$config = ".var_export($config,true).";");
    }

    public static function getSchema() {

        static $schema;
        if(!$schema){
//            /** @var array $config */
//            include self::getSchemaFilename();
//            $schema = $config;
            
            $schema = self::generateSchema();
        }

        return $schema;

    }

//    const FILENAME = 'system_config_schema.php';

//    static public function getSchemaFilename(){
//        return PATH_PLATFORM_CONFIG."dynamic/".self::FILENAME;
//    }

    static public function generateSchema() {
        $config_schema = array();

        foreach(explode("\n", `find ./core ./domain ./lib ./pages | grep .php`) as $file) {

            if(!file_exists($file))
                continue;

            preg_match_all('/SysConfig\:\:get\(([^\)]*)\)/',file_get_contents($file), $matches, PREG_PATTERN_ORDER);

            foreach($matches[1] as $args_str) {
                try{
                    $args_str = strtr($args_str,array(
                        '__CLASS__' => "'".substr(basename($file),0,-4)."'",
                        'self::' => substr(basename($file),0,-4)."::",
                    ));
                    
                    /** @var array $args */
                    eval('$args = array('.$args_str.');');
                    $config_schema[$args[0]][$args[1]] = isset($args[2])?$args[2]:'string';
                } catch(Exception $e){
                    echo $e->getMessage()."\n";
                }

            }
        }
        return $config_schema;
//        $file_content = var_export($config_schema, true);
//        file_put_contents(self::getSchemaFilename(), "<?php \$config = ".$file_content.";");
    }


}