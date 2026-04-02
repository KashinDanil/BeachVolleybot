<?php

declare(strict_types=1);

define('TG_BOT_ACCESS_TOKEN', 'test_token');
define('APP_TOKEN_HASH', 'test_hash');
define('BASE_LOG_DIR', sys_get_temp_dir() . '/bvb_test_logs');
define('BASE_QUEUE_DIR', sys_get_temp_dir() . '/bvb_test_queues');
define('QUEUE_CLASS', \DanilKashin\FileQueue\Queue\FileQueue::class);
define('VERBOSE_LOGGING', false);