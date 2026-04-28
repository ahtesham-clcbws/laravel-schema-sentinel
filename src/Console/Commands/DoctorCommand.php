<?php

namespace Sentinel\SchemaSentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * Health check command for Laravel Schema Sentinel.
 */
class DoctorCommand extends Command
{
    protected $signature = 'schema:sentinel-doctor';
    protected $description = 'Check if your environment is properly configured for Schema Sentinel.';

    public function handle(): int
    {
        $this->components->info('Running Schema Sentinel Health Check...');

        $checks = [
            'PHP Version (>= 8.2)' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'PDO SQLite Extension' => extension_loaded('pdo_sqlite'),
            'Config File' => file_exists(\Illuminate\Support\Facades\App::configPath('schema-sentinel.php')) || file_exists(__DIR__.'/../../../config/schema-sentinel.php'),
            'Database Connection' => $this->checkConnection(),
            'Shadow Database' => $this->checkShadowConnection(),
        ];

        $failed = 0;

        foreach ($checks as $label => $passed) {
            if ($passed) {
                $this->components->task($label, fn() => true);
            } else {
                $this->components->task($label, fn() => false);
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->newLine();
            $this->components->error("Doctor found $failed issues. Please resolve them to ensure Sentinel works correctly.");
            return 1;
        }

        $this->newLine();
        $this->components->info('Everything looks good! Sentinel is ready to protect your schema.');
        return 0;
    }

    protected function checkConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function checkShadowConnection(): bool
    {
        try {
            $config = Config::get('schema-sentinel.shadow_connection', [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ]);

            Config::set('database.connections.sentinel_doctor_test', $config);
            DB::connection('sentinel_doctor_test')->getPdo();
            DB::disconnect('sentinel_doctor_test');
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
