<?php

abstract class _OSRizzomaAdmin {

    static $sid = '';
    static $document = '';

    static function getCategories() {
        $cats = array();
        foreach(static::getConfig() as $key=>$val) {
            if(preg_match("/^PD\.catalog\.([0-9]+)\.([a-z]+)$/", $key, $matches)) {
                list(, $cat_id, $field) = $matches;
                if(!isset($cats[$cat_id])) {
                    $cats[$cat_id] = array(
                        '_num' => $cat_id
                    );
                }
                $cats[$cat_id][$field] = $val;
            }
        }
        return array_values($cats);
    }

    static function getItems($cat_id) {
        return static::getConfigTree('PD.catalog.'.$cat_id);
    }

    static function getConfigTree($path = '') {
        $vars = static::getConfig();
        $result = array();
        foreach($vars as $key=>$val) {
            $cursor = &$result;
            foreach(explode(".", $key) as $name) {
                if(is_numeric($name)) {
                    $name--;
                }
                if(!isset($cursor[$name])) {
                    $cursor[$name] = array();
                    if(is_numeric($name)) {
                        $cursor[$name]['_num'] = $name + 1;
                    }
                }
                $cursor = &$cursor[$name];
            }
            $cursor = $val;
        }

        if($path) {
            foreach(explode(".",$path) as $item) {
                if(is_numeric($item)) {
                    $result = $result[$item - 1];
                } else {
                    $result = $result[$item];
                }
            }
        }

        return $result;
    }

    static function loadedFilesStorage() {
        return new StorageKeyDataFile(static::class, __METHOD__, []);
    }

    static function getConfig($force = false) {

        if(!$force) {
            $config = JSON::decode(file_get_contents("tmp/rizzoma_config.json"));
        }

        if(!isset($config)) {

            $to_load = [];

            $vars = static::getConfigWithSource($force);

            echo "loaded config\n";

            $loaded_files = static::loadedFilesStorage()->get();

            foreach($vars as $key=>&$var) {
                $val = '';
                foreach($var as $_v) {
                    if(isset($_v['type']) && $_v['type'] == 'text') {
                        if(isset($_v['value'])) {
                            $val .= $_v['value'];
                        } else {
                            Logger()->warn("Assertion failed on line ".__LINE__,__CLASS__);
                        }
                    }
                    if(isset($_v['type']) && ($_v['type'] == 'file' || $_v['type'] == 'attachment') ) {
                        if(isset($_v['url'])) {
//                            $dir = __DIR__."/PDRizzomaAdminFiles";
//                            $to_load[] = "$dir/$key.jpg:".$_v['url'];
//                            $val .= "PDRizzomaAdminFiles/$key.jpg";
                            if (!isset($loaded_files[$key]) || !is_array($loaded_files[$key]) || $loaded_files[$key][0] != $_v['url']) {
                                echo 'uploading '.$_v['url']." for key ".$key."...\n";

                                if(preg_match('/^.*\/([^\/]\.(jpg|png|gif))/', $_v['url'], $matches)
                                    && file_exists(UploadifyImages::DATA_PATH.$matches[1])) {
                                    // our file
                                    $val = UploadifyImages::URL.$matches[1];
                                } else {
                                    // upload file
//                                    $file = $uc->uploader->fromUrl($_v['url']);
//                                    $file->store();
//                                    $loaded_files[$key] = $_v['url'];
//                                    $val = $file->getUrl();
                                    $val = UploadifyImages::uploadFromWeb(isset($matches[0])?$matches[0]:$_v['url'], isset($_v['name'])?$_v['name']:null);
                                }

                                echo "stored to $val\n";
                                $loaded_files[$key] = array($_v['url'], $val);
                                static::loadedFilesStorage()->set($loaded_files);
                            } else {
                                $val = $loaded_files[$key][1];
                            }
                        } else {
                            Logger()->warn("Assertion failed on line ".__LINE__,__CLASS__);
                        }
                    }
                }
                $var = $val;
            }

            static::loadedFilesStorage()->set($loaded_files);

            file_put_contents("tmp/rizzoma_config.json", JSON::hencode($vars));

            $config = $vars;
        }


        return $config;
    }

    static function getConfigWithSource($force = false) {
        $sid = static::$sid;

        $document = static::$document;

        $json = `curl -s -b connect.sid=$sid 'https://rizzoma.com/api/export/1/$document/json/'`;

        try {
            $tree = json_decode($json, true);
            if(!$force) {
                $it_is_ok  = true;
                throw new Exception('just read!');
            }
            if(!$tree) {
                throw new Exception("A hui ego znaet");
            }
            file_put_contents("tmp/rizzoma_export.json", JSON::hencode(JSON::decode($json)));
        } catch(Exception $e) {
            if(!isset($it_is_ok)) {
                Logger()->error($e);
            }
            $json = file_get_contents("tmp/rizzoma_export.json");
            $tree = json_decode($json, true);
        }

        $list_elements = [];

        $path = array();
        $cursor = 0;
        for(;;) {
            $element = $tree;

            foreach($path as $p) {
                $element = $element['nodes'][$p];
            }

            if (isset($element['nodes'][$cursor]['nodes'][0])) {
                unset($element['nodes'][$cursor]['nodes']);
                $list_elements[] = array("_path" => implode(".", $path).".".$cursor) + $element['nodes'][$cursor];
                $path[] = $cursor;
                $cursor = 0;
            } elseif(isset($element['nodes'][$cursor])) {
                unset($element['nodes'][$cursor]['nodes']);
                $list_elements[] = array("_path" => implode(".", $path).".".$cursor) + $element['nodes'][$cursor];
                $cursor++;
            } elseif(sizeof($path)) {
                $cursor = array_pop($path);
                $cursor++;
            } else {
                break;
            }
        }

        $filtered_elements = [];

        foreach($list_elements as $element) {
            if(isset($element['value']) || !empty($element['url']) || !empty($element['author'])) {
                $filtered_elements[] = $element;
            }
        }

        $list_elements = $filtered_elements;

        $namespace = '';
        $namespace_path = '0';
        $vars = [];

        $enums = [];

        for($i = 0; $i < sizeof($list_elements); $i++) {
            $element = $list_elements[$i];
            if (!empty($element['value']) && preg_match('/^([_a-zA-Z0-9\.]+)=$/', $element['value'], $matches)) {
                $key = $matches[1];

                if($namespace && strpos($element['_path'], $namespace_path)!==0) {
                    $namespace_path = '0';
                    $namespace = '';
                }

                if (isset($vars[$namespace.$key])) {
                    Logger()->error("Duplicated entry '$namespace$key' in rizzoma admin", static::__name);
                    throw new Exception("Duplicated entry '$namespace$key' in rizzoma admin");
                }
                $vars[$namespace.$key] = [];
                //вычисляем путь следующего элемента
                $path = explode(".", $element['_path']);
                $idx = array_pop($path);
                $nextpath = implode(".", $path).".".($idx+1);
                while(isset($list_elements[$i+1])&&strncmp($list_elements[$i+1]['_path'], $nextpath, strlen($nextpath)) == 0) {
                    $catch_node = array('_key_node_path' => $element['_path'] ) + $list_elements[$i+1];
                    $vars[$namespace.$key][] = $catch_node;
                    $i++;
                }

                if($key == "_namespace") {
                    array_pop($path);
                    $ns = $vars[$namespace.$key];
                    unset($vars[$namespace.$key]);
                    $namespace = current($ns)['value'].".";
                    $namespace_path = implode(".", $path);
                    if($namespace == '.') {
                        $namespace = '';
                        $namespace_path = '0';
                    }
                    if(substr($namespace,-4)=='.[].') {
                        $_ns = substr($namespace,0,-4);
                        if(!isset($enums[$_ns])) {
                            $enums[$_ns] = 1;
                        } else {
                            $enums[$_ns]++;
                        }
                        $namespace = $_ns.".".$enums[$_ns].".";
                    }
                    elseif(substr($namespace,-3)=='[].') {
                        $_ns = substr($namespace,0,-3);
                        if(!isset($enums[$_ns])) {
                            $enums[$_ns] = 1;
                        } else {
                            $enums[$_ns]++;
                        }
                        $namespace = $_ns.".".$enums[$_ns].".";
                    }
                }
            }
        }

        return $vars;
    }

    static function loadActual() {
        CatchEvent(\_OS\Core\System_InitConfigs::class);
        echo __METHOD__."\n";
        static::getConfig(true);
    }

}