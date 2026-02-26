<?php

namespace AiAdvent;

/**
 * Terminal I/O utilities for proper UTF-8 and international character handling
 */
class TerminalIO
{
    private static $helperProcess = null;
    private static array $helperPipes = [];
    private static bool $useUnicodeReader = false;
    private static ?string $helperTempFile = null;
    private static string $readerType = 'fgets';

    /**
     * Initialize terminal for UTF-8 input/output.
     * Called once at script startup.
     */
    public static function initializeUTF8(): void
    {
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_cp_set')) {
            sapi_windows_cp_set(65001);
        }

        // On Windows interactive console, spawn a Unicode-capable co-process.
        // PHP's fgets/ReadConsoleA corrupts non-Latin input in many environments
        // (especially ConEmu/Cmder where stdin is a pipe, not a real console buffer).
        if (PHP_OS_FAMILY === 'Windows' && stream_isatty(STDIN)) {
            if (self::initPythonReader()) {
                self::$useUnicodeReader = true;
                self::$readerType = 'python';
            } elseif (self::initPowerShellReader()) {
                self::$useUnicodeReader = true;
                self::$readerType = 'powershell';
            }
        }
    }

    /**
     * Return which reader is active: 'python', 'powershell', or 'fgets'
     */
    public static function getReaderType(): string
    {
        return self::$readerType;
    }

    /**
     * Start a Python 3 co-process for reading console input.
     * Python handles stdin encoding reliably via reconfigure().
     */
    private static function initPythonReader(): bool
    {
        $pyCmd = null;
        foreach (['python', 'py -3', 'python3'] as $candidate) {
            $out = [];
            @exec($candidate . ' -c "import sys; print(sys.version_info[0])" 2>NUL', $out, $code);
            if ($code === 0 && trim(implode('', $out)) === '3') {
                $pyCmd = $candidate;
                break;
            }
        }

        if ($pyCmd === null) {
            return false;
        }

        $script = implode("\n", [
            "import sys",
            "try:",
            "    sys.stdin.reconfigure(encoding='utf-8')",
            "except AttributeError:",
            "    pass",
            "while True:",
            "    try:",
            "        line = sys.stdin.readline()",
            "        if not line:",
            "            break",
            "        sys.stdout.buffer.write(line.encode('utf-8', errors='replace'))",
            "        sys.stdout.buffer.flush()",
            "    except EOFError:",
            "        break",
        ]);

        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_reader_' . getmypid() . '.py';
        if (@file_put_contents($tmpFile, $script) === false) {
            return false;
        }

        $descriptors = [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR];
        $process = @proc_open($pyCmd . ' "' . $tmpFile . '"', $descriptors, $pipes);

        if (!is_resource($process)) {
            @unlink($tmpFile);
            return false;
        }

        self::$helperProcess = $process;
        self::$helperPipes = $pipes;
        self::$helperTempFile = $tmpFile;
        stream_set_blocking($pipes[1], true);
        return true;
    }

    /**
     * Start a PowerShell co-process that reads console input and outputs UTF-8.
     * Uses a temp .ps1 file to avoid command-line quoting issues.
     * Explicitly sets InputEncoding to UTF-8 so pipe-based stdin (ConEmu/Cmder)
     * is decoded correctly regardless of system ANSI code page.
     */
    private static function initPowerShellReader(): bool
    {
        $script = implode("\r\n", [
            '[Console]::InputEncoding = [System.Text.Encoding]::UTF8',
            '$stdout = [Console]::OpenStandardOutput()',
            'while ($true) {',
            '    $line = [Console]::ReadLine()',
            '    if ($null -eq $line) { break }',
            '    $bytes = [System.Text.Encoding]::UTF8.GetBytes($line)',
            '    $stdout.Write($bytes, 0, $bytes.Length)',
            '    $stdout.WriteByte(10)',
            '    $stdout.Flush()',
            '}',
        ]);

        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_reader_' . getmypid() . '.ps1';
        // UTF-8 BOM tells PowerShell to read the file as UTF-8
        if (@file_put_contents($tmpFile, "\xEF\xBB\xBF" . $script) === false) {
            return false;
        }

        $cmd = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' . $tmpFile . '"';
        $descriptors = [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR];
        self::$helperProcess = @proc_open($cmd, $descriptors, self::$helperPipes);

        if (!is_resource(self::$helperProcess)) {
            @unlink($tmpFile);
            self::$helperProcess = null;
            return false;
        }

        self::$helperTempFile = $tmpFile;
        stream_set_blocking(self::$helperPipes[1], true);
        return true;
    }

    /**
     * Convert terminal input from Windows encodings to UTF-8.
     * Only called when the co-process reader is unavailable.
     */
    public static function decodeTerminalInput(string $input): string
    {
        if (mb_check_encoding($input, 'UTF-8')) {
            return $input;
        }

        $detected = mb_detect_encoding(
            $input,
            ['Windows-1251', 'Windows-1252', 'UTF-8', 'ASCII'],
            true
        );

        if ($detected && $detected !== 'UTF-8') {
            $converted = @iconv($detected, 'UTF-8//TRANSLIT', $input);
            if ($converted !== false) {
                return $converted;
            }
        }

        foreach (['Windows-1251', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//TRANSLIT', $input);
            if ($converted !== false) {
                return $converted;
            }
        }

        return mb_convert_encoding($input, 'UTF-8');
    }

    /**
     * Read a line from STDIN with proper UTF-8 decoding
     */
    public static function readLine(string $prompt = ''): string
    {
        if ($prompt !== '') {
            echo $prompt;
        }

        if (self::$useUnicodeReader && self::$helperProcess !== null) {
            $line = fgets(self::$helperPipes[1]);
            if ($line !== false) {
                return rtrim($line, "\r\n");
            }
            // Co-process died, fall back to fgets
            self::$useUnicodeReader = false;
            self::$readerType = 'fgets';
        }

        $input = trim(fgets(STDIN));
        return self::decodeTerminalInput($input);
    }

    /**
     * Clean up the helper co-process and temp file
     */
    public static function shutdown(): void
    {
        if (self::$helperProcess !== null) {
            foreach (self::$helperPipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate(self::$helperProcess);
            proc_close(self::$helperProcess);
            self::$helperProcess = null;
            self::$helperPipes = [];
        }

        if (self::$helperTempFile !== null) {
            @unlink(self::$helperTempFile);
            self::$helperTempFile = null;
        }
    }
}
