#!/usr/bin/env php
<?php
use Peterujah\Cli\System\Session;

// Load composer bootloader.
require __DIR__ . '/plugins/vendor/autoload.php';

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
    echo "Run: 'php session.php' {$sessionId} to access this session.\n";
} else {
    echo "Resumed session with ID: {$sessionId}\n";
    echo "User: {$_SESSION['user']}\n";
    echo "Data: {$_SESSION['cli_data']}\n";
}
