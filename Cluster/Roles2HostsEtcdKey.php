<?php
class Roles2HostsEtcdKey extends EtcdKey {

    const SORT_BY_KEYS = true;

    /**
     * Рагируем на изменение ключей и бросаем отдельные ивенты на добавление или удаление тех или
     * иных хостов в каких-то ролях
     */
    public static function throwHostRoleEvents() {
        $Event = CatchEvent(Roles2HostsEtcdKey_Changed::class); /** @var EtcdKey_Changed $Event */

        self::fireEventDiffHosts((array) $Event->to,   (array) $Event->from, 'Add');
        self::fireEventDiffHosts((array) $Event->from, (array) $Event->to,   'Remove');
    }

    /**
     * @param array $source
     * @param array $dest
     * @param string $event
     */
    protected static function fireEventDiffHosts(array $source, array $dest, $event) {
        foreach ($source as $role => $hosts) {
            if (!is_array($hosts)) {
                continue;
            }
            
            foreach (array_diff($hosts, isset($dest[$role]) ? $dest[$role] : []) as $host) {
                // Событие кидаем только на том хосте, который был задействован
                if ($host !== Taskman::getHostname()) {
                    continue;
                }
                
                $event_class = $role . '_' . $event;
                FireEvent(new $event_class($host));
            }
        }
    }
}