<?php

class Templates {

    public $search_paths = [];

    function addSearchFileMask($file_mask) {
        if($file_mask[0] == '/') {
            $this->search_paths[] = PATH_ROOT.$file_mask;
        } else {
            $this->search_paths[] = dirname(debug_backtrace()[0]['file'])."/".$file_mask;
        }
    }

    function compile($xpath, $distinct = false) {
        $out = [];
        foreach($this->search_paths as $file_mask) {

            $files = array();
            {// fetch files and sort it in alphabet case-insensitive order
                foreach(glob($file_mask) as $file) {
                    $files[strtolower($file)] = $file;
                }
                ksort($files);
                $files = array_values($files);
            }

            foreach($files as $file) {

                try {
                    $content = file_get_contents($file);
                    $content = str_replace('<html xmlns=', '<html ns=', $content);
                    $content = str_replace('&nbsp;', '&#160;', $content);
                    $SimpleXML = simplexml_load_string($content);
                } catch(Exception $e) {
                    echo $e->getMessage()."\n".$e->getTraceAsString();
                    Logger()->error($e, __CLASS__);
                    continue;
                }
                foreach($SimpleXML->xpath($xpath) as $HtmlElement) {
                    $data = $HtmlElement->asXML();
//                $data = "<!-- file: ".substr($file, strlen(PATH_WORKDIR) + 1)." xpath: $xpath -->\n" . $data;

                    if($distinct)
                        $out[md5($data)] = $data;
                    else
                        $out[] = $data;
                };
            }
        }
        $compiled = implode("\n", $out);
        
        preg_match_all('/#[a-z0-9A-Z_]+(\.[a-z0-9A-Z_]+)+/', $compiled, $matches, PREG_PATTERN_ORDER);

        $vars = array_unique($matches[0]);

        $data = [];

        foreach($vars as $var) {
            $data[$var] = Requests::Templates_NeedResolveVar(substr($var,1));
        }

        $compiled = strtr($compiled, $data);

        echo $compiled;
    }
}