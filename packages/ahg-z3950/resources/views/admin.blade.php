{{-- Z39.50 admin: targets + query/import logs --}}
<x-app-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="mb-6">
            <a href="{{ route('z3950.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Z39.50
            </a>
        </div>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('Z39.50 Admin') }}</h1>
                <p class="text-sm text-gray-500 mt-1">Manage targets and view connection history.</p>
            </div>
            <a href="{{ route('z3950.target.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + Add target
            </a>
        </div>

        {{-- Targets --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('Targets') }}</h2>
            </div>
            @if($targets->isEmpty())
                <div class="text-center py-10 text-gray-400">
                    No targets configured.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Name') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Host') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Port') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Database') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Syntax') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Status') }}</th>
                            <th class="text-right px-5 py-2.5 font-medium text-gray-500">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($targets as $target)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-900">{{ $target->name }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $target->host }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $target->port }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $target->database }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $target->syntax }}</td>
                                <td class="px-5 py-3">
                                    @if($target->active)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 px-2 py-1 rounded-full">Active</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-400 bg-gray-100 px-2 py-1 rounded-full">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('z3950.target.delete', $target->id) }}" onsubmit="return confirm('Remove target \'{{ addslashes($target->name) }}\'?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">{{ __('Remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Recent queries --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('Recent queries') }}</h2>
            </div>
            @if($recentQueries->isEmpty())
                <div class="text-center py-8 text-gray-400 text-sm">No queries yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('When') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Target') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Query') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Results') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Elapsed') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($recentQueries as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-500 text-xs whitespace-nowrap">{{ $log->created_at }}</td>
                                <td class="px-5 py-3 font-medium text-gray-900 text-xs">{{ $log->target_name }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ Str::limit($log->query, 40) }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $log->result_count }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $log->elapsed_ms }}ms</td>
                                <td class="px-5 py-3">
                                    @if($log->error)
                                        <span class="text-xs text-red-600">Error</span>
                                    @else
                                        <span class="text-xs text-green-600">OK</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Recent imports --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('Recent imports') }}</h2>
            </div>
            @if($recentImports->isEmpty())
                <div class="text-center py-8 text-gray-400 text-sm">No imports yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('When') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Result set') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Record #') }}</th>
                            <th class="text-right px-5 py-2.5 font-medium text-gray-500">{{ __('Works created') }}</th>
                            <th class="text-right px-5 py-2.5 font-medium text-gray-500">{{ __('Instances created') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($recentImports as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-500 text-xs whitespace-nowrap">{{ $log->created_at }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $log->result_set }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $log->record_number + 1 }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $log->works_created }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $log->instances_created }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>