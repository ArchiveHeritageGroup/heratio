{{-- Z39.50 integration dashboard --}}
<x-app-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('Z39.50 Client &amp; Server') }}</h1>
                <p class="text-sm text-gray-500 mt-1">Search remote bibliographic targets and import records.</p>
            </div>
            <a href="{{ route('z3950.admin') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Admin
            </a>
        </div>

        {{-- Extension status --}}
        <div class="mb-6">
            @if($stats['yaz_available'])
                <div class="flex items-center gap-2 text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <strong>yaz</strong> extension loaded — Z39.50 client is ready.
                </div>
            @else
                <div class="flex items-center gap-2 text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 17.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <strong>yaz</strong> extension not installed. Install with <code class="font-mono bg-amber-100 px-1 rounded">pecl install yaz</code> or <code class="font-mono bg-amber-100 px-1 rounded">apt-get install php-yaz</code>.
                </div>
            @endif
        </div>

        {{-- Stats cards --}}
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_targets']) }}</div>
                <div class="text-sm text-gray-500 mt-1">Configured targets</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_searches']) }}</div>
                <div class="text-sm text-gray-500 mt-1">Searches run</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_imports']) }}</div>
                <div class="text-sm text-gray-500 mt-1">Records imported</div>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <a href="{{ route('z3950.search') }}"
               class="group flex items-start gap-4 bg-white border border-gray-200 rounded-xl px-5 py-5 hover:border-indigo-300 hover:shadow-sm transition">
                <div class="flex-shrink-0 w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center text-indigo-600 group-hover:bg-indigo-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-900">Search remote target</div>
                    <div class="text-sm text-gray-500 mt-0.5">Query a Z39.50 server using bib-1 attributes</div>
                </div>
            </a>

            <a href="{{ route('z3950.admin') }}"
               class="group flex items-start gap-4 bg-white border border-gray-200 rounded-xl px-5 py-5 hover:border-indigo-300 hover:shadow-sm transition">
                <div class="flex-shrink-0 w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center text-indigo-600 group-hover:bg-indigo-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-900">Manage targets</div>
                    <div class="text-sm text-gray-500 mt-0.5">Add, edit, or remove Z39.50 target profiles</div>
                </div>
            </a>
        </div>

        {{-- Recent targets --}}
        @if($targets->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-900">{{ __('Configured targets') }}</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Name') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Host') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Port') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Database') }}</th>
                            <th class="text-left px-5 py-2.5 font-medium text-gray-500">{{ __('Syntax') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($targets as $target)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-900">{{ $target->name }}</td>
                                <td class="px-5 py-3 text-gray-500 font-mono text-xs">{{ $target->host }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $target->port }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $target->database }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $target->syntax }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-10 text-gray-400 border border-dashed border-gray-200 rounded-xl">
                <p>No targets configured.</p>
                <a href="{{ route('z3950.admin') }}" class="text-indigo-600 hover:underline text-sm mt-1 inline-block">Add your first target</a>
            </div>
        @endif

        {{-- Protocol info --}}
        <div class="mt-8 bg-gray-50 rounded-xl border border-gray-200 px-5 py-4">
            <h3 class="font-semibold text-gray-700 text-sm mb-2">{{ __('About Z39.50') }}</h3>
            <p class="text-xs text-gray-500 leading-relaxed">
                Z39.50 is a client-server protocol for information retrieval (ANSI/NISO Z39.50, ISO 23950).
                It uses the bib-1 attribute set to define query operators (title, author, ISBN, subject, etc.).
                Records are returned in USmarc/MARC21 format. Common targets include national libraries (Library of Congress, BL),
                union catalogues (WorldCat via OCLC), and SRU/Z39.50 gateways.
            </p>
        </div>
    </div>
</x-app-layout>