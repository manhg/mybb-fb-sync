#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('Asia/Tokyo');

error_reporting(E_ALL);
ini_set('log_errors', 'On');
ini_set('display_errors', 'On');
ini_set('error_log', 'error_log');
ini_set('display_startup_errors', 'On');

$console = new ConsoleKit\Console();
$g = new Command\Group($console);
$console->addCommand('Command\Group');
$console->addCommand('Command\GroupDoc');
$console->addCommand('Command\Comment');
$console->addCommand('Command\Page');
$console->run();
