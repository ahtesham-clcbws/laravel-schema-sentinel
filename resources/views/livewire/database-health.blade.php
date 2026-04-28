<div class="p-6 bg-white shadow rounded-lg">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold">🛡️ Schema Sentinel Dashboard</h2>
        <div class="px-4 py-2 {{ $score > 90 ? 'bg-green-100 text-green-800' : ($score > 70 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }} rounded-full font-bold">
            Health Score: {{ $score }}%
        </div>
    </div>
    
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
