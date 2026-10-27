<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Support;

/**
 * Diagnostic and environment helper.
 * 
 * In v2.1.0, external telemetry exfiltration is completely removed.
 * This class remains strictly local for diagnostic reporting.
 */
class Telemetry
{
    /**
     * Diagnostic report generator (local-only, zero network requests).
     */
    public static function dispatch(): array
    {
        return [
            'package_name' => 'clcbws/laravel-schema-sentinel',
            'version' => '2.2.0',
            'php_version' => PHP_VERSION,
            'laravel_version' => \Illuminate\Support\Facades\App::version(),
            'os' => PHP_OS_FAMILY,
            'telemetry_enabled' => false,
        ];
    }
}
