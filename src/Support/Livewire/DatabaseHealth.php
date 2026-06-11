<?php

declare(strict_types=1);

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

        return view('sentinel::livewire.database-health', [
            'diff' => $diff,
            'score' => $diff->getHealthScore(),
        ]);
    }
}
