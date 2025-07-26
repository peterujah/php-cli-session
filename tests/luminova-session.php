#!/usr/bin/env php
<?php
use PeterUjah\Cli\System\Session;
use Luminova\Boot;

// Load framework bootloader.
require __DIR__ . '/system/Boot.php';

// Autoload models and app classes.
Boot::autoload();

// Initialize CLI session
Session::init();

$sessionId = Session::getSystemId();
session_id($sessionId);

if (!session_start()) {
    echo "Failed to start session.\n";
    exit(1);
}

if (!isset($_SESSION['user'])) {
    $_SESSION['cli_data'] = 'This is data from CLI session.';
    $_SESSION['user'] = 'peter';

    echo "Registered new session ID: {$sessionId}\n";
    echo "Run: php script2.php {$sessionId} to access this session.\n";
} else {
    echo "Resumed session with ID: {$sessionId}\n";
    echo "User: {$_SESSION['user']}\n";
    echo "Data: {$_SESSION['cli_data']}\n";
}
