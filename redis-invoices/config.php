<?php

// ===== REDIS CONFIG =====
define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('REDIS_PORT', 6379);
// define('REDIS_PASSWORD', '');  // раскомментировать если нужен пароль
// define('REDIS_DB', 0);        // номер базы данных Redis (по умолчанию 0)

/**
 * Создать и подключить Redis
 */
function redisConnect(): Redis {
    $redis = new Redis();
    $redis->connect(REDIS_HOST, REDIS_PORT);
    // if (REDIS_PASSWORD) $redis->auth(REDIS_PASSWORD);
    // $redis->select(REDIS_DB);
    $redis->ping();
    return $redis;
}
