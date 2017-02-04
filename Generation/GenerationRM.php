<?php

class GenerationRM extends Generation {

    static function getAllTexts() {
        $config = json_decode(file_get_contents(PATH_WORKDIR."/cowork/config.json"));
        $tasklist = $config->tasklist;

        $texts = [];

        foreach($tasklist as $tq) {
            $url = $config->rm_url."/issues.json";
            $params = [
                'project_id' => $config->project_id,
                'status_id' => '*',
                $config->queue_field => $tq,
                'key' => $config->key,
                'limit' => 10000,
                'sort' => 'created_on',
            ];

            $query_string = [];
            foreach ($params as $key => $val) {
                $query_string[] = urlencode($key) . "=" . urlencode($val);
            }

            $query_string = implode("&", $query_string);

            $data = json_decode(file_get_contents($url."?".$query_string));

            foreach($data->issues as $task) {
                $texts[] = strtr($task->description, array("\r" => ""));
            }
        }

        return $texts;
    }
}