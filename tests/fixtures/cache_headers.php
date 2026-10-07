<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use WebSK\SimpleRouter\SimpleRouter;

SimpleRouter::cacheHeaders(3600);

echo 'ok';
