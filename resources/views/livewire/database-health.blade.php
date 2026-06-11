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
        <div class="space-y-6">
            @if(!empty($diff->missingTables))
                <div>
                    <h3 class="font-bold text-red-600 mb-1">❌ Missing Tables:</h3>
                    <ul class="list-disc ml-6 space-y-1">
                        @foreach($diff->missingTables as $table)
                            <li><span class="font-semibold">{{ $table->name }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($diff->extraTables))
                <div>
                    <h3 class="font-bold text-yellow-600 mb-1">⚠️ Extra Tables (Strict Mode):</h3>
                    <ul class="list-disc ml-6 space-y-1">
                        @foreach($diff->extraTables as $table)
                            <li><span class="font-semibold">{{ $table->name }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($diff->missingColumns))
                <div>
                    <h3 class="font-bold text-red-500 mb-1">❌ Missing Columns:</h3>
                    <ul class="list-disc ml-6 space-y-1">
                        @foreach($diff->missingColumns as $c)
                            <li><span class="font-mono bg-gray-100 px-1 rounded">{{ $c['table'] }}</span> -> <span class="font-semibold">{{ $c['column']->name }}</span> ({{ $c['column']->type }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($diff->extraColumns))
                <div>
                    <h3 class="font-bold text-yellow-500 mb-1">⚠️ Extra Columns:</h3>
                    <ul class="list-disc ml-6 space-y-1">
                        @foreach($diff->extraColumns as $c)
                            <li><span class="font-mono bg-gray-100 px-1 rounded">{{ $c['table'] }}</span> -> <span class="font-semibold">{{ $c['column']->name }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($diff->mismatchedColumns))
                <div>
                    <h3 class="font-bold text-orange-600 mb-1">🔄 Column Mismatches:</h3>
                    <ul class="list-disc ml-6 space-y-2">
                        @foreach($diff->mismatchedColumns as $c)
                            <li>
                                <span class="font-mono bg-gray-100 px-1 rounded">{{ $c['table'] }}</span> -> <span class="font-semibold">{{ $c['live']->name }}</span>
                                <div class="ml-4 text-sm text-gray-600 space-y-0.5">
                                    @foreach($c['diffs'] as $attr => $val)
                                        <div>
                                            <span class="capitalize">{{ $attr }}</span>: 
                                            <span class="text-red-500 line-through">Live [{{ is_bool($val['live']) ? ($val['live'] ? 'true' : 'false') : ($val['live'] ?? 'null') }}]</span> vs 
                                            <span class="text-green-600 font-semibold font-mono">Ideal [{{ is_bool($val['ideal']) ? ($val['ideal'] ? 'true' : 'false') : ($val['ideal'] ?? 'null') }}]</span>
                                        </div>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($diff->missingIndexes))
                <div>
                    <h3 class="font-bold text-indigo-600 mb-1">🔑 Missing Indexes:</h3>
                    <ul class="list-disc ml-6 space-y-1">
                        @foreach($diff->missingIndexes as $idx)
                            <li>
                                Table <span class="font-mono bg-gray-100 px-1 rounded">{{ $idx['table'] }}</span>: 
                                index <span class="font-semibold">{{ $idx['index']->name }}</span> on ({{ implode(', ', $idx['index']->columns) }})
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($diff->missingForeignKeys))
                <div>
                    <h3 class="font-bold text-purple-600 mb-1">🔗 Missing Foreign Keys:</h3>
                    <ul class="list-disc ml-6 space-y-1">
                        @foreach($diff->missingForeignKeys as $fk)
                            <li>
                                <span class="font-mono bg-gray-100 px-1 rounded">{{ $fk['table'] }}</span> -> ({{ implode(', ', $fk['fk']->columns) }}) 
                                references <span class="font-semibold">{{ $fk['fk']->foreignTable }}</span>({{ implode(', ', $fk['fk']->foreignColumns) }})
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
