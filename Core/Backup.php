<?php

class Backup {
    /**
     * Сохранение бэкапа всех файлов в исходной папочке
     *
     * @param string $source_dir Папочка с исходными файлами для бэкапа
     * @param string|bool $rename Папка, в которую переименовать при бэкапе
     * @throws Exception
     */
    public static function make($source_dir, $rename = false) {
        // Check source dir exists
        if (!is_dir($source_dir))
            throw new Exception("Source dir $source_dir for backup not found");
            
        // Get backup locations
        foreach (glob(getenv('HOME') . '/_cluster*.backup*') as $backup_storage) {
            // Create backup dir if not exist
            $backup_dir = $backup_storage . '/' . PROJECT . '/' . ($rename ? $rename : basename($source_dir));
            if (!is_dir($backup_dir))
                mkdir($backup_dir, 0755, true);
    
            // Make copy if source != dest
            if (realpath($source_dir) !== $backup_dir)
                exec('rsync -avz ' . escapeshellarg($source_dir) . '/* ' . escapeshellarg($backup_dir));
    
            // Make snapshot
            $snapshot_dir = $backup_dir . '-' . date('YmdHis');
            exec('mfsmakesnapshot ' . escapeshellarg($backup_dir) .  ' ' . escapeshellarg($snapshot_dir));            
        }
    }

    /**
     * Установка демона, который занимается очисткой старых бэкапов из стораджа
     */
    public static function installCleanDaemon() {
        CatchEvent(System_InitConfigs);
        Taskman::installCronTask('00 05 * * * source ~/.projectsrc; cdproject ' . PROJECT . '; php-r \'Backup::clean();\'', __PROJECT__ .'/' . __CLASS__ . 'Cleaner');
    }

    /**
     * Выполнение очистки старых бэкапов
     * очистка бэкапов: 7 дней - не чистим, месяц - 2 раза в неделю
     * (понедельник, четверг), за каждый месяц - оставляем первый дамп
     * и первый дамп после 15 числа
     */
    public static function clean() {
        // Check storage dir exists
        $storage = glob(getenv('HOME') . '/_cluster*.mfs');
        $storage = end($storage);
        if (!$storage)
            throw new Exception('Cant find MFS storage dir');

        // Find all backups
        exec('ls -dt ' . escapeshellarg($storage . '/' . PROJECT . '.data/') . '*', $backups);
        if ($backups) {
            $delete = [];
            $saved  = [];
            $now    = time();

            // Смотрим все существующие бэкапы
            foreach (array_reverse($backups) as $dir) {
                $ts = filemtime($dir);
                $m  = date('m', $ts);

                if (!isset($saved[$m]))
                    $saved[$m] = [];

                switch (true) {
                    // В течение месяца сохраняем бэкапы только по понедельникам и четвергам
                    case (($ts >= $now - 86400 * 31) && !in_array(date('D', $ts), ['Mon', 'Thu'])):
                    // В течение месяца сохраняем только два бэкапа
                    case (($ts < $now - 86400 * 31) && date('d', $ts) < 15 && $saved[$m]):
                    // Voodoo magic
                    case (($ts < $now - 86400 * 31) && date('d', $ts) >= 15 && $saved[$m] && date('d', filemtime(end($saved[$m]))) >= 15):
                        $delete[] = $dir;
                        break;

                    // Saved files
                    default:
                        $saved[$m][] = $dir;
                        break;
                }
            }

            // Удаляем отмеченные папки
            foreach ($delete as $dir) {
                exec('rm -fR ' . escapeshellarg($dir));
            }
        }
    }
}