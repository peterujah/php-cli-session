#!/usr/bin/env php
<?php
use Peterujah\Cli\System\Session;
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

if (!isset($_SESSION['cli_user'])) {
    $_SESSION['cli_data'] = 'This is data from CLI session.';
    $_SESSION['cli_user'] = 'peter';

    echo "Registered new session ID: {$sessionId}\n";
    echo "Run: 'php luminova-session.php' {$sessionId} to access this session.\n";
} else {
    echo "Resumed session with ID: {$sessionId}\n";
    echo "User: {$_SESSION['cli_user']}\n";
    echo "Data: {$_SESSION['cli_data']}\n";
}
