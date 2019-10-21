<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/WebHook.php';

$webHook = new WebHook();
$webHook->processing();
