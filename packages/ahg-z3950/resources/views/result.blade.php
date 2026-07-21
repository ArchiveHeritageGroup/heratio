{{-- Browse Z39.50 result set --}}
<x-app-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="mb-6">
            <a href="{{ route('z3950.search') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                New search
            </a>
        </div>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('Search Results') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ count($records) }} records retrieved - {{ $syntax }} / {{ $elementSet }}</p>
            </div>
            <form method="POST" action="{{ route('z3950.import-batch') }}">
                @csrf
                <input type="hidden" name="result_set" value="{{ $resultSet }}">
                <input type="hidden" name="record_numbers" value="all">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Import all records
                </button>
            </form>
        </div>

        @if(count($records) === 0)
            <div class="text-center py-12 text-gray-400">
                <p>No records in this result set.</p>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500 w-16">#</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Title') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Author') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('ISBN') }}</th>
                            <th class="text-right px-5 py-2.5 font-medium text-gray-500">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($records as $i => $record)
                            {{-- Records are pre-parsed by the controller; the
                                 service is not exposed to this view. --}}
                            @php($fields = $parsed[$i] ?? [])
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-400">{{ $i + 1 }}</td>
                                <td class="px-5 py-3 font-medium text-gray-900">
                                    {{ $fields['245']['a'] ?? 'Unknown title' }}
                                </td>
                                <td class="px-5 py-3 text-gray-500">
                                    {{ $fields['100']['a'] ?? $fields['110']['a'] ?? '-' }}
                                </td>
                                <td class="px-5 py-3 text-gray-500 font-mono text-xs">
                                    {{ $fields['020']['a'] ?? '' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('z3950.import', [$resultSet, $i]) }}"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                        Import
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- MARC viewer --}}
            <div class="mt-8">
                <h2 class="font-semibold text-gray-900 mb-4">{{ __('Record details') }}</h2>
                <div class="space-y-4">
                    @foreach($records as $i => $record)
                        @php($fields = $parsed[$i] ?? [])
                        <details class="bg-white border border-gray-200 rounded-xl">
                            <summary class="px-5 py-3.5 cursor-pointer font-medium text-gray-800 list-none flex items-center justify-between">
                                <span>#{{ $i + 1 }} - {{ $fields['245']['a'] ?? 'Unknown title' }}</span>
                                <svg class="w-4 h-4 text-gray-400 details-toggle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </summary>
                            <div class="px-5 pb-4">
                                <pre class="text-xs font-mono text-gray-600 bg-gray-50 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap">{{ $record }}</pre>
                                <div class="mt-3 flex gap-2">
                                    <a href="{{ route('z3950.import', [$resultSet, $i]) }}"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                        Import this record
                                    </a>
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>