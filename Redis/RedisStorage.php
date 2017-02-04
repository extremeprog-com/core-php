<?php

trait RedisStorage
{
    use RedisStateDaemon;
    
    /**
     * @return RedisClient
     */
    public static function RedisStorage() {
        static $RedisConnection;
        return $RedisConnection ?: $RedisConnection = static::redisClient('master');
    }
}
