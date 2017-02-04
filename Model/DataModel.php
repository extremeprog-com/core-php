<?php

trait DataModel {

    public $id;
    public $version;

    static $_cache;

    /**
     * Генерация класса запроса вычисляемого поля
     * @return	void
     */
    public static function createCalculatedFieldRequestClass() {
        CatchEvent(\_OS\Core\System_InitFiles::class);
        $class = static::class . '_GetFieldRequest';
        $content = <<<FILE
<?php
class $class extends \_OS\Request {
    public \$field = '';
    
    public function __construct(\$field) {
        \$this->field = \$field;
    }
}
FILE;
        file_put_contents(PROJECTTMP . '/' . $class . '.php', $content);
    }

    function get($field) {
        if (!isset(static::$fields[$field])) {
            throw new Exception('Field '.$field.' does not exists in class '.static::class);
        }

        if($field == 'id') {
            return $this->id;
        }

        // Если поле вычисляемое
        if (!isset($this->data[$field]) && isset(static::$fields[$field]['calculated']) && static::$fields[$field]['calculated'] === true) {
            $request_class = static::class . '_GetFieldRequest';
            $this->data[$field] = FireRequest(new $request_class($field));
        }

        $ret = isset($this->data[$field]) ? $this->data[$field] : static::$fields[$field]['default'];

        if (isset($this->data[$field]) && $this->data[$field] !== static::$fields[$field]['default']) {
            switch (static::$fields[$field]['type']) {
                case 'int':
                    $ret = (int) $ret;
                    break;

            }
        }
        return $ret;
    }

    /**
    * @param string $field
    * @param mixed $value
    * @return $this
    * @throws Exception
    */
    function set($field, $value) {
        if(!isset(static::$fields[$field])) {
            throw new Exception('Field '.$field.' does not exists in class '.static::class);
        }
        if (isset(static::$fields[$field]['calculated']) && static::$fields[$field]['calculated'] === true) {
            throw new Exception('Cant set value for calculated field '.$field.' in class '.static::class);
        }
        $fail = false;
        $errors = [];

        if (is_null($value)) {
            if (!is_null(static::$fields[$field]['default'])) {
                throw DataModelException::create([
                    $field => "Поле неправильного типа."
                ]);
            }
        } else {
            switch (static::$fields[$field]['type']) {
                case 'object': {
                    if (!is_object($value)) $fail = true;
                    break;
                }
                case 'array': {
                    if (!is_array($value)) $fail = true;
                    break;
                }
                case 'bool': {
                    if (!is_bool($value)) $fail = true;
                    break;
                }
                case 'int': {
                    if (!is_numeric($value)) $fail = true;
                    break;
                }
                case 'float': {
                    if (!is_float($value)) $fail = true;
                    break;
                }
                case 'string': {
                    if (!is_string($value)) $fail = true;
                    break;
                }
                case 'date': {
                    if (!is_numeric($value)) $fail = true;
                    break;
                }
                case 'datetime': {
                    if (!is_string($value)) $fail = true;
                    break;
                }
                case 'timestamp': {
                    if (!filter_var($value, FILTER_VALIDATE_INT)) $fail = true;
                    break;
                }
                default: {
                    $fail = true;
                    if (class_exists(static::$fields[$field]['type']) && $value instanceof static::$fields[$field]['type']) {
                        $fail = false;
                    }
                    break;
                }
            }

            if ($fail) {
                throw DataModelException::create([
                    $field => "Поле неправильного типа."
                ]);
            }

            $errors = $this->validateField($field, $value);
        }

        if (!$errors) {
            if ($field === 'id') {
                $this->id = $value;
            }

            $this->data[$field] = $value;
        } else {

            Log::security([
                'message'   => $m = 'Возникли ошибки при проверке данных.',
                'data'      => $this->getData(),
            ], __CLASS__);

            throw DataModelException::create($errors);
        }
        return $this;
    }

    /**
     * @param $field
     * @param $value
     * @return array
     */
    function validateField($field, $value) {
        $methods = isset(static::$fields[$field]['validate']) ? static::$fields[$field]['validate'] : [];
        $errors = [];

        if (is_array($methods)) {
            foreach ($methods as $method) {
                if (method_exists(__CLASS__, $method)) {
                    $error = call_user_func([__CLASS__, $method], $value);
                    if (is_string($error)) {
                        return [$field => $error];
                    }
                }
            }
        }
        return $errors;
    }

    /**
     * @param $values
     * @return $this
     * @throws DataModelException
     */
    function setMany($values) {
        $errors = [];
        foreach ($values as $field => $value) {
            try {
                $this->set($field, $value);
            } catch (DataModelException $e) {
                $errors = array_merge($errors, $e->getErrors());
            }
        }
        if ($errors) {
            throw DataModelException::create($errors);
        }
        return $this;
    }

    function &access($field) {
        if(!isset(static::$fields[$field])) {
            throw new Exception('Field '.$field.' does not exists in class '.static::class);
        }
        if (isset(static::$fields[$field]['calculated']) && static::$fields[$field]['calculated'] === true) {
            throw new Exception('Cant access to calculated field in class ' . static::class);
        }

        if(!isset($this->data[$field])) {
            $this->data[$field] = static::$fields[$field]['default'];
        };
        return $this->data[$field];
    }

    function getData($skip_calculated = false) {
        $data = $this->data;
        foreach(static::$fields as $field => $params) {
            // Skip calculated fields if need
            if ($skip_calculated && isset(static::$fields[$field]['calculated']) && static::$fields[$field]['calculated'] === true) {
                continue;
            }
            $data[$field] = $this->get($field);
            /*
            if(isset(static::$fields[$field]['default']) && !isset($data[$field])) {
                $data[$field] = static::$fields[$field]['default'];
            }
            if(isset($data[$field])) {
                switch($params['type']) {
                    case "int": $data[$field] = (int)$data[$field]; break;
                }
            }*/
        }
        return $data + ['id' => $this->id];
    }

    /**
     * Валидация полей
     *
     * @return $this
     * @throws Exception
     * @throws DataModelException
     */
    function validate() {
        $errors = [];
        foreach ($this->getData() as $field => $value) {
            $error = false;

            // Если не нужно валидировать
            if (!isset(self::$fields[$field]['validate']))
                continue;

            // Определяем метод валидации
            $method = 'validate' . implode('', array_map(function ($item) { return ucfirst($item); }, explode('_', $field)));
            if (!method_exists(__CLASS__, $method)) {
                Log::error([
                    'message'   => $m = 'Cant find validator method for field ' . $field,
                    'field'     => $field,
                    'validator' => __CLASS__ . '::' . $method,
                ], __CLASS__);

                throw new Exception($m);
            }

            $error = call_user_func([__CLASS__, $method], $value);

            // Если была ошибка
            if (is_string($error))
                $errors[$field] = $error;
        }

        // Если были какие-то ошибки, прерываем
        if ($errors) {
            Log::security([
                'message'   => $m = 'Возникли ошибки при проверке данных.',
                'data'      => $this->getData(),
            ], __CLASS__);

            throw DataModelException::create($errors);
        }

        return $this;
    }

    static function generateDataClass() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        if(defined(static::class."::_prefix")) {
            if(!isset(static::$fields['id'])) {
                throw new Exception('class with ::_prefix must have ->id field ('.static::class.')');
            }
            if(!method_exists(static::class, '_load')) {
                throw new Exception('class with ::_prefix must have ->_load() method ('.static::class.')');
            }
        }

        // check signature
        $rp = new ReflectionProperty(static::class, 'data');
        if(strpos($rp->getDocComment(), "@var ".static::class."Data") === false) {
            throw new Exception("Cannot find PHPDoc signature /** @var ".static::class."Data */ for field \$data");
        }

        $fields = [];
        foreach(static::$fields as $field => $params) {
            $fields[] = "    /** @var ".str_pad($params['type'], 8, " ")." */  public $$field;";
        }

        `mkdir -p tmp/DataModel`;
        file_put_contents("tmp/DataModel/".static::class."Data.php", "<?php\n\nclass ".static::class."Data {\n\n".implode("\n", $fields)."\n}");
    }

    static function resolveSelf() {
        $Request = CatchRequest(defined(static::class.'::_prefix')?[DataModel_ResolveObject::class => [ 'prefix' => static::_prefix ]]:[]);

        /** @var DataModel_ResolveObject $Request */
        return self::getVirtualOrReal($Request->id);
    }

    static function getVirtualOrReal($id) {
        if(!isset(static::$_cache[$id])) { 
            static::$_cache[$id] = unserialize('O:'.strlen(static::class).':"'.static::class.'":1:{s:2:"id";s:'.strlen($id).':"'.$id.'";}');
        }
        return static::$_cache[$id];
    }

    function isVirtual() {
        return empty($this->data);
    }

//
//    public function offsetExists($offset) {
//        return isset(self::$fields[$offset]);
//    }
//
//    public function offsetGet($offset) {
//        return $this->get($offset);
//    }
//
//    public function offsetSet($offset, $value) {
//        $this->set($offset, $value);
//    }
//
//    public function offsetUnset($offset){
//        unset($this->data[$offset]);
//    }
//
}
