<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Phar;
use Throwable;

/**
 * Local analytics for tracking command usage.
 *
 * Only active when running as built binary (PHAR).
 * Disabled during development. No remote telemetry.
 */
class Analytics
{
    public function track(string $command, int $exitCode, array $context, float $startTime): void
    {
        // Only track when running as PHAR (built binary)
        if (! Phar::running()) {
            return;
        }

        try {
            $entry = json_encode([
                'command' => $command,
                'timestamp' => date('c'),
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'context' => $context,
            ], JSON_THROW_ON_ERROR);

            $this->disk()->append('analytics.jsonl', $entry);
        } catch (Throwable) {
            // Silently fail - analytics should never break the command
        }
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $root = realpath(dirname(Phar::running(false))) ?: dirname(Phar::running(false));

        return Storage::build([
            'driver' => 'local',
            'root' => $root,
        ]);
    }
}
