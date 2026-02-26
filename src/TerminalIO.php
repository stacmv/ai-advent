<?php

namespace AiAdvent;

/**
 * Terminal I/O utilities for proper UTF-8 and international character handling
 */
class TerminalIO
{
    /**
     * Initialize terminal for UTF-8 input/output
     * Called once at script startup
     */
    public static function initializeUTF8(): void
    {
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }
    }

    /**
     * Convert terminal input from Windows encodings to UTF-8
     *
     * On Windows, stdin may arrive in:
     * - Windows-1251 (Cyrillic) for Russian/Eastern European input
     * - Windows-1252 (Latin) for Western European input
     * - UTF-8 (if explicitly configured)
     *
     * This method detects and converts as needed.
     */
    public static function decodeTerminalInput(string $input): string
    {
        // Already valid UTF-8, no conversion needed
        if (mb_check_encoding($input, 'UTF-8')) {
            return $input;
        }

        // Auto-detect encoding from the input
        $detected = mb_detect_encoding(
            $input,
            ['Windows-1251', 'Windows-1252', 'UTF-8', 'ASCII'],
            true
        );

        if ($detected && $detected !== 'UTF-8') {
            // Convert from detected encoding to UTF-8
            $converted = @iconv($detected, 'UTF-8//TRANSLIT', $input);
            if ($converted !== false) {
                return $converted;
            }
        }

        // Fallback: try common Windows encodings
        $encodings = ['Windows-1251', 'Windows-1252', 'ISO-8859-1'];
        foreach ($encodings as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//TRANSLIT', $input);
            if ($converted !== false) {
                return $converted;
            }
        }

        // Last resort: attempt generic conversion
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

        $input = trim(fgets(STDIN));
        return self::decodeTerminalInput($input);
    }
}
