<?php

class FileAPI {

    /**
     * Прочитать файл. Создает пути и пустой файл, если файл не существует.
     * @param $file
     * @param $contents
     */
    public static function get($file) {
        if(!file_exists($file)) {
            if(!file_exists(dirname($file))) {
                mkdir(dirname($file));
            }
            touch($file);
        }
        return file_get_contents($file);
    }

    /**
     * Записать файл. Создает пути и файл в случае необходимости.
     * @param $file
     * @param $contents
     */
    public static function put($file, $contents) {
        if(!file_exists($file)) {
            if(!file_exists(dirname($file))) {
                mkdir(dirname($file));
            }
        }
        file_put_contents($file, $contents);
    }

    /**
     * Получить файл в формате json. Создает необходимые пути, если их не существует. Если файл не существует или пустой, возвращает пустой массив или второй аргумент
     * @param $file
     * @throws Exception
     */
    public static function getJSON($file, $result_on_empty_file = []) {
        $contents = self::get($file);
        if (!$contents) {
            return $result_on_empty_file;
        }
        $json_contents = JSON::decode($contents);

        if(json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON data in ' . $file . ' has wrong format, json parser error was: ' . json_last_error_msg());
        }
        return $json_contents;
    }

    /**
     * Сохранить файл в формате json. Создает необходимые пути, если их не существует.
     * @param $file
     * @param $data
     * @param string $JSON_method - какой метод класса JSON использовать для серилизации
     */
    public static function putJSON($file, $data, $JSON_method = "hencode") {
        self::put($file, call_user_func_array([JSON::class, $JSON_method], [$data]));
    }

}