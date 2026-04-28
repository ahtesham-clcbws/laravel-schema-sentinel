<?php

namespace Sentinel\SchemaSentinel\Support;

use Illuminate\Support\Facades\Http;
use Sentinel\SchemaSentinel\DTOs\SchemaDiff;

/**
 * Handles sending alerts to Slack or Discord when drift is detected.
 */
class NotificationManager
{
    public function notify(SchemaDiff $diff): void
    {
        $config = config('schema-sentinel.notifications');

        if (!$config['enabled'] || !$diff->hasDifferences()) {
            return;
        }

        $message = $this->buildMessage($diff);

        if ($config['slack_webhook']) {
            Http::post($config['slack_webhook'], ['text' => $message]);
        }

        if ($config['discord_webhook']) {
            Http::post($config['discord_webhook'], ['content' => $message]);
        }
    }

    protected function buildMessage(SchemaDiff $diff): string
    {
        $summary = "🛡️ *Schema Sentinel Alert*\n";
        $summary .= "Schema drift detected in *[" . config('app.name') . "]* (" . config('app.env') . ")\n\n";

        if (!empty($diff->missingTables)) {
            $summary .= "❌ *Missing Tables:* " . count($diff->missingTables) . "\n";
        }
        
        if (!empty($diff->missingColumns)) {
            $summary .= "🔸 *Missing Columns:* " . count($diff->missingColumns) . "\n";
        }

        if (!empty($diff->mismatchedColumns)) {
            $summary .= "⚠️ *Mismatched Columns:* " . count($diff->mismatchedColumns) . "\n";
        }

        $summary .= "\nRun `php artisan schema:drift` to review and fix.";

        return $summary;
    }
}
