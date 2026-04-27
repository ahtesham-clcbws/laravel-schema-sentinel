<?php

namespace Sentinel\SchemaSentinel\Facades;

use Illuminate\Support\Facades\Facade;
use Sentinel\SchemaSentinel\DTOs\SchemaDiff;

/**
 * @method static SchemaDiff check(bool $strict = false)
 * 
 * @see \Sentinel\SchemaSentinel\Sentinel
 */
class Sentinel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-schema-sentinel';
    }
}
