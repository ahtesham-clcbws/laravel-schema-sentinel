<?php

declare(strict_types=1);

namespace Sentinel\SchemaSentinel\Tests\Feature;

use Sentinel\SchemaSentinel\Tests\TestCase;

class HelpCommandTest extends TestCase
{
    public function test_it_displays_welcome_board_when_no_arguments()
    {
        $this->artisan('schema:help')
            ->expectsOutputToContain('Laravel Schema Sentinel - Interactive Help Board')
            ->expectsOutputToContain('Available Commands:')
            ->expectsOutputToContain('schema:drift')
            ->assertExitCode(0);
    }

    public function test_it_displays_details_on_exact_match()
    {
        $this->artisan('schema:help schema:drift')
            ->expectsOutputToContain('Command: schema:drift')
            ->expectsOutputToContain('Audit database schema drift against migration blueprints')
            ->assertExitCode(0);
    }

    public function test_it_corrects_typos_using_lookalike_suggestions()
    {
        // "drft" should suggest "schema:drift"
        $this->artisan('schema:help drft')
            ->expectsOutputToContain('Command "drft" not found. Did you mean "schema:drift"?')
            ->expectsOutputToContain('Command: schema:drift')
            ->assertExitCode(0);
    }

    public function test_it_warns_on_completely_unknown_commands()
    {
        $this->artisan('schema:help completelyunknowncommand')
            ->expectsOutputToContain('Command "completelyunknowncommand" not recognized in Schema Sentinel')
            ->assertExitCode(1);
    }
}
