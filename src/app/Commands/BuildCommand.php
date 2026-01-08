<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

/**
 * Builds self-contained binary from Laravel Zero project.
 *
 * Combines micro.sfx + PHAR into standalone executable.
 * Strips dev dependencies for smaller production binary.
 */
class BuildCommand extends Command
{
    protected $signature = 'build {--no-install : Only build, do not copy to skill root}';

    protected $description = 'Build self-contained binary';

    private string $projectDir;

    public function handle(): int
    {
        $this->projectDir = dirname(__DIR__, 2);
        $skillRoot = dirname($this->projectDir);
        $microPath = $this->projectDir.'/buildroot/bin/micro.sfx';
        $name = config('app.name');

        if (! file_exists($microPath)) {
            $this->error('micro.sfx not found at: '.$microPath);
            $this->line('');
            $this->line('Run these commands first:');
            $this->line('  phpcli-spc-setup --doctor');
            $this->line('  phpcli-spc-build --extensions "ctype,filter,mbstring,mbregex,phar,zlib"');

            return self::FAILURE;
        }

        $boxPath = $this->projectDir.'/vendor/laravel-zero/framework/bin/box';
        if (! file_exists($boxPath)) {
            $this->error('Box not found. Run: composer install');

            return self::FAILURE;
        }

        $buildsDir = $this->projectDir.'/builds';
        if (! is_dir($buildsDir)) {
            mkdir($buildsDir, 0755, true);
        }

        // Strip dev dependencies for smaller binary
        $this->info('Stripping dev dependencies...');
        if (! $this->composerInstall(noDev: true)) {
            return self::FAILURE;
        }

        try {
            $result = $this->buildBinary($boxPath, $microPath, $buildsDir, $name, $skillRoot);
        } finally {
            // Always restore dev dependencies
            $this->info('Restoring dev dependencies...');
            $this->composerInstall(noDev: false);
        }

        return $result;
    }

    private function composerInstall(bool $noDev): bool
    {
        $cmd = sprintf(
            'cd %s && composer install %s --optimize-autoloader --quiet 2>&1',
            escapeshellarg($this->projectDir),
            $noDev ? '--no-dev' : ''
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Composer install failed:');
            $this->line(implode("\n", $output));

            return false;
        }

        return true;
    }

    private function buildBinary(string $boxPath, string $microPath, string $buildsDir, string $name, string $skillRoot): int
    {
        $this->info('Building PHAR...');

        $boxCmd = sprintf(
            'cd %s && php -d phar.readonly=Off %s compile --config=%s 2>&1',
            escapeshellarg($this->projectDir),
            escapeshellarg($boxPath),
            escapeshellarg($this->projectDir.'/box.json')
        );

        $output = [];
        $exitCode = 0;
        exec($boxCmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Box compile failed:');
            $this->line(implode("\n", $output));

            return self::FAILURE;
        }

        $pharPath = $buildsDir.'/'.$name.'.phar';
        if (! file_exists($pharPath)) {
            $this->error('PHAR not created at: '.$pharPath);

            return self::FAILURE;
        }

        $pharSize = round(filesize($pharPath) / 1024 / 1024, 2);
        $this->line("  PHAR: {$pharSize}MB");

        $this->info('Combining with micro.sfx...');

        $binaryPath = $buildsDir.'/'.$name;
        $combineCmd = sprintf(
            'cat %s %s > %s && chmod +x %s',
            escapeshellarg($microPath),
            escapeshellarg($pharPath),
            escapeshellarg($binaryPath),
            escapeshellarg($binaryPath)
        );

        exec($combineCmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($binaryPath)) {
            $this->error('Failed to combine binary');

            return self::FAILURE;
        }

        unlink($pharPath);

        $binarySize = round(filesize($binaryPath) / 1024 / 1024, 2);
        $this->line("  Binary: {$binarySize}MB");

        if (! $this->option('no-install')) {
            $installPath = $skillRoot.'/'.$name;

            $this->info('Installing to skill root...');

            if (! copy($binaryPath, $installPath)) {
                $this->error('Failed to copy to: '.$installPath);

                return self::FAILURE;
            }

            chmod($installPath, 0755);
            $this->line("  Installed: {$installPath}");
        }

        $this->newLine();
        $this->info('Build complete!');

        return self::SUCCESS;
    }
}
