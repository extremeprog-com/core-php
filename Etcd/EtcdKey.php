<?php


abstract class EtcdKey {

    const SORT_BY_KEYS = false;
    const USE_PREFIX   = true;
    /**
     * Получение значения текущего ключа
     * @return mixed
     */
    public static function get() {
        $value = FileAPI::getJSON(PROJECTDATA . "/" . static::class . '.json');
        if (static::SORT_BY_KEYS) {
            ksort($value);
        }
        return $value;
    }

    /**
     * Получение значения текущего ключа прямо из Etcd. Использовать только в отладочных целях!
     * @return mixed
     */
    public static function getReal() {
        $value = Etcd::instance()->get(static::key());
        if (static::SORT_BY_KEYS) {
            ksort($value);
        }
        return $value;
    }

    /**
     * Установка значения для текущего ключа
     * @param mixed $value
     */
    public static function set($value) {
        if (static::SORT_BY_KEYS) {
            ksort($value);
        }
        Etcd::instance()->set(static::key(), $value);
    }

    /**
     * Если в ключе хранится массив, изменить ключ в этом массиве
     * @param string $key
     * @param mixed $value
     */
    public static function modify($key, $value) {
        $index = null;
        // защищаемся от блокировок - используем cas и пробуем сохраниться максиум 10 раз
        for ($i = 0; $i < 10; $i++) {
            try {
                $val = Etcd::instance()->get(static::key(), $index);
                if ($val === null) {
                    $val = [];
                }
            } catch (Exception $e) {
                Log::warn($e);
            }
            // если не удалось найти значение в Etcd, попробуем получить его локально
            if (!isset($val) || !$val) {
                $val = static::get();
            }
            $val = [$key => $value] + $val;
            if (static::SORT_BY_KEYS) {
                ksort($val);
            }
            if (Etcd::instance()->set(static::key(), $val/*, null, $index*/)) {
                return;
            }
        }
//        throw new Exception('Cannot modify value');
    }

    /**
     * Создание события изменнеия ключа в етцд
     */
    public static function _createChangedEvent() {
        CatchEvent(\_OS\Core\System_InitFiles::class);
        $class_name = static::class . '_Changed';
        $class_file = PATH_TMP . '/' . $class_name . '.php';
        $content = "<?php
class {$class_name} extends EtcdKey_Changed {

}
";
        file_put_contents($class_file, $content);
    }

    /**
     * Запуск демона, который следит за изменнеием ключа
     */
    public static function runDaemon() {
        CatchEvent(\_OS\Core\System_InitDaemons::class);

        if (Etcd::enabled()) {
            Taskman::installDaemonUnderTaskman(static::class . '::' . __FUNCTION__, 'php-r \''.static::class.'::watch();\'');
        } else {
            Taskman::deleteDaemonUnderTaskman(static::class . '::' . __FUNCTION__);
        }
    }

    public static function watch() {
        $eventClass = static::class . '_Changed';
        $old = static::get();
        $new = Etcd::instance()->get(static::key(), $modifyIndex);
        while (true) {
            if ($old != $new) {
                FileAPI::putJSON(PROJECTDATA . "/" . static::class . '.json', $new);
                FireEvent(new $eventClass($old, $new));
            }
            $old = $new;
            $modifyIndex++;
            $new = Etcd::instance()->watch(static::key(), $modifyIndex);
        }
    }

    public static function key() {
        return (static::USE_PREFIX ? (PROJECT . '/') : '') . static::class;
    }
}
