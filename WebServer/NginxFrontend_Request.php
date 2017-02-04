<?php

abstract class NginxFrontend_Request extends \_OS\Request {

    public $host;
    public $uri;
    public $params = [];
    public $type;
    public $raw_post;
    public $handler;
    public $method;
    public $upload;

    use API_Request;
    
    /**
     * Проверка на обязательные поря
     *
     * @param array $keys
     * @throws \Exception
     */
    public function checkFields(array $keys)
    {
        foreach($keys as $field => $checks) {
            if(!is_array($checks)) {
                $checks = [ $checks ];
            }
            foreach($checks as $check) {
                /** @var check $check */
                if(!$check->check($this->param($field))) {
                    switch($check->method) {
                        case 'exists':   $m = 'Вы не заполнили обязательное поле: ' . $field; break;
                        case 'type':     {
                            //$m = 'Поле '.$field.' = '.print_r($this->param($field), true).' неправильного типа '.(gettype($this->param($field)) == 'object'?get_class($this->param($field)):gettype($this->param($field))).', ожидается '.$check->value;
                            $m = 'Вы ввели неправильное значение для поля ' . $field . ' (' . $check->value . ')';
                            break;
                        }
                        case 'loadable': $m = 'Невозможно загрузить '.$field; break;
                        default:         $m = 'Ошибка в поле '.$field; break;
                    }
//                    Log::security([
//                        'message' => $m,
//                        'params' => $this->params,
//                    ], __CLASS__);
                    throw new Exception($m);
                }
            }
        }
    }

    /**
     * Get request param by key. Returns null if no such param
     *
     * @param string|array|null $key
     * @return null
     */
    public function param($key = null)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    /**
     * @param mixed $target
     * @return mixed
     */
    function dispatch($target = null) {
        foreach($this->params as $field => $val) {
            if (preg_match('/^[A-Z]/', $field) && preg_match('/^[a-z]{1,5}\-/', $val)) {

                $list = explode(',', $val);
                if(sizeof($list) == 1) {
                    $this->params[$field] = FireRequest(new \DataModel_ResolveObject($val));
                } else {
                    if(sizeof($list) == 2 && $list[1] == '') { // финальная запятая - это сигнатура 1 элемента
                        array_pop($list);
                    }
                    $this->params[$field] = $list;
                    foreach($this->params[$field] as $key => $value) {
                        if(preg_match('/^[a-z]{1,3}\-/', $value)) {
                            $this->params[$field][$key] = FireRequest(new \DataModel_ResolveObject($value));
                        }
                    }

                }


            }
        }

        return parent::dispatch($target);

    }
}