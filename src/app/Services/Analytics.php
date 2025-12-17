<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Local analytics for tracking command usage.
 *
 * Stores data in analytics.jsonl alongside the skill binary.
 * No remote telemetry - all data stays local.
 */
class Analytics
{
    public function track(string $command, int $exitCode, array $context, float $startTime): void
    {
        $entry = json_encode([
            'command' => $command,
            'timestamp' => date('c'),
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            'context' => $context,
        ]);

        $this->disk()->append('analytics.jsonl', $entry);
    }

    private function disk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        return Storage::build([
            'driver' => 'local',
            'root' => $this->getSkillRoot(),
        ]);
    }

    private function getSkillRoot(): string
    {
        if (\Phar::running()) {
            return dirname(\Phar::running(false));
        }

        return dirname(__DIR__, 3);
    }
}
