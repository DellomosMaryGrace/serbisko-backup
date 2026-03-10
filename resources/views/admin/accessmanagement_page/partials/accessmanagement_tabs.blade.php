<div x-data="{ activeTab: '{{ request('role', 'All') }}' }" class="flex justify-between items-end border-b border-gray-400">
    <div class="flex gap-6">
        @foreach(['All', 'Administrator', 'Facilitator'] as $tab)
            <button 
                {{-- Redirect using the route name and passing the role --}}
                @click="window.location.href = '{{ route('admin.accessmanagement', ['role' => $tab]) }}'"
                
                {{-- Visual active state logic --}}
                :class="activeTab === '{{ $tab }}' ? 'text-[#005288] border-[#005288] font-bold' : 'text-gray-500 border-transparent font-medium'"
                class="pb-3 px-2 transition-all duration-200 text-sm border-b-4 -mb-[1px]">
                {{ $tab }}
            </button>
        @endforeach
    </div>

    <div class="flex items-center gap-4 pb-3">
        <div class="flex items-center gap-2">
            {{-- Add User  Button --}}
            <button @click="showRegistration = true" class="flex items-center gap-2 px-4 py-1.5 bg-[#005288] text-white font-semibold text-xs rounded-lg hover:bg-[#003d66] transition-colors shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add User
            </button>
        </div>
    </div>
</div>