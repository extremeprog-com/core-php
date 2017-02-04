<?php
/**
 * Взаимодействие с Redis
 *
 * @link http://code.google.com/p/redis/wiki/CommandReference
 * @use Redis extension
 */
class RedisClient {

    /** результирующие элементы будут содержать содержать сумму всех весов */
    const Z_SETS_AGGREGATE_SUM = 'sum';
    /** результирующие элементы будут содержать минимальный из результирующих весов  */
    const Z_SETS_AGGREGATE_MIN = 'min';
    /** результирующие элементы будут содержать максимальный из результирующих весов */
    const Z_SETS_AGGREGATE_MAX = 'max';
    
    /**
     * @param array $server
     * @return void
     */
    public function __construct(array $server) {
        $this->host = $server['host'];
        $this->port = isset($server['port']) ? (int)$server['port'] : self::REDIS_DEFAULT_PORT;
        $this->db = isset($server['db']) ? (int)$server['db'] : 0;
        $this->Redis = new Redis();
        $this->Redis->pconnect($this->host, $this->port, 15);
        $this->Redis->select($this->db);
        
        if(isset($server['instance'])) {
            $this->Redis->setInstance($server['instance']);
        }
    }
    
    public static function instance($instance) {
        if (!is_array($instance)) {
            $config = RedisInstance::getConfig($instance);
        } else {
            $config = $instance;
        }
        return new RedisClient($config);
    }
    
    public function getStorageKeySerializedValue($key) {
        return new StorageKeyRedisSerializedValue($this, $key);
    }
    
    public function getStorageKeyValue($key) {
        return new StorageKeyRedisValue($this, $key);
    }
    
    public function getStorageKeyList($key) {
        return new StorageKeyRedisList($this, $key);
    }
    
    public function getStorageKeyZList($key) {
        return new StorageKeyRedisZSet($this, $key);
    }

    /**
     * Возвращает true, если $key является валидным
     *
     * @param string $key
     * @return bool
     */
    public function isValidKey($key) {
        return $this->Redis->isValidKey($key);
    }

    /**
     * Получить объект Redis
     *
     * @param int $tries
     * @return Redis
     */
    private function redis($try = 1) {
        return $this->Redis;
//        if (!isset($this->Redis)) {
//            $b = BENCHMARK();
//    //        if ($b) {Benchmark::start($this->benchmarkName, $bench=('connect ' . $this->host . ':' . $this->port));}
//            do {
//                try {
//                    $this->connect();
//                    break;
//                } catch (RedisException $e) {
//                    ExampleZmq()->warn("Redis can not connect to ({$this->host}, {$this->port}). Attempt to reconnect $try from " . self::MAX_TRIES . "...");
//                }
//                $this->close();
//                usleep(50000 * $try);
//            } while ($try++ < self::MAX_TRIES);
//            if ($try >= self::MAX_TRIES) {
//                ExampleZmq()->error("Redis can not connect to ({$this->host}, {$this->port}). No more tries left...");
//                throw new RedisException("Can't connect");
//            }
//    //        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
//        }
//
//        if ($this->ping()) {
//            return $this->Redis;
//        }
//
//        $this->close();
//
//        if ($try < self::MAX_TRIES) {
//            ExampleZmq()->warn("Redis lost connection ({$this->host}, {$this->port}). Attempt to reconnect $try from " . self::MAX_TRIES . "...");
//        } else if ($try >= self::MAX_TRIES) {
//            ExampleZmq()->error("Redis lost connection ({$this->host}, {$this->port}). No more tries left...");
//            throw new RedisException("Lost connection");
//        }
//        usleep(50000 * $try);
//        return $this->redis(++$try);
    }

    /**
     * @param string $key
     * @return string
     */
    public function type($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
////        if ($b) {Benchmark::start($this->benchmarkName, $bench=('TYPE '.$key));}
        $type = $R->type($key);
////        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        switch ($type) {
            case 0:
                return 'none';
            case 1:
                return 'string';
            case 2:
                return 'set';
            case 3:
                return 'list';
        }
        return $type;
    }

    /**
     * Получить значение бита
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public function getBit($key, $bit) {
        $R = $this->Redis;
        //$b = BENCHMARK();
        //if ($b) {Benchmark::start($this->benchmarkName, $bench=('SET '.$key.' ['.substr($value, 0, 255).']'));}
        $result = $R->{__FUNCTION__}($key, $bit);
        //if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Установить значение бита
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public function setBit($key, $bit, $value) {
        $R = $this->Redis;
        //$b = BENCHMARK();
        //if ($b) {Benchmark::start($this->benchmarkName, $bench=('SET '.$key.' ['.substr($value, 0, 255).']'));}
        $result = $R->{__FUNCTION__}($key, $bit, $value);
        //if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Установить значение ключа
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public function set($key, $value) {
        $R = $this->Redis;
        //$b = BENCHMARK();
        //if ($b) {Benchmark::start($this->benchmarkName, $bench=('SET '.$key.' ['.substr($value, 0, 255).']'));}
        $result = $R->set($key, $value);
        //if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Установить значение ключа
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public function setJSON($key, $value) {
        $R = $this->Redis;
        //$b = BENCHMARK();
        //if ($b) {Benchmark::start($this->benchmarkName, $bench=('SET '.$key.' ['.substr($value, 0, 255).']'));}
        $result = $R->set($key, JSON::encode($value));
        //if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Установить значение ключа, если его еще не существует
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setnx ($key, $value) {
        $R = $this->Redis;
        //$b = BENCHMARK();
        //if ($b) {Benchmark::start($this->benchmarkName, $bench=('SETNX '.$key.' ['.substr($value, 0, 255).']'));}
        $result = $R->setnx($key, $value);
        //if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Установить значение не существующего ключаы
     * @param string $key
     * @param mixed  $value
     */
    public function add($key, $value) {
        $R = $this->Redis;
////        $b = BENCHMARK();
////        if ($b) {Benchmark::start($this->benchmarkName, $bench=('ADD '.$key.' ['.substr($value, 0, 255).']'));}
        $result = $R->add($key, $value);
////        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * @return boolean
     */
    public function ping() {
        if (!$this->Redis) {
            return false;
        } else {
            try {
                return (bool) $this->Redis->ping();
            } catch (Exception $e) {
                return false;
            }
        }
    }

    /**
     * Получить значение по ключу
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('GET '.$key));}
        $result = $R->get($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Получить значение по ключу
     * @param string $key
     * @return mixed
     */
    public function getJSON($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('GET '.$key));}
        $result = JSON::decode($R->get($key));
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Установить новое значение и получить старое
     * @param string $key
     * @param mixed  $value
     * @return mixed
     *         старое значение
     */
    public function getset($key, $value) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('GETSET '.$key));}
        $result = $R->getset($key, $value);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Проверить существование ключа
     * @param string $key
     * @return bool
     */
    public function exists($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('EXISTS '.$key));}
        $result = $R->exists($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Удалить ключ
     * @param string $key
     * @return mixed
     */
    public function delete($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('DELETE '.$key));}
        $result = $R->delete((string)$key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Инкремент
     * @param string $key
     * @param int $value [optional]
     * @return int
     */
    public function incr($key, $value = null) {
        if ($value === 0) {
            return $this->get($key);
        }
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('INCR '.$key));}
        if (!is_null($value)) {
            $result = $R->incr($key, $value);
        } else {
            $result = $R->incr($key);
        }
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Декремент
     * @param string $key
     * @param int $value [optional]
     * @return int
     */
    public function decr($key, $value = null) {
        if ($value === 0) {
            return $this->get($key);
        }
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('DECR '.$key));}
        if (!is_null($value)) {
            $result = $R->decr($key, $value);
        } else {
            $result = $R->decr($key);
        }
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Старт сессии
     *
     * @param array $keys
     * @return array
     */
    public function multi() {
        $args = func_get_args();
        $result = call_user_func_array(array($this->Redis,__FUNCTION__), $args);
        return $result;
    }

    /**
     * Получить ключи по шаблону
     *
     * @param array $keys
     * @return array
     */
    public function keys($pattern) {
        $args = func_get_args();
        $result = call_user_func_array(array($this->Redis,__FUNCTION__), $args);
        return $result;
    }

    /**
     * Запустить команды в сессии
     *
     * @param array $keys
     * @return array
     */
    public function exec() {
        $args = func_get_args();
        $result = call_user_func_array(array($this->Redis,__FUNCTION__), $args);
        return $result;
    }

    /**
     * Отметить для CAS
     *
     * @param array $keys
     * @return array
     */
    public function watch($key) {
        $args = func_get_args();
        $result = call_user_func_array(array($this->Redis,__FUNCTION__), $args);
        return $result;
    }

    /**
     * Отменить сессию
     *
     * @param array $keys
     * @return array
     */
    public function discard() {
        $args = func_get_args();
        $result = call_user_func_array(array($this->Redis,__FUNCTION__), $args);
        return $result;
    }

    /**
     *
     * @param array $keys
     * @return array
     */
    public function getRange($key, $start, $end) {
        $args = func_get_args();
        $result = call_user_func_array(array($this->Redis,__FUNCTION__), $args);
        return $result;
    }

    /**
     *
     * @param array $keys
     * @return array
     */
    public function setRange($key, $offset, $value) {
        $args = func_get_args();
        $result = call_user_func_array(array($this->Redis,__FUNCTION__), $args);
        return $result;
    }

    /**
     * Получить значения ключей в формате Memcached
     *
     * redis extension возвращает результат в неудобом виде (числовые индексы), так что
     * обрабатываем результат и возвращаем так же, как это делает pecl/memcached
     *
     * @param array $keys
     * @return array
     */
    public function getMultiple(array $keys) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('MULTI-GET ['.json_encode($keys).']'));}
        $result = $R->getMultiple($keys);
        if (is_array($result)) {
            $result = array_combine($keys, $result);
        } else {
            $result = false;
        }
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Получить значения ключей в формате Redis
     * @param array $keys
     * @return array
     */
    public function getMultipleRedis(array $keys) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('MULTI ['.json_encode($keys).']'));}
        $result = $R->getMultiple($keys);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Получить ключи, соответствующие шаблону
     * @param string $pattern
     * @param int $limit [optional]
     * @return array
     */
    public function getKeys($pattern, $limit = null) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('KEYS ['.$pattern.']'));}
        if (is_null($limit)) {
            $result = $R->getKeys($pattern);
        } else {
            // server was modified here? there is no argument limit in keys command in new redis server
            $result = array_slice($R->getKeys($pattern), 0, $limit);
        }
        if (!is_array($result)) {
            Logger()->warn("Redis::getKeys($pattern,$limit) returned not an array: " . json_encode($result));
            return array();
        }
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Переименовать ключ
     * @param string $oldKey
     *        старое название
     * @param string $newKey
     *        новое название
     */
    public function rename($oldKey, $newKey) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('RENAME ['.$oldKey.'], ['.$newKey.']'));}
        $result = $R->srename($oldKey, $newKey);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return true;
    }

    /**
     * Вставка элемента в конец списка, использовать lRPush
     *
     * @param string $key
     *        ключ
     * @param string $value
     *        значение
     * @return bool
     */
    public function lPush($key, $value) {
        return $this->lRPush($key, $value);
    }

    /**
     * Добавить элемент к голове списка
     * @param string $key
     * @param string $value
     */
    public function lLPush($key, $value) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('LPUSH '.$key.' '.json_encode(substr($value, 0, 255))));}
        $result = $this->Redis->lPushLeft($key, $value);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Добавить элемент к хвосту списка
     * @param string $key
     * @param string $value
     */
    public function lRPush($key, $value) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('RPUSH '.$key.' '.json_encode(substr($value, 0, 255))));}
        $result = $this->Redis->rPush($key, $value);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Добавляет элемент в множество
     * @param string $key
     * @param string $member
     */
    public function sAdd($key, $member) {
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=(__FUNCTION__.' '.$key));}
        $result = $this->Redis->{__FUNCTION__}($key, $member);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;        
    }
    
    /**
     * Удаляет элемент из множества
     * @param string $key
     * @param string $member
     */
    public function sRemove($key, $member) {
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=(__FUNCTION__.' '.$key));}
        $result = $this->Redis->{__FUNCTION__}($key, $member);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;        
    }
    public function sRem($key, $member){
        // alias for RedisClient::sRemove()
        return $this->sRemove($key, $member);
    }
    
    /**
     * Проверяет наличие элемента в множестве
     * @param string $key
     * @param string $member
     */
    public function sIsMember($key, $member) {
        // alias for RedisClient::sContains()
        return $this->sContains($key, $member);
    }
    public function sContains($key, $member){
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=(__FUNCTION__.' '.$key));}
        $result = $this->Redis->{__FUNCTION__}($key, $member);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;        
    }
    
    /**
     * Возвращает все элементы множества
     * @param string $key
     */
    public function sMembers($key) {
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=(__FUNCTION__.' '.$key));}
        $result = $this->Redis->{__FUNCTION__}($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;        
    }

    /**
     * Возвращает количество элементов во множестве
     * @param string $key
     * @return int
     */
    public function sCard($key) {
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('sCard '.$key));}
        $result = $this->Redis->sCard($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Возвращает количество элементов во множестве
     * @param $oldKey
     * @param $newKey
     * @param $member
     * @return bool
     */
    public function sMove($oldKey, $newKey, $member) {
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('sCard '.$key));}
        $result = (bool) $this->Redis->smove($oldKey, $newKey, $member);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * @param $key -ключ
     * @param int $offset -смещение
     * @param int $limit - если 0 - все элементы (лучше не использовать)
     * @return array $result
     */
    public function sort($key, $offset=0, $limit=0){
//        $b = BENCHMARK();
        $limit = ($limit==0)? $this->sCard($key) : $limit;
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('sort '.$key));}
        $options = array(
//            'by' => null,
            'limit' => array($offset, $limit),
            'sort' => 'asc',
            'alpha' => false

        );
        $result = $this->Redis->sort($key,$options);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }
    
    // @todo: добавить остальные методы для SETS 
    
    /**
     * Добавление нового элемента в SORTED SET
     * @param string $key
     * @param float $score
     * @param string $value 
     */
    public function zAdd($key, $score, $value) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zAdd '.$key));}
        $result = $this->Redis->zAdd($key, $score, $value);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Извлечение среза из SORTED SET
     * @param string $key
     * @param int $start
     * @param int $end
     * @param bool $withscores
     */
    public function zRange($key, $start, $end, $withscores = false) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zRange '.$key));}
        $result = $this->Redis->zRange($key, $start, $end, $withscores);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    
    public function zReverseRange($key, $start, $end, $withscores = false) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zReverseRange '.$key . ', offset:' . $start . ', limit: ' . $end ));}
        $result = $this->Redis->zReverseRange($key, $start, $end, $withscores);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     *
     * @param string $key
     * @param int $start
     * @param int $end
     * @return array
     */
    public function zRangeByScore($key, $start, $end, $offset=null, $count=null, $withscores=false) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zRangeByScore '.$key));}
        $opts = array('withscores'=>$withscores);

        if(is_null($offset))
            $offset = 0;

        if(is_null($count))
            $count = -1;

        if($offset!=0||$count!=-1)
            $opts['limit'] = array($offset, $count);

        $result = $this->Redis->zRangeByScore($key, $start, $end, $opts);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * @param $key
     * @param $start
     * @param $end
     * @param $withscores
     * @return mixed|array
     */
    public function zRevRangeByScore($key, $start, $end, $offset=null, $count=null, $withscores=false){
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zRevRangeByScore '.$key));}
        $opts = array('withscores'=>$withscores);

        if(is_null($offset))
            $offset = 0;

        if(is_null($count))
            $count = -1;

        if($offset!=0||$count!=-1)
            $opts['limit'] = array($offset, $count);

        $result = $this->Redis->zRevRangeByScore($key, $start, $end, $opts);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Сохранение пересечения множеств SORTED SET по ключу
     * @param string $destKey
     * @param array $sourceKeys
     * @param array $sourceWeights (optional)
     * @param int $aggregate (optional)
     */
    public function zInterStore($destKey, $sourceKeys, $sourceWeights = null, $aggregate = RedisClient::Z_SETS_AGGREGATE_SUM) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zInterStore '.$destKey));}
        foreach($sourceKeys as $key=>$val)
            $sourceKeys[$key] = (string)$val;
        $result = $this->Redis->zInterStore($destKey, $sourceKeys, $sourceWeights, $aggregate);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Сохранение объединения множеств SORTED SET по ключу
     * @param string $key
     * @param float $score
     * @param string $value 
     */
    public function zUnionStore($destKey, $sourceKeys, $sourceWeights, $aggregate = RedisClient::Z_SETS_AGGREGATE_SUM) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zUnionStore '.$destKey));}
        foreach($sourceKeys as $key=>$val)
            $sourceKeys[$key] = (string)$val;
        $result = $this->Redis->zUnionStore($destKey, $sourceKeys, $sourceWeights, $aggregate);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }
    

    /**
     *
     * @param string $key
     * @param string $member
     */
    public function zDelete($key, $member) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zDelete '.$key));}
        $result = $this->Redis->zDelete($key, $member);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;        
    }

    public function zRemRangeByScore($key, $start, $end) {
        return $this->Redis->zRemRangeByScore($key, $start, $end);
    }

    public function zIncrBy($key, $increment, $member) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zIncrBy '.$key));}
        $result = $this->Redis->zIncrBy($key, $increment, $member);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;        
    }

    /**
     * Возвращает количество элементов в SORTED SET
     * @param string $key
     * @return int
     */
    public function zCard($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zCard '.$key));}
        $result = $this->Redis->zCard($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     *
     * @param string $key
     * @param string $member
     * @return float
     */
    public function zScore($key, $member) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('zScore '.$key));}
        $result = $this->Redis->zScore($key, $member);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Возвращает элемент из начала очереди
     *
     * @param string $key
     * @return mixed
     */
    public function lPop($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('LPOP '.$key));}
        $result = $this->Redis->lPop($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Возвращает элемент из начала очереди
     *
     * @param string $key
     * @return mixed
     */
    public function lLen($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('LPOP '.$key));}
        $result = $this->Redis->lLen($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Возвращает элемент из начала очереди
     *
     * @param string $key
     * @return mixed
     */
    public function blPop($key, $timeout = null) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('LPOP '.$key));}
        $result = $this->Redis->blPop($key, $timeout);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Возвращает элемент из начала очереди
     *
     * @param string $key
     * @return mixed
     */
    public function rPop($key) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('RPOP '.$key));}
        $result = $this->Redis->rPop($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * LRANGE
     * @param string $key
     * @param int $start
     * @param int $stop
     * @return array
     */
    public function lGetRange($key, $start, $stop) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('LRANGE '.$key.','.$start.','.$stop));}
        $result = $R->lGetRange($key, $start, $stop);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * LRANGE
     * @param string $key
     * @param int $start
     * @param int $stop
     * @return array
     */
    public function lTrim($key, $start, $stop) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('LRANGE '.$key.','.$start.','.$stop));}
        $result = $R->lTrim($key, $start, $stop);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * @param string $key
     * @param int $expire
     */
    public function expire($key, $expire) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('expire '.$key.', '.$expire));}
        $result = $R->expire($key, $expire);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Сбросить базу
     * @param bool $all
     *        сбросить все базы
     */
    public function flushDB($all = false) {
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('FLUSH DB'));}
        $result = $R->flushDB();
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * Удалить ключи по шаблону
     * (Не встроенная функция)
     * @param string pattern
     * @return int
     */
    public function deleteKeysByPattern($pattern) {
        $keys = $this->getKeys($pattern);
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return count($keys);
    }

    /**
     * Всё что не переопределили здесь передаётся непосредственно в extension
     * @param sring $m
     * @param array $a
     * @return mixed
     */
    public function __call($m, $a) {
        throw new LogicException('Invalid method call: ' . $m);
    }

    protected function connect() {
        if (!isset($this->Redis)) {
            $this->Redis = new Redis();
            $this->Redis->connect($this->host, $this->port, 10);
        }
    }

    /**
     * Выполнение реконнекта к клиенту
     *
     * @return $this
     */
    public function reconnect() {
        $this->Redis->connect($this->host, $this->port, 15);
        return $this;
    }
    
    /**
     * хэш-операции, set
     * @param $key
     * @param $fieldName
     * @param $value
     * @return int
     */
    public function hSet($key, $fieldName, $value){
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('hash '.$key));}
        $result = $R->hSet($key, $fieldName, $value);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * хэш-операции, get
     * @param $key
     * @param $fieldName
     * @return mixed
     */
    public function hGet($key, $fieldName){
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('hash '.$key));}
        $result = $R->hGet($key, $fieldName);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * хэш-операции, exists
     * @param $key
     * @param $fieldName
     * @return void
     */
    public function hExists($key, $fieldName){
        $R = $this->Redis;
        $result = $R->hExists($key, $fieldName);
        return $result;
    }

    /**
     * @param $key
     * @param array $fieldNames
     * @return mixed
     */
    public function hmGet($key, $fieldNames){
        $R = $this->Redis;
        $result = $R->hmGet($key, $fieldNames);
        return $result;
    }

    /**
     * @param $key
     * @param array $fieldNames
     * @return mixed
     */
    public function hKeys($key){
        $R = $this->Redis;
        $result = $R->hKeys($key);
        return $result;
    }

    /**
     * хэш-операции, getAll
     * @param $key
     * @return mixed
     */
    public function hGetAll($key){
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('hash '.$key));}
        $result = $R->hGetAll($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * хэш-операции, hDel
     * @param $key
     * @return mixed
     */
    public function hDel($key, $hash){
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('hash '.$key));}
        $result = $R->hDel($key, $hash);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * хэш-операции, hLen
     * @param $key
     * @return mixed
     */
    public function hLen($key){
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('hash '.$key));}
        $result = $R->hLen($key);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * хэш-операции, hMset
     * @param $key
     * @param array $array_field_values
     * @return mixed
     */
    public function hMset($key, $array_field_values){
        $R = $this->Redis;
//        $b = BENCHMARK();
//        if ($b) {Benchmark::start($this->benchmarkName, $bench=('hash '.$key));}
        $result = $R->hMset($key, $array_field_values);
//        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }

    /**
     * хэш операции hIncrBy
     * @param $key
     * @param $field_name
     * @param $incr
     * @return bool
     */
    public function hIncrBy($key, $field_name, $incr){
        $R = $this->Redis;
//        $b = BENCHMARK();
////        if ($b) {Benchmark::start($this->benchmarkName, $bench=('hash '.$key));}
        $result = $R->hIncrBy($key, $field_name, $incr);
////        if ($b) {Benchmark::end($this->benchmarkName, $bench);}
        return $result;
    }
    
    /**
     * Summary
     * @param string $server
     * @param int $port
     * @return bool
     */
    public function slaveOf($server = null, $port = 0) {
        if ($server) {
            $result = $this->Redis->slaveof($server, $port);
        } else {
            $result = $this->Redis->slaveof();
        }
        return $result;
    }
    
    public function info() {
        return $this->Redis->info();
    }

    /**
     * 
     */
    const REDIS_DEFAULT_PORT = 6379;

    /**
     * @var Redis
     */
    private $Redis;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;
}
