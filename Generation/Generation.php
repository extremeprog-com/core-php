<?php

class Generation {

    static function generate() {
        $generated_path = PATH_WORKDIR."/cowork/_generated";
        $modules = [];
        $class_to_module = [];
        $class_to_methods = [];
        $class_to_content = [];
        $classes = [];
        $class_to_definition = [];
        $class_to_description = [];

        // получим все тексты
        $texts = static::getAllTexts();

        // распарсим используемые определения
        $terms = [];
        $pres = [];
        $pres_descriptions = [];
        foreach($texts as $text) {
            preg_match_all('|(?:\n(?:- )?([^\n]+)\n+)?<pre>(.+?)</pre>|sm', $text, $matches);
            foreach($matches[2] as $i=>$txt) {
                $pres[] = $txt;
                $pres_descriptions[] = $matches[1][$i];
                preg_match_all('/[A-Za-z_][A-Za-z0-9_]+/', $txt, $mtch);
                $terms = array_unique(array_merge($terms, $mtch[0]));
            }
            $terms = array_diff($terms, self::$hideTerms);
        }
        natcasesort($terms);
        Console::writeLn("Symbols:");
        Console::writeLn(implode("\n", $terms), "    ");

        // распарсим соответствие модулей и классов
        foreach($texts as $text) {
            preg_match_all('|\n([^\n]*)\\/\\.\\.\\.\n(.+)\n\\.\\.\\./|smU', $text, $matches);
            foreach($matches[1] as $i => $module) {
                if(array_search($module, $modules) === false) {
                    $modules[] = $module;
                }
                foreach(explode("\n", $matches[2][$i]) as $class) {
                    preg_match('/ *([^ \n]+)(?: - )?(.*)/', $class, $mt);
                    $class = $mt[1];
                    if(!isset($class_to_description[$class])) {
                        $class_to_description[$class] = [];
                    }
                    if(!empty($mt[2])) {
                        $class_to_description[$class][] = $mt[2];
                    }
                    $class_to_module[$class] = $module;
                    if(array_search($class, $classes) === false) {
                        $classes[] = $class;
                    }
                }
            }
        }

        // распарсим соответствие классов и методов
        foreach($pres as $prei => $pre) {
            preg_match_all('|\n(?:php: )?([^\n ]*)::([^\n]*) \\{\\.\\.\\.\n(.+)\n\\.\\.\\.}|smU', $pre, $matches);
            foreach($matches[1] as $i => $class) {
                $method  = $matches[2][$i];
                $content = $matches[3][$i];
                if(array_search($class, $classes) === false) {
                    throw new Exception('Not declared module for class "'.$class.'"');
                }
                if(!isset($class_to_methods[$class])) {
                    $class_to_methods[$class] = [];
                }
                if(!isset($class_to_methods[$class][$method])) {
                    $class_to_methods[$class][$method] = [];
                }
                $class_to_methods[$class][$method][] = "    // {$pres_descriptions[$prei]} \n".$content;
            }
        }

        // распарсим определения внутри классов
        foreach($texts as $text) {
            preg_match_all('|\n([^:\n]*) \\{\\.\\.\\.\n(.+)\n\\.\\.\\.}|smU', $text, $matches);
            foreach($matches[1] as $i => $class) {
                $content = $matches[2][$i];
                if(array_search($class, $classes) === false) {
                    throw new Exception('Not declared module for class "'.$class.'"');
                }
                if(!isset($class_to_definition[$class])) {
                    $class_to_definition[$class] = '';
                }
                $class_to_definition[$class] .= "\n".$content."\n";
            }
        }

        foreach($classes as $class) {
            if(class_exists($class)) {
                $pass = true;
                foreach(isset($class_to_methods[$class])?$class_to_methods[$class]:[] as $method => $content) {
                    $method = explode("(", $method)[0];
                    if(!method_exists($class, $method)) {
                        $pass = false;
                    }
                }
                if($pass) {
                    $class_to_content[$class] = "";
                    continue;
                }
            }
            $class_to_content[$class] = "<?php \n\nnamespace _generated;\nuse \\Exception, \\cmp, \\PDO;\n\n"
                ."class $class {\n"
                .($class_to_description[$class]?"    // ".implode("; ", $class_to_description[$class])."\n":'')
                .(isset($class_to_definition[$class])?$class_to_definition[$class]:'');
            foreach(isset($class_to_methods[$class])?$class_to_methods[$class]:[] as $method => $content) {
                $class_to_content[$class] .= "\n    static function $method {". strtr("\n".implode("\n\n    // <------>\n\n", $content)."\n", ["\n" => "\n    "])."}\n";
            }
            $class_to_content[$class] .= "\n}\n";
        }

        foreach($modules as $module) {
            `mkdir -p $generated_path/$module`;
        }

        // запишем файлы
        foreach($class_to_content as $class => $content) {
            if($content) {
                $file = $generated_path."/".$class_to_module[$class]."/_".$class.".php";
                file_put_contents($file, $content);
            }
        }

        // выведем записанные файлы
        Console::writeLn();
        Console::writeLn("Files:");
        foreach($modules as $module) {
            Console::writeLn($module."/", "    ");
            foreach($class_to_content as $class => $content) {
                if($class_to_module[$class] != $module) continue;
                Console::writeLn($class.'.php', "        ");
            }
        }

        // структура классов
        Console::writeLn();
        Console::writeLn("Classes:");
        foreach($modules as $module) {
            foreach($class_to_content as $class => $content) {
                if($class_to_module[$class] != $module) continue;
                Console::writeLn(
                    $class.
                    ($class_to_description[$class]?" - ".implode("\n", $class_to_description[$class]):''),
                    "    "
                );

            }
        }

        // структура классов
        Console::writeLn();
        Console::writeLn("Methods:");
        foreach($modules as $module) {
            foreach($class_to_content as $class => $content) {
                if($class_to_module[$class] != $module) continue;
                Console::writeLn($class, "    ");
                foreach(isset($class_to_methods[$class])?$class_to_methods[$class]:[] as $method => $body) {
                    $method_ready = false;
                    if(method_exists($class, explode("(",$method)[0])) {
                        $rm = new ReflectionMethod($class, explode("(",$method)[0]);
                        $method_ready = strpos($rm->getDocComment(), '@complete')!==false;
                    }
                    Console::writeLn(
                        ($method_ready?'':'+ ').
                        "->".$method
                        ,
                        "        ");
                }
            }
        }

        // выведем описание без <pre>
        Console::writeLn();
        Console::writeLn("Texts:");
        foreach($texts as $text) {
            $text = preg_replace('|(\n+ *)+<pre>.+</pre>(\n+ *)+|smU', "\n", $text);
            $text = preg_replace('|\n+( ?-)+|smU', "\n$1", $text);
            $text = preg_replace('|\n\n+|sm', "\n\n", $text);
            Console::writeLn($text);
        }
    }

    static function getAllTexts() {
        $docs_path = PATH_WORKDIR."/cowork/_docs/";

        $texts = [];
        foreach(glob($docs_path.'/*.txt') as $file) {
            $texts[$file] = file_get_contents($file);
        }
        return $texts;
    }

    static $hideTerms = [
        'try', 'throw', 'foreach', 'strtolower', 'strtoupper', 'function', 'catch', 'file_get_contents', 'if', 'done',
        'test', 'Test', 'td', 'tr', 'table', 'Session', 'session', 'http', 'namespace', 'class', 'microtime', 'md5',
        'FireEvent', 'CatchEvent', 'FireRequest', 'CatchRequest', 'StartsWith', 'Exception', 'true', 'false', 'null',
        'while', 'as', 'and', 'or', 'else', 'elseif', 'eq', 'instanceof', 'self', 'static', '__CLASS__', '__METHOD__',
        'isset', 'onclick', 'createMessage', 'RPC',
    ];
}