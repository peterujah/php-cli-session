# CLI Session Helper for PHP

A lightweight utility class to manage and inspect CLI session environments in PHP.  
Designed for cross-platform compatibility (Linux, macOS, Windows) with focus on stream handling, session identification, terminal capability detection, and environmental diagnostics.

> No external dependencies. Built for PHP 7+

---

## Features

- Detects and resolves `STDIN`, `STDOUT`, `STDERR` in CLI environments
- Handles platform-specific stream opening safely
- Retrieves:
  - Terminal size (height and width)
  - Current user (`whoami`)
  - MAC address
  - OS and system model
  - Shell and terminal name
- Detects:
  - ANSI and color support
  - Platform type (Windows, macOS, Linux, cloud)
- Generates a **unique system identifier** for CLI sessions
- Silently handles errors using proper fallbacks

---

## Installation

Install via Composer PHP package manager.

```bash
composer install peterujah/php-cli-session
```

---

## Usage

### Initialize CLI streams

```php
use Peterujah\Cli\System\Session;

Session::init(); // Optional if constructor is called
```

### Start PHP session with CLI-safe ID

```php
$sessionId = Session::getSystemId();
session_id($sessionId);
session_start();
```

See working example [CLI Demos](https://github.com/peterujah/php-cli-session/tree/main/tests)

---

## Examples

### Get CLI terminal info

```php
$info = Session::getSystemInfo();

foreach ($info as $item) {
    echo $item['Name'] . ': ' . $item['Value'] . PHP_EOL;
}
```

### Detect color and ANSI support

```php
if (Session::isColorSupported()) {
    echo "✅ Color output is supported." . PHP_EOL;
}

if (Session::isAnsiSupported()) {
    echo "✅ ANSI escape sequences are supported." . PHP_EOL;
}
```

### Detect terminal dimensions

```php
$width = Session::getWidth();
$height = Session::getHeight();

echo "Terminal Size: {$width} x {$height}" . PHP_EOL;
```

---

## Methods Overview

| Method                       | Description                                                    |
| ---------------------------- | -------------------------------------------------------------- |
| `init()`                     | Initializes CLI stream resources (`STDIN`, `STDOUT`, etc.)     |
| `getSystemId()`              | Returns a unique, hash-based identifier for the current system |
| `getSystemInfo()`            | Returns structured system diagnostics                          |
| `getTerminalName()`          | Returns terminal name (`tty`, PowerShell, etc.)                |
| `getMacAddress()`            | Retrieves system MAC address                                   |
| `getPid()`                   | Gets the parent process ID                                     |
| `whoami()`                   | Gets the current user executing the script                     |
| `isColorSupported()`         | Checks if terminal supports color output                       |
| `isAnsiSupported()`          | Checks if terminal supports ANSI escape codes                  |
| `getWidth()` / `getHeight()` | Gets terminal width and height                                 |
| `isPlatform($os)`            | Checks for OS platform or cloud environment                    |

---

## Platform Compatibility

| OS       | Status                                       |
| -------- | -------------------------------------------- |
| Linux    | ✅ Tested                                    |
| macOS    | ✅ Tested                                    |
| Windows  | ✅ Tested                                    |
| BSD/Unix | ⚠️ Partial support                           |
| Docker   | ✅ Supported (if `tput` or `stty` available) |

---

## License

MIT License — use it, hack it, extend it.

---

## TODO

* Optional support for logging output to a file
* Add unit tests
* CLI command wrapper integration
* PHP framework integration hook
