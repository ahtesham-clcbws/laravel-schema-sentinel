<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Sentinel\SchemaSentinel\Tests\TestCase;
use Sentinel\SchemaSentinel\Support\Telemetry;

class TelemetryTest extends TestCase
{
    public function test_it_does_not_send_network_requests(): void
    {
        Http::fake();

        $data = Telemetry::dispatch();

        Http::assertNothingSent();
        $this->assertSame('clcbws/laravel-schema-sentinel', $data['package_name']);
        $this->assertFalse($data['telemetry_enabled']);
    }
}
