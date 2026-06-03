{{-- Z39.50 search form --}}
<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4">
        <div class="mb-6">
            <a href="{{ route('z3950.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Z39.50
            </a>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ __('Search a Z39.50 Target') }}</h1>
        <p class="text-sm text-gray-500 mb-8">
            Build a CQL query using field prefixes (<code>title=</code>, <code>author=</code>, <code>isbn=</code>, <code>subject=</code>).
            Use <code>AND</code> / <code>OR</code> to combine terms. Append <code>*</code> for right truncation.
        </p>

        {{-- Target selector --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <h2 class="font-semibold text-gray-900 mb-4">{{ __('1. Select target') }}</h2>

            @if($targets->isEmpty())
                <div class="text-center py-6 text-gray-400">
                    <p>No active targets configured.</p>
                    <a href="{{ route('z3950.admin') }}" class="text-indigo-600 hover:underline text-sm mt-1 inline-block">Add a target first</a>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($targets as $target)
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50/30">
                            <input type="radio" name="target_id" value="{{ $target->id }}"
                                   class="text-indigo-600 focus:ring-indigo-500"
                                   required>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ $target->name }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $target->host }}:{{ $target->port }} / {{ $target->database }}</div>
                            </div>
                            <span class="text-xs text-gray-400">{{ $target->syntax }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Query builder --}}
        <form method="POST" action="{{ route('z3950.search-run') }}">
            @csrf
            <input type="hidden" name="target_id" id="target_id">

            <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
                <h2 class="font-semibold text-gray-900 mb-4">{{ __('2. Build query') }}</h2>

                <div class="mb-4">
                    <label for="query" class="block text-sm font-medium text-gray-700 mb-1">
                        CQL Query string
                    </label>
                    <input type="text" id="query" name="query"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                           placeholder="{{ __('title=digital preservation AND author=smith*') }}"
                           required>
                    @error('query')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="syntax" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Record syntax') }}</label>
                        <select id="syntax" name="syntax" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            <option value="USmarc">{{ __('USmarc') }}</option>
                            <option value="MARC21">{{ __('MARC21') }}</option>
                            <option value="XML">XML</option>
                            <option value="SUTRS">{{ __('SUTRS') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="element_set" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Element set') }}</label>
                        <select id="element_set" name="element_set" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            <option value="F">{{ __('F (Full)') }}</option>
                            <option value="B">{{ __('B (Brief)') }}</option>
                            <option value="S">{{ __('S (Suggested)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="max_records" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Max records') }}</label>
                        <input type="number" id="max_records" name="max_records"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"
                               value="100" min="1" max="1000">
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6">
                <h3 class="font-medium text-gray-700 text-sm mb-2">{{ __('Query examples') }}</h3>
                <div class="space-y-1 text-xs font-mono text-gray-500">
                    <div><code>title=preservation</code> — works with "preservation" in the title</div>
                    <div><code>author=pieterse AND isbn=978*</code> — by author with ISBN starting 978</div>
                    <div><code>subject=archives AND subject=digitization</code> — both subject terms</div>
                    <div><code>subject=metadata*</code> — right truncation (metadata, metadatamanagement, etc.)</div>
                </div>
            </div>

            <button type="submit" @click.prevent="$el.previousElementSibling.querySelector('#target_id').value = document.querySelector('input[name=target_id]:checked')?.value; if(!$el.previousElementSibling.querySelector('#target_id').value){alert('Select a target first');return;} $el.closest('form').submit();"
                    class="w-full sm:w-auto px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                Search
            </button>
        </form>
    </div>
</x-app-layout>