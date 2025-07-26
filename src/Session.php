<?php 
/**
 * PHP Command-Line Session Helper class.
 *
 * @package php-cli-session
 * @author Ujah Chigozie Peter
 * @license See LICENSE file 
 * @link https://github.com/peterujah/php-cli-session
 */
namespace App\Utils\Cli;

use \Throwable;
use \RuntimeException;

class Session
{
    /**
     * Represents the standard output stream.
     * 
     * @var int STD_OUT
     */
    public const STD_OUT = 0;

    /**
     * Represents the standard error stream.
     * 
     * @var int STD_ERR
     */
    public const STD_ERR = 1;

    /**
     * Represents the standard input stream.
     * 
     * @var int STD_IN
     */
    public const STD_IN = 2;

    /**
     * Height of terminal visible window
     *
     * @var int|null $windowHeight
     */
    protected static ?int $windowHeight = null;

    /**
     * Width of terminal visible window
     *
     * @var int|null $windowWidth
     */
    protected static ?int $windowWidth = null;

    /**
     * About system information.
     *
     * @var array $session
     */
    private static array $session = [
        'metadata' => null,
        'ppid' => null,
        'model' => null,
        'name' => null,
        'id' => null,
    ];

    /**
     * Flags to determine if color and ansi are supported 
     * based on different `STDOUT` and `STDERR` resource.
     *
     * @var array{colors:array{0:?bool,1:?bool},ansi:array{0:?bool,1:?bool}} $isSupported
     */
    protected static array $isSupported = [
        'colors' => [
            0 => null, //stdout
            1 => null, //stderr,
            2 => null //stdin
        ],
        'ansi' => [
            0 => null, // stdout
            1 => null, // stderr,
            2 => null //stdin
        ]
    ];

    /**
     * Command is initialized.
     * 
     * @var bool $isInitialized;
     */
    protected static bool $isInitialized = false;

    /**
     * Creates a new instance of the command-line session system.
     *
     * This constructor automatically calls the `init()` method to prepare
     * the standard input/output streams and initialize the session environment.
     *
     * @see self::init()
     */
    public function __construct()
    {
        self::init();
    }

    /**
     * Initializes the command-line session system.
     *
     * This method sets up the standard input (STDIN), output (STDOUT),
     * and error (STDERR) streams if they are not already defined.
     * It also checks if the terminal supports colored output.
     *
     * This method should only be called once during the session lifecycle.
     *
     * @return void
     */
    public static function init(): void
    {
        if(self::$isInitialized){
            return;
        }

        defined('STDIN') || define('STDIN', self::tryFopen('php://stdin', 'r'));
        defined('STDOUT') || define('STDOUT', self::tryFopen('php://stdout', 'w'));
        defined('STDERR') || define('STDERR', self::tryFopen('php://stderr', 'w'));
        self::isColorSupported(self::STD_OUT);
        self::$isInitialized = true;
    }

    /**
     * Returns the name of the system user running the script.
     *
     * This method first tries to get the username using a shell command:
     * - On Windows, it runs `echo %USERNAME%`
     * - On Unix-like systems, it runs `whoami`
     *
     * If the shell command fails, it falls back to PHP's built-in `get_current_user()`.
     *
     * The returned username is trimmed of any extra whitespace.
     *
     * @return string Return the current system user's username.
     */
    public static function whoami(): string
    {
        $user = self::_shell(self::isPlatform('windows') ? 'echo %USERNAME%' : 'whoami');
    
        if (!$user) {
            $user = get_current_user();
        }

        return trim($user);
    }

    /**
     * Checks if the terminal supports ANSI color output.
     *
     * This method detects whether the given stream (STDOUT, STDERR, or STDIN)
     * can display colored output using ANSI escape codes.
     * 
     * On Windows, it checks for support using the `WT_SESSION` environment variable
     * or the Windows Terminal API. On Unix-like systems, it runs the `tput colors` command.
     * 
     * The result is cached to avoid repeated checks.
     *
     * @param int $std The stream to check (use constants like `Terminal::STD_OUT`, `Terminal::STD_ERR`, or `Terminal::STD_IN`).
     * 
     * @return bool Return true if color output is supported, false otherwise.
     */
    public static function isColorSupported(int $std = self::STD_OUT): bool
    {
        if((self::$isSupported['colors'][$std] ?? null) !== null){
            return self::$isSupported['colors'][$std];
        }

        if (!self::isColorDisabled()) {
            if (self::isPlatform('windows')) {
                return self::$isSupported['colors'][$std] = getenv('WT_SESSION') || self::isWindowsTerminal($std);
            }

            return self::$isSupported['colors'][$std] = (int) trim(@self::_exec('tput colors')) > 0;
        }

        return self::$isSupported['colors'][$std] = false;
    }

    /**
     * Checks if the terminal supports ANSI escape codes for color and text formatting.
     *
     * This method detects whether the specified stream (STDOUT, STDERR, or STDIN)
     * supports ANSI codes such as color, bold, or underline.
     * 
     * On Windows, it checks for the `ANSICON` or `WT_SESSION` environment variables.
     * On Unix-like systems, it uses a platform-specific check.
     * 
     * The result is cached per stream to avoid repeated detection.
     *
     * @param int $std The stream to check (use `Terminal::STD_OUT`, `Terminal::STD_ERR`, or `Terminal::STD_IN`).
     * 
     * @return bool Return true if ANSI escape codes are supported, false otherwise.
     */
    public static function isAnsiSupported(int $std = self::STD_OUT): bool
    {
        if ((self::$isSupported['ansi'][$std] ?? null) !== null) {
            return self::$isSupported['ansi'][$std];
        }

        self::$isSupported['ansi'][$std] = false;

        if (!self::isAnsiDisabled()) {
            self::$isSupported['ansi'][$std] = self::isPlatform('windows') 
                ? getenv('ANSICON') === 'ON' || getenv('WT_SESSION') !== false
                :  self::isLinuxAnsi();
        }

        return self::$isSupported['ansi'][$std];
    }

    /**
     * Checks if color output is disabled in the environment.
     *
     * This method looks for the `NO_COLOR` flag in environment variables
     * or server variables to determine if colored output should be turned off.
     *
     * @return bool Return true if color output is disabled, false otherwise.
     */
    public static function isColorDisabled(): bool
    {
        return (isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false);
    }

    /**
     * Checks if ANSI escape codes are disabled in the environment.
     *
     * This method looks for the `DISABLE_ANSI` flag in environment variables
     * or server variables to determine if ANSI formatting should be disabled.
     *
     * @return bool Return true if ANSI escape codes are disabled, false otherwise.
     */
    public static function isAnsiDisabled(): bool
    {
        return (isset($_SERVER['DISABLE_ANSI']) || getenv('DISABLE_ANSI') !== false);
    }

    /**
     * Checks whether the given stream is a Windows terminal that supports ANSI codes.
     *
     * This method checks for known environment variables and uses
     * the `sapi_windows_vt100_support()` function to detect terminal support
     * for ANSI escape sequences on Windows systems.
     *
     * @param resource|string|int $resource The stream to check (e.g. `Terminal::STD_OUT`, `STDIN`, or a file descriptor).
     * 
     * @return bool Return true if the stream supports ANSI output on Windows, false otherwise.
     */
    public static function isWindowsTerminal(mixed $resource = self::STD_IN): bool
    {
        return self::isStreamSupports('sapi_windows_vt100_support', $resource) ||
            isset($_SERVER['ANSICON']) || 
            getenv('ANSICON') !== false || 
            getenv('ConEmuANSI') === 'ON' || 
            getenv('TERM') === 'xterm';
    }

    /**
     * Checks whether the given stream supports a specific terminal function.
     *
     * This method calls a given function (like `sapi_windows_vt100_support`)
     * on the stream resource to test if the stream supports a specific terminal capability.
     *
     * @param string $function The name of the function to call.
     * @param resource|string|int $resource The stream to check (e.g. `Terminal::STD_OUT`, `STDIN`, or file descriptor).
     * 
     * @return bool Return true if the stream is supported by the function, otherwise false.
     */
    public static function isStreamSupports(string $function, mixed $resource = self::STD_OUT): bool
    {
        if (!function_exists($function)) {
            return false;
        }

        return @$function(self::getStd($resource));
    }

    /**
     * Checks if the terminal is a known ANSI-compatible terminal on Linux or Unix systems.
     *
     * This method reads the `TERM` environment variable and compares it against
     * a list of known terminals that support ANSI escape codes.
     *
     * @return bool Return true if the terminal supports ANSI formatting, false otherwise.
     */
    public static function isLinuxAnsi(): bool
    {
        $term = getenv('TERM');
        if ($term !== false) {
            $supported = ['xterm', 'xterm-color', 'screen', 'screen-256color', 'tmux', 'linux'];
            foreach ($supported as $terminal) {
                if (str_contains($term, $terminal)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gets the current width of the visible terminal window.
     *
     * If the width is not already known, it attempts to detect it.
     * If detection fails, it falls back to the default value.
     *
     * @param int $default Optional default width to use if detection fails (default is 80).
     * 
     * @return int Return terminal window width, or the default if not detectable.
     */
    public static function getWidth(int $default = 80): int
    {
        if (self::$windowWidth === null) {
            self::getVisibleWindow();
        }

        return self::$windowWidth ?: $default;
    }

    /**
     * Gets the current height of the visible terminal window.
     *
     * If the height is not already known, it attempts to detect it.
     * If detection fails, it falls back to the default value.
     *
     * @param int $default Optional default height to use if detection fails (default is 24).
     * 
     * @return int Return terminal window height, or the default if not detectable.
     */
    public static function getHeight(int $default = 24): int
    {
        if (self::$windowHeight === null) {
            self::getVisibleWindow();
        }

        return self::$windowHeight ?: $default;
    }

    /**
     * Retrieves the parent process ID (PPID) of the current CLI session.
     *
     * This method detects the process ID of the parent terminal or shell 
     * that launched the current script.
     *
     * - On Unix-like systems, it runs the `ps` command to extract the PPID.
     * - On Windows, it uses the `wmic` command to get the parent process ID.
     *
     * Useful for distinguishing between multiple sessions or shell spawns
     * initiated by the same user.
     *
     * @return string Return the parent process ID, or '0' if it cannot be determined.
     */
    public static function getPid(): string 
    {
        if(self::$session['ppid'] !== null){
            return self::$session['ppid'];
        }

        $ppid = trim(self::_exec((PHP_OS_FAMILY === 'Windows') 
            ? 'wmic process where (ProcessId=' . getmypid() . ') get ParentProcessId /value' 
            : 'ps -o ppid= -p ' . getmypid()
        ) ?? '0');

        if (PHP_OS_FAMILY === 'Windows' && $ppid && preg_match('/ParentProcessId=(\d+)/', $ppid, $match)) {
            $ppid = $match[1];
        }

        return self::$session['ppid'] = $ppid;
    }

    /**
     * Retrieves key system and environment information in a structured array format.
     *
     * This method collects details about the PHP runtime, operating system,
     * terminal settings, and session-specific values. It is useful for displaying
     * diagnostic data in CLI tools, logging environments, or identifying runtime context.
     *
     * The information includes system details like OS name, PHP version, user,
     * terminal dimensions, and support for color or ANSI output.
     *
     * @return array<int,array{Name:string,Value:string}> Return a list of system properties and their corresponding values.
     */
    public static function getSystemInfo(): array 
    {
        if(self::$session['metadata'] !== null){
            return self::$session['metadata'];
        }

        return self::$session['metadata'] = [
            ['Name' => 'PHP Version', 'Value' => PHP_VERSION],
            ['Name' => 'OS Name', 'Value' => php_uname('s')],
            ['Name' => 'OS Version', 'Value' => php_uname('v')],
            ['Name' => 'OS Model', 'Value' => self::getSystemModel()],
            ['Name' => 'Machine Type', 'Value' => php_uname('m')],
            ['Name' => 'Host Name', 'Value' => php_uname('n')],
            ['Name' => 'MAC Address', 'Value' => self::getMacAddress()],
            ['Name' => 'Process Id','Value' =>  self::getPid()],
            ['Name' => 'Current User (PHP)', 'Value' => get_current_user()],
            ['Name' => 'Whoami', 'Value' => self::whoami() ?: 'Unavailable'],
            ['Name' => 'Terminal Name','Value' =>  self::getTerminalName()],
            ['Name' => 'Terminal Width', 'Value' => self::getHeight()],
            ['Name' => 'Terminal Height', 'Value' => self::getWidth()],
            ['Name' => 'Color Supported', 'Value' => self::isColorSupported() ? 'Yes' : 'No'],
            ['Name' => 'ANSI Supported', 'Value' => self::isAnsiSupported() ? 'Likely Yes' : 'Unknown/No'],
            ['Name' => 'Shell', 'Value' => getenv('SHELL') ?: getenv('ComSpec') ?: 'Unknown'],
            ['Name' => 'TERM Variable', 'Value' => getenv('TERM') ?: 'Unknown']
        ];
    }

    /**
     * Generates a consistent and unique system identifier for CLI authentication.
     *
     * This method creates a hash based on system and environment information such as
     * the hostname, OS version, machine type, MAC address, process ID, and current user.
     * The result is a unique value that can be used to identify a specific CLI session,
     * machine, or user context across executions.
     *
     * You can customize the result by providing a prefix, choosing a hashing algorithm,
     * or requesting raw binary output.
     *
     * @param string|null $prefix Optional prefix to prepend to the result (default: '').
     * @param string $algo Hashing algorithm to use (default: 'sha256').
     * @param bool $binary Whether to return the raw binary output instead of a string hash (default: false).
     *
     * @return string Return a hashed system identifier based on system-specific and user-specific data.
     */
    public static function getSystemId(?string $prefix = null, string $algo = 'sha256',  bool $binary = false): string
    {
        if(self::$session['id'] !== null){
            return self::$session['id'];
        }

        $prefix ??= '';
        $info = [
            php_uname('n'),
            php_uname('v'),
            php_uname('m'),
            self::getMacAddress(),
            self::getSystemModel(),
            self::getPid(),
            get_current_user() ?: self::whoami(),
            getenv('SHELL') ?: getenv('ComSpec') ?: 'Unknown',
            getenv('TERM') ?: 'Unknown',
            $prefix
        ];

        return self::$session['id'] = $prefix . hash($algo, implode('|', $info), $binary);
    }

    /**
     * Retrieves the system's model name (e.g., MacBook Pro, Dell XPS).
     *
     * This method uses platform-specific commands to detect the hardware model.
     * - On macOS, it runs `sysctl`
     * - On Windows, it uses `wmic`
     * - On Linux, it reads from system files or uses `dmidecode`
     *
     * @return string Return the system's model name, or 'Unknown' if not detected.
     */
    public static function getSystemModel(): string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $model = self::_exec("sysctl -n hw.model");
        }elseif (PHP_OS_FAMILY === 'Windows') {
            $output = self::_exec('wmic computersystem get model /value');
            if (preg_match('/Model=(.*)/i', $output, $matches)) {
                $model = $matches[1];
            }
        }elseif (PHP_OS_FAMILY === 'Linux') {
            $model = @file_get_contents('/sys/devices/virtual/dmi/id/product_name');

            if (!$model) {
                $model = self::_exec('dmidecode -s system-product-name');
            }
        }

        if(!$model){
            return self::$session['model'] = 'Unknown';
        }

        return self::$session['model'] = trim($model) ?: 'Unknown';
    }

    /**
     * Retrieves the name of the terminal being used to run the script.
     *
     * On Windows, it detects PowerShell or Command Prompt based on environment variables.
     * On Unix-like systems, it returns the TTY device name using the `tty` command.
     *
     * @return string Return the terminal name, or 'N/A' if it cannot be detected.
     */
    public static function getTerminalName(): string 
    {
        if (self::isPlatform('windows')) {
            if (getenv('PSModulePath') !== false) {
                return self::$session['name'] = 'PowerShell';
            }

            $comspec = getenv('ComSpec');
            return self::$session['name'] = ($comspec ? basename($comspec) : 'N/A');
        }

        return self::$session['name'] = trim(self::_exec('tty') ?? 'N/A');
    }

    /**
     * Retrieves the MAC (Media Access Control) address of the system's network interface.
     *
     * This method runs platform-specific shell commands to extract the MAC address:
     * - On Windows: uses `getmac`
     * - On Unix-like systems: tries `ifconfig -a` or `ip link`
     *
     * Only the first detected MAC address is returned.
     *
     * @return string|null Return the first matched MAC address, or null if not found.
     */
    public static function getMacAddress(): ?string
    {
        $output = self::_shell(self::isPlatform('windows')
            ? 'getmac' 
            : 'ifconfig -a 2>/dev/null || ip link 2>/dev/null'
        );

        preg_match('/([a-f0-9]{2}[:-]){5}[a-f0-9]{2}/i', $output, $matches);

        return $matches[0] ?? null;
    }

    /**
     * Resolves a CLI stream constant to its corresponding PHP stream resource.
     *
     * This method translates custom stream constants (`Session::STD_OUT`, `Session::STD_ERR`, or `Session::STD_IN`)
     * into the built-in PHP stream resources (`STDOUT`, `STDERR`, or `STDIN`).
     * If the input does not match a known constant, it is returned as-is.
     *
     * @param resource|int $std The stream identifier, typically a `Session::STD_*` constant or a custom resource.
     *
     * @return resource|string Return the corresponding PHP stream resource, or the original input if unmatched.
     */
    private static function getStd(mixed $std): mixed
    {
        return ($std === self::STD_OUT) ? STDOUT 
            : (($std === self::STD_ERR) ? STDERR
            : (($std === self::STD_IN) ? STDIN : $std));
    }

    /**
     * Safely attempts to open a file and returns the handle.
     *
     * If the file cannot be opened (e.g., invalid path or permission denied),
     * an exception is thrown. Errors are caught and attached to the exception for context.
     *
     * @param string $filename The file path to open.
     * @param string $mode The mode in which to open the file (e.g., 'r', 'w', 'a').
     *
     * @return resource Return the opened file handle on success.
     *
     * @throws RuntimeException If the file cannot be opened.
     */
    private static function tryFopen(string $filename, string $mode): mixed
    {
        $error = null;
        $handle = null;

        try {
            $handle = fopen($filename, $mode);
        } catch (Throwable $e) {
            $error = $e;
        }

        if (!is_resource($handle)) {
            throw new RuntimeException(sprintf(
                'Failed to open file "%s" with mode "%s"%s',
                $filename,
                $mode,
                $error ? ': ' . $error->getMessage() : ''
            ), 0, $error);
        }

        return $handle;
    }

    /**
     * Executes a shell command using `exec()` and captures the output.
     *
     * This method automatically redirects stderr to null based on the platform:
     * - On Windows: appends `2>NUL`
     * - On Unix-like systems: appends `2>/dev/null`
     *
     * If the command already contains redirection, none is added.
     *
     * @param string $command The command to run.
     * @param array &$output The output lines from the command, passed by reference.
     * @param int &$result_code The exit status of the command, passed by reference.
     *
     * @return string|false Return the last line of the output on success, or false on failure.
     */
    private static function _exec(
        string $command, 
        array &$output = [], 
        int &$result_code = STATUS_ERROR
    ): string|bool
    {
        $devNull = self::isPlatform('windows') ? ' 2>NUL' : ' 2>/dev/null';
        if(str_contains($command, $devNull)){
            $devNull = '';
        }

        return exec("{$command}{$devNull}", $output, $result_code);
    }

    /**
     * Executes a shell command and returns the full output as a string.
     *
     * This method uses `shell_exec()` and suppresses error output using platform-specific redirection:
     * - On Windows: appends `2>NUL`
     * - On Unix-like systems: appends `2>/dev/null`
     *
     * If the command already contains redirection, none is added.
     *
     * @param string $command The command to execute.
     *
     * @return string|null Return the full output of the command, or null if execution fails.
     */
    private static function _shell(string $command): ?string
    {
        $devNull = self::isPlatform('windows') ? ' 2>NUL' : ' 2>/dev/null';

        if(str_contains($command, $devNull)){
            $devNull = '';
        }

        $response = shell_exec("{$command}{$devNull}");

        if(!$response){
            return null;
        }

        return $response;
    }

    /**
     * Checks if the current operating environment matches a given platform keyword.
     *
     * This method supports checks for common platforms such as:
     * - `windows`, `mac`, `linux`, `bsd`, `freebsd`, `openbsd`, `solaris`
     * - Cloud providers like `aws` and `azure`
     * - Or performs a loose match against the system name
     *
     * @param string $os The platform name or keyword to check against.
     *
     * @return bool Return true if the current system matches the specified platform.
     */
    private static function isPlatform(string $os): bool
    { 
        $os = \strtolower($os);
        return match ($os) {
            'mac' => \PHP_OS_FAMILY === 'Darwin',
            'windows' => \PHP_OS_FAMILY === 'Windows',
            'freebsd' => \PHP_OS === 'FreeBSD',
            'openbsd' => \PHP_OS === 'OpenBSD',
            'bsd' => \PHP_OS_FAMILY === 'BSD',
            'solaris' => \PHP_OS_FAMILY === 'Solaris',
            'linux' => \PHP_OS_FAMILY === 'Linux',
            'aws' => isset($_ENV['AWS_EXECUTION_ENV']),
            'azure' => isset($_ENV['WEBSITE_INSTANCE_ID']) || isset($_ENV['AZURE_FUNCTIONS_ENVIRONMENT']),
            default => \str_contains(\php_uname('s'), $os),
        };
    }

    /**
     * Calculates the visible CLI window width and height.
     *
     * This method detects the terminal dimensions and stores them internally
     * for use by other methods such as `getWidth()` and `getHeight()`.
     *
     * - On Windows, it uses PowerShell or `mode con`
     * - On Unix-like systems, it tries `stty size` and falls back to `tput`
     *
     * Values are cached after the first call to avoid repeated detection.
     *
     * @return void Return nothing. Updates static properties with width and height.
     */
    private static function getVisibleWindow(): void
    {
        if (self::$windowHeight !== null && self::$windowWidth !== null) {
            return;
        }

        $height = 0;
        $width = 0;

        if (self::isPlatform('windows')) {
            // Use PowerShell to get console size on Windows
            $size = self::_shell('powershell -command "Get-Host | ForEach-Object { $_.UI.RawUI.WindowSize.Height; $_.UI.RawUI.WindowSize.Width }"');

            if ($size) {
                $dimensions = explode("\n", trim($size));
                $height = (int) $dimensions[0] ?? 0;
                $width = (int) $dimensions[1] ?? 0;
            }else{
                $size = self::_exec('mode con');
                preg_match('/Columns:\s+(\d+)/i', $size, $colMatch);
                preg_match('/Lines:\s+(\d+)/i', $size, $rowMatch);

                $height = (int) $colMatch[1] ?? 0;
                $width = (int) $rowMatch[1] ?? 0;
            }
        } else {
            // Fallback for Unix-like systems
            $size = self::_exec('stty size');
            if ($size && preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {

                $height = (int) $matches[1] ?? 0;
                $width = (int) $matches[1] ?? 0;
            }
        }

        self::$windowHeight = ($height === 0) ? (int) self::_exec('tput lines') : $height;
        self::$windowWidth  = ($width === 0) ? (int) self::_exec('tput cols') : $width;
    }
}
