<?php
declare(strict_types=1);

/**
 * Central error logging. Writes one line per entry to logs/error.log
 * (override with the SENTINEL_LOG_FILE environment variable).
 *
 * Also installs handlers so uncaught exceptions, PHP warnings/notices
 * and fatal errors end up in the same file.
 */

if (!function_exists('log_error')) {

    function log_file_path(): string
    {
        $env = getenv('SENTINEL_LOG_FILE');
        return is_string($env) && $env !== '' ? $env : dirname(__DIR__) . '/logs/error.log';
    }

    function log_write(string $level, string $message, array $context = []): void
    {
        $file = log_file_path();
        $dir  = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $context['uri'] = $context['uri'] ?? (($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '')));

        // Log in SAST regardless of PHP's default timezone
        $time = (new DateTimeImmutable('now', new DateTimeZone('Africa/Johannesburg')))->format('c');

        $line = "[$time] $level: $message "
              . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
              . PHP_EOL;

        // Fall back to PHP's own error log if our file is not writable
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($line));
        }
    }

    function log_error(string $message, array $context = []): void
    {
        log_write('ERROR', $message, $context);
    }

    function log_warning(string $message, array $context = []): void
    {
        log_write('WARNING', $message, $context);
    }

    /** Log a caught exception with its location and a short trace. */
    function log_exception(\Throwable $e, string $message = '', array $context = []): void
    {
        $context += [
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
        ];
        log_error(($message !== '' ? "$message: " : '') . $e->getMessage(), $context);
    }

    // Uncaught exceptions: log, then answer with a generic JSON 500
    set_exception_handler(function (\Throwable $e): void {
        log_exception($e, 'Uncaught exception');
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'status' => 'error', 'error' => 'Internal server error']);
    });

    // Warnings, notices, deprecations: log, then let PHP handle them as usual
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
        if (!(error_reporting() & $errno)) return false;   // silenced with @ or not reported
        $level = in_array($errno, [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)
            ? 'WARNING' : 'ERROR';
        log_write($level, $errstr, ['file' => "$errfile:$errline", 'errno' => $errno]);
        return false;
    });

    // Fatal errors (out of memory, timeouts, parse errors in includes)
    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            log_write('FATAL', $err['message'], ['file' => $err['file'] . ':' . $err['line']]);
        }
    });
}
