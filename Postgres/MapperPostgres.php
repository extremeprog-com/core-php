<?php

trait MapperPostgres {
    protected $_just_created;

    static $type_map = [
        'int'       => 'integer',
        'float'     => 'float',
        'double'    => 'float',
        'string'    => 'text',
        'date'      => 'int',
        'datetime'  => 'timestamp(6)',
        'bool'      => 'boolean',
        'blob'      => 'text',
    ];

    /**
     * Генераций ID по sequence
     *
     * @param string $field
     * @return int
     */
    protected static function generateID($field = 'id') {
        // assert('isset(static::$table)');
        $Stmt = DB::connect()->prepare("SELECT nextval('" . static::$table . "_seq".($field=='id'?'':'_'.$field)."');");
        $Stmt->execute();
        return $Stmt->fetchColumn();
    }

    /**
     * Получение строки по отдельным полям
     *
     * @param array $fields
     * @return DataModel|null
     */
    protected static function getItemByFields(array $fields) {
        // assert('$fields');
        $list = self::getList($fields);
        return isset($list[0]) ? $list[0] : null;
    }

    /** @return self */
    public static function create() {
        $Object = new static;
        $Object->id = self::generateID();
        $Object->_just_created = true;
        return $Object;
    }

    public static function fetch($id) {
        $Obj = static::getById($id);
        if (!$Obj->data) {
            $Obj->id = $id;
            $Obj->_just_created = true;
        }
        return $Obj;
    }

    /**
     * @param array $filters
     * @param array|bool $order_by
     * @param int|bool $limit
     * @return array
     */
    static function  getList($filters = [], $order_by = false, $limit = false) {

        $order = [];
        if ($order_by) {
            if(!is_array($order_by)) {
                $order_by = [ $order_by => '' ];
            }
            foreach ($order_by as $field => $value) {
                if (isset(static::$fields[$field])) {
                    $order[] = ('(data::json->>\''.$field.'\')::VARCHAR' .
                            ( static::$fields[$field]['type'] == 'int' ?  '::INTEGER' : '' )) . ' ' . $value;
                }
            }
        }

        $params = [];

        $ph = DB::connect(isset(self::$external_dsn)?self::$external_dsn:null)->prepare('select * from '.static::$table.($filters?' where '
                .implode(
                    ' and ',
                    array_map(
                        function($field, $value) use (&$params) {
                            if(!is_array($value)) {
                                if($value instanceof check) {
                                    $value = [ $value ];
                                } else {
                                    $value = [ check::eq($value) ];
                                }
                            }
                            $sql_where_expressions = [];
                            foreach($value as $check) {
                                /** @var check $check */
                                switch($check->method) {
                                    case 'eq' : {
                                        $params[$key = ":_v".sizeof($params)] = $check->value;
                                        $sql_where_expressions[] = "(data::json->>'$field')::VARCHAR = ".$key;
                                        break;
                                    }
                                    case 'more': {
                                        $params[$key = ":_v".sizeof($params)] = $check->value;
                                        $sql_where_expressions[] = "(data::json->>'$field')::VARCHAR::INTEGER > ".$key;
                                        break;
                                    }
                                    case 'less': {
                                        $params[$key = ":_v".sizeof($params)] = $check->value;
                                        $sql_where_expressions[] = "(data::json->>'$field')::VARCHAR::INTEGER < ".$key;
                                        break;
                                    }
                                    case 'oneOf': {
                                        if (is_array($check->value) && count($check->value)) {
                                            $in = [];
                                            foreach ($check->value as $i => $v) {
                                                $params[$key = ":_v".sizeof($params)."_".$i] = $v;
                                                $in[] = $key;
                                            }
                                            $sql_where_expressions[] = "(data::json->>'$field')::VARCHAR in (".implode(',', $in).")";
                                        }
                                        break;
                                    }
                                    default: throw new Exception("Don't know how to translate check::{$check->method} to PSQL");
                                }
                            }
                            return implode(' and ', $sql_where_expressions);
                        },
                        array_keys($filters),
                        array_values($filters)
                    )
                ):'')
            . ($order ? ' order by ' . implode(' , ', $order) : '')
            . ($limit?(is_array($limit)?" LIMIT ".$limit[1]." OFFSET ".$limit[0]:" LIMIT ".$limit):'')
        );
        $ph->execute($params);
        $list = [];
        foreach($ph->fetchAll() as $fields) {
            $o = self::getVirtualOrReal($fields['id']);
            if (!$o->data)
                $o->data = JSON::decode($fields['data']);

            $list[] = $o;
        }
        return $list;
    }

    /**
     * @param array $fields
     * @return mixed
     */
    static function resolveObjectFromArray($fields) {
        $o = self::getVirtualOrReal($fields['id']);
        if (!$o->data) $o->data = JSON::decode($fields['data']);
        return $o;
    }

    static function saveObject() {
        /** @var DataModel_SaveRequest $Request */
        $Request = CatchRequest([DataModel_SaveRequest::class => [ 'class' => static::class ]]);
        $Request->Object->save();
        return true;
    }

    /**
     * Save the data in database
     *
     * @return $this
     */
    function save() {
        $all_fields = ['id' => $this->id] + ['data' => JSON::encode($this->getData(true))];
        if($this->_just_created) {
            $ps = DB::connect(isset(self::$external_dsn)?self::$external_dsn:null)->prepare("
                insert into ".static::$table."(".implode(",", array_keys($all_fields)).")
                values (".implode(", ", array_map(function($field) {return ":$field";}, array_keys($all_fields))).")
            ");
        } else {
            $ps = DB::connect(isset(self::$external_dsn)?self::$external_dsn:null)->prepare("
                update ".static::$table."
                set ".implode(", ", array_map(function($field) {return "$field = :$field";}, array_keys($all_fields)))."
                where id = :id
            ");
        }
        foreach ($all_fields as $field => $value) {
            if (is_bool($value)) {
                $ps->bindValue($field, $value, \PDO::PARAM_BOOL);
            } else {
                $ps->bindValue($field, $value);
            }
        }
        // Little cheat to get calculated data to be displayed in api call request
        $this->data = $this->getData();
        $ps->execute();
        $this->_just_created = false;
        return $this;
    }

    function delete() {
        assert('$this->id > 0');
        $Stmt = DB::connect()->prepare('DELETE FROM ' . static::$table . ' WHERE id = :id');
        $Stmt->execute(['id' => $this->id]);
        unset(static::$_cache[$this->id]);
        return $this;
    }

    /**
     *
     */
    function _load() {
        self::getById($this->id);
    }

    /**
     * @param int $id
     * @return mixed
     */
    static function getById($id) {
        $Stmt = DB::connect()->prepare('SELECT * FROM ' . static::$table . ' WHERE id = :id LIMIT 1');
        $Stmt->execute(['id' => $id]);
        $row = $Stmt->fetch();
        $o = self::getVirtualOrReal($id);

        if ($row && !$o->data) {
            $o->data = JSON::decode($row['data']);
        }

        // Hacky hack. Id must be type of fields declared
        if (isset(static::$fields['id']) && static::$fields['id']['type'] === 'int') {
            $o->id = (int) $o->id;
        }
        return $o;
    }

    /**
     * Increment counters in database
     *
     * @param array $counters
     * @return $this
     */
    public function increment(array $counters) {
        if (!$counters) {
            Logger()->error('Empty counters for increment value in database', __CLASS__);
            return;
        }

        // Fetch data if
        if (!$this->data) {
            if ($Obj = self::getById($this->id))
                $this->data = $Obj->getData(true);
            else
                return;
        }

        // Increment data keys
        foreach ($this->data as $key => $val) {
            if (isset($counters[$key]))
                $this->data[$key] += $counters[$key];
        }

        return $this->save();
    }

    /**
     * Запись (создание или обновление) строки в таблице
     * Если в классе определено свойство static array $counter_keys,
     * то указанные ключи обновляются как счетчики key = key + value (происходит инкремент)
     *
     * @param array $fields
     * @return $this
     */
    public function store(array $fields) {
        if ($Obj = self::getById($this->id)) {
            $data           = $Obj->getData(true);
            $has_counters   = property_exists(__CLASS__, 'counter_keys') ;
            foreach ($fields as $key => $field) {
                $data[$key] = $has_counters && in_array($key, self::$counter_keys)
                    ? $data[$key] + $field
                    : $field;
            }
            $this->data = array_merge($Obj->data, $data);
        } else {
            $this->_just_created = true;
            $fields['id'] = $this->id;
            // дописываем неопределенные индексы
            foreach (self::$fields as $key => $field) {
                if (!isset($fields[$key]))
                    $fields[$key] = $field['default'];
            }
            $this->data = $fields;
        }
        return $this->save();
    }


    /**
     * Генерация таблицы в базе данных, соответствующей модели, если таблица не создана, создается
     *
     * @todo Modify index declaration in database if code changes
     * @uses DB
     * @throws Exception
     * @return void
     */
    static function generateTable() {
        CatchEvent([PostgresStateDaemon::class."Host" => \_OS\Core\System_InitData::class]);

        $max_wait = 120;
        while(!in_array($state = PostgresStateDaemon::getState('master')['state'], ['base_ready', 'slave_init', 'make_slave', 'check_slave'])) {
            if(!isset($$state)) {
                $$state = 0;
            }
            if($$state++%5 == 0) {
                echo '[waiting master for state "base_ready", current is ' . $state."]\n";
            }
            sleep(1);
            if(!$max_wait--) break;
        }

        // Карта соответствий объявляемых типов и создания в базе

        $indexes = [];
        $sequences = [];

        // Holy shit try connection and wait for a while
        $conn = DB::connect(isset(self::$external_dsn)?self::$external_dsn:null);

        // Create table
        $conn->query('CREATE TABLE IF NOT EXISTS ' . self::$table . ' (id integer not null primary key, data text not null default \'\');');

        // Create VIEW
        //  $conn->query('DROP VIEW IF EXISTS ' . self::$table . '_view');

        $sql = 'CREATE OR REPLACE VIEW ' . self::$table . '_view AS SELECT ';
        foreach (static::$fields as $field => $params) {
            if (!isset($params['calculated']) || !$params['calculated']) {                
                $cast = 'varchar';
                if (isset(self::$type_map[$params['type']])) {
                    $cast = self::$type_map[$params['type']];
                }
                $sql .= '(data::json->>\'' . $field . '\')::' . $cast . ' AS ' . $field . ', ';
            }
        }
        $sql = trim($sql, ', ');
        $sql .= ' FROM ' . self::$table . ' ORDER BY id ASC';
        $conn->query($sql);

        // Prepare sequences list to be created
        foreach (self::$fields as $field => $params) {
            if (isset($params['sequence']) && $params['sequence']) {
                $seq_name = self::$table."_seq".($field=='id'?'':'_'.$field);
                $sequences[$seq_name] = "CREATE SEQUENCE ".$seq_name." INCREMENT BY 1 START WITH 1;";
            }
        }

        //getting all sequences from scheme
        $stmt = $conn->prepare("
            SELECT c.relname FROM pg_class c WHERE c.relkind = 'S'
        ");
        $stmt->execute();
        $current_sequences = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        //creating undefined sequences for table that doesn't exist
        foreach ($sequences as $name => $seq_sql) {
            if (in_array($name, $current_sequences))
                continue;

            $conn->query($seq_sql);
        }

        //getting all table fields
        $stmt = $conn->prepare('
            SELECT column_name
              FROM information_schema.columns
             WHERE table_name = :table
        ');
        $stmt->execute([':table' => self::$table]);
        $real_fields = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // Getting all indexes for current table        
        $stmt = $conn->prepare('
            SELECT a.attname AS column_name 
            FROM pg_class t, pg_class i, pg_index ix, pg_attribute a
            WHERE t.oid = ix.indrelid AND i.oid = ix.indexrelid AND a.attnum = ANY(ix.indkey) AND t.relname = :table
        ');
        $stmt->execute([':table' => self::$table]);
        $current_indexes = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // Creating undefined indexes for table that doesn't exist
        foreach ($indexes as $name => $sql) {
            if (in_array($name, $current_indexes))
                continue;

            $conn->query($sql);
        }
    }

    public static function _PostgresStorageMakeCandidateRole() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        if(isset(self::$external_dsn)) {
            return;
        }

        $template = <<<EOF
<?php
class PostgresCandidateHost extends HostRole {

}
EOF;

        file_put_contents(PATH_TMP."/PostgresCandidateHost.php", $template);
    }

    static function _PostgresStorageMakeMasterRole() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        if(isset(self::$external_dsn)) {
            return;
        }

        $RoleClass = __TRAIT__."Host";
        $template = <<<EOF
<?php

class $RoleClass extends HostRole {

}

EOF;

        file_put_contents(PATH_TMP."/{$RoleClass}.php", $template);
    }

    static function _PostgresStorageMakeSlaveRole() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        if(isset(self::$external_dsn)) {
            return;
        }

        $RoleClass = __TRAIT__."SlaveHost";
        $template = <<<EOF
<?php

class $RoleClass extends HostRole {

}

EOF;
        file_put_contents(PATH_TMP."/{$RoleClass}.php", $template);
    }

    /**
     * Генерация событий добавления хоста для старт демона при мультироли
     */
    public static function runDaemonUnderMultirole() {
        CatchEvent(System_InitConfigs);
        $config = Project::getConfig();
        if (isset($config['multirole']) && $config['multirole']) {
            FireEvent(new PostgresStateDaemonHost_Add(Taskman::getHostname()));
        }
    }
}
