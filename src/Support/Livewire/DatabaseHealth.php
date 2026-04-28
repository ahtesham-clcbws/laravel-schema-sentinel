<?php

namespace Sentinel\SchemaSentinel\Support\Livewire;

use Livewire\Component;
use Sentinel\SchemaSentinel\Facades\Sentinel;

class DatabaseHealth extends Component
{
    public function render()
    {
        if (!app()->environment('local')) {
            return '<div>Dashboard is only available in local environment.</div>';
        }

        $diff = Sentinel::check(strict: true);

        return <<<'HTML'
        <div class="p-6 bg-white shadow rounded-lg">
            <h2 class="text-2xl font-bold mb-4">🛡️ Schema Sentinel Dashboard</h2>
            
            @if(!$diff->hasDifferences())
                <div class="p-4 mb-4 text-green-700 bg-green-100 rounded-lg">
                    Database is in perfect sync with migrations!
                </div>
            @else
                <div class="space-y-4">
                    @if(!empty($diff->missingTables))
                        <div>
                            <h3 class="font-bold text-red-600">Missing Tables:</h3>
                            <ul class="list-disc ml-6">
                                @foreach($diff->missingTables as $table)
                                    <li>{{ $table->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!empty($diff->missingColumns))
                        <div>
                            <h3 class="font-bold text-orange-600">Missing Columns:</h3>
                            <ul class="list-disc ml-6">
                                @foreach($diff->missingColumns as $c)
                                    <li><span class="font-mono">{{ $c['table'] }}</span> -> {{ $c['column']->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
        HTML;
    }
}
