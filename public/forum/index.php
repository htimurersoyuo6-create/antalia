<?php

$frontController = dirname(__DIR__).'/index.php';

$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $frontController;

require $frontController;
