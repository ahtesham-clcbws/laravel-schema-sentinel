<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Sentinel\SchemaSentinel\Tests\TestCase;
use Sentinel\SchemaSentinel\Support\Telemetry;

class TelemetryTest extends TestCase
{
    private string $tempHome;
    private string|bool $oldHome;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tempHome = sys_get_temp_dir() . '/sentinel_test_home_' . uniqid();
        @mkdir($this->tempHome, 0755, true);
        
        $this->oldHome = getenv('HOME');
        putenv("HOME={$this->tempHome}");
    }

    protected function tearDown(): void
    {
        if ($this->oldHome !== false) {
            putenv("HOME={$this->oldHome}");
        } else {
            putenv("HOME");
        }
        
        $this->deleteDir($this->tempHome);
        parent::tearDown();
    }

    public function test_it_dispatches_telemetry_payload_successfully(): void
    {
        Http::fake([
            'https://www.clcbws.com/api/telemetry/package' => Http::response(['status' => 'ok'], 200),
        ]);

        Telemetry::dispatch();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.clcbws.com/api/telemetry/package'
                && $request->method() === 'POST'
                && $request['package_name'] === 'clcbws/laravel-schema-sentinel'
                && isset($request['name'])
                && isset($request['email'])
                && isset($request['php_version'])
                && isset($request['laravel_version'])
                && isset($request['os'])
                && isset($request['project_hash'])
                && $request['package_version'] === '2.0.0'
                && isset($request['machine_fingerprint']);
        });

        // Config file must exist and contain telemetry_sent => true
        $configFile = $this->tempHome . '/.config/laravel-schema-sentinel/config.json';
        $this->assertFileExists($configFile);
        
        $config = json_decode((string) file_get_contents($configFile), true);
        $this->assertTrue($config['telemetry_sent']);
    }

    public function test_it_skips_dispatching_if_already_sent(): void
    {
        Http::fake();

        // Create pre-existing state
        $configDir = $this->tempHome . '/.config/laravel-schema-sentinel';
        @mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/config.json', json_encode(['telemetry_sent' => true]));

        Telemetry::dispatch();

        Http::assertNothingSent();
    }

    public function test_it_silently_ignores_connection_failures(): void
    {
        Http::fake([
            'https://www.clcbws.com/api/telemetry/package' => Http::response('Gateway Timeout', 504),
        ]);

        // This should run without throwing exceptions
        Telemetry::dispatch();

        $configFile = $this->tempHome . '/.config/laravel-schema-sentinel/config.json';
        $this->assertFileDoesNotExist($configFile);
    }

    private function deleteDir(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }
        
        $objects = scandir($dirPath);
        if ($objects === false) {
            return;
        }

        foreach ($objects as $object) {
            if ($object !== '.' && $object !== '..') {
                if (is_dir($dirPath . DIRECTORY_SEPARATOR . $object) && !is_link($dirPath . DIRECTORY_SEPARATOR . $object)) {
                    $this->deleteDir($dirPath . DIRECTORY_SEPARATOR . $object);
                } else {
                    @unlink($dirPath . DIRECTORY_SEPARATOR . $object);
                }
            }
        }
        @rmdir($dirPath);
    }
}
