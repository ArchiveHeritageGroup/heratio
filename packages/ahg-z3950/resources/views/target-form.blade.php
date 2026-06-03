{{-- Add/edit Z39.50 target form --}}
<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <div class="mb-6">
            <a href="{{ route('z3950.admin') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to admin
            </a>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ __('Add Z39.50 Target') }}</h1>
        <p class="text-sm text-gray-500 mb-8">
            Register a remote Z39.50 server. Common targets include national libraries, union catalogues, and SRU gateways.
        </p>

        <form method="POST" action="{{ route('z3950.target.store') }}">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }}</label>
                    <input type="text" id="name" name="name"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="{{ __('Library of Congress Z39.50') }}" required value="{{ old('name') }}">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Host') }}</label>
                        <input type="text" id="host" name="host"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                               placeholder="{{ __('lx2.loc.gov') }}" required value="{{ old('host') }}">
                        @error('host')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="port" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Port') }}</label>
                        <input type="number" id="port" name="port"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="210" required value="{{ old('port', 210) }}">
                        @error('port')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="database" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Database') }}</label>
                    <input type="text" id="database" name="database"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                           placeholder="{{ __('LCDB') }}" required value="{{ old('database') }}">
                    @error('database')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="syntax" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Record syntax') }}</label>
                        <select id="syntax" name="syntax" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            <option value="USmarc">{{ __('USmarc') }}</option>
                            <option value="MARC21">{{ __('MARC21') }}</option>
                            <option value="SUTRS">{{ __('SUTRS') }}</option>
                            <option value="XML">XML</option>
                        </select>
                    </div>
                    <div>
                        <label for="element_set" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Element set') }}</label>
                        <select id="element_set" name="element_set" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            <option value="F">{{ __('F — Full') }}</option>
                            <option value="B">{{ __('B — Brief') }}</option>
                            <option value="S">{{ __('S — Suggested') }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="active" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" checked>
                        <span class="text-sm text-gray-700">Active (visible in search form)</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                    Save target
                </button>
                <a href="{{ route('z3950.admin') }}" class="px-6 py-2.5 text-gray-600 font-medium rounded-lg hover:bg-gray-100 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>