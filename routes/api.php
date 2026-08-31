<?php

foreach (glob(__DIR__ . '/api/*_api.php') ?: [] as $routeFile) {
    require $routeFile;
}
