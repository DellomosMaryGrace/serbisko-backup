<div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full">
    
    <div class="bg-[#F7FBF9]/50 rounded-2xl shadow-lg border-t-8 border-[#1a8a44] p-4 flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-black text-[#003918] uppercase tracking-tighter">Total Registrations</h3>
            <p class="text-gray-500 text-[10px] italic mb-2">Students who successfully completed and submitted the Google Form</p>
            <div class="text-3xl font-medium text-[#0c4222] mb-2">
                {{ number_format($totalRegistrations) }}
            </div>
        </div>
        <div>
            <div class="flex items-center gap-3">
                {{-- Replace the Form with this Button --}}
                <button type="button" 
                        x-data="{ syncing: false }" 
                        @click="
                            if(syncing) return;
                            syncing = true;
                            fetch('{{ route('admin.sync.perform') }}', { 
                                method: 'POST', 
                                headers: { 
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'X-Requested-With': 'XMLHttpRequest'
                                } 
                            })
                            .then(() => {
                                $dispatch('refresh-dashboard'); // Tells the main blade to update numbers
                                syncing = false;
                            })
                            .catch(() => syncing = false)
                        "
                        :class="syncing ? 'opacity-75 cursor-not-allowed' : ''"
                        class="flex items-center gap-2 py-1 text-[#005288] font-bold text-md hover:text-[#005288]/50 transition-colors">
                    <svg class="w-4 h-4" :class="syncing ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span x-text="syncing ? 'Syncing...' : 'Sync Data'"></span>
                </button>
            </div>
            <div class="text-gray-400 text-[9px] font-medium">Last Synced {{ $lastSyncTime }}</div>
        </div>
    </div>

    <div class="bg-[#F7FBF9]/50 rounded-2xl shadow-lg border-t-8 border-[#1a8a44] p-4 flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-black text-[#003918] uppercase tracking-tighter">Total Submissions Received</h3>
            <p class="text-gray-500 text-[10px] italic mb-2">Applicants who have submitted required documents through the Serbisko Kiosk</p>
            <div class="text-3xl font-medium text-[#0c4222] mb-2">
                {{ number_format($totalSubmissions) }}
            </div>
        </div>
        <a href="{{ route('admin.students', ['status' => 'Document Verified', 'grade_level' => request('grade_level')]) }}" 
        class="text-[#00568d] font-bold underline text-md hover:text-[#005288]/50 transition-colors inline-block mt-auto">
            View
        </a>
    </div>

    <div class="bg-[#F7FBF9]/50 rounded-2xl shadow-lg border-t-8 border-[#1a8a44] p-4 flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-black text-[#003918] uppercase tracking-tighter">Total Enrolled Students</h3>
            <p class="text-gray-500 text-[10px] italic mb-2">Students who are successfully enrolled in DepEd LIS</p>
            <div class="text-3xl font-medium text-[#0c4222] mb-2">
                {{ number_format($totalEnrolled) }}
            </div>
        </div>
        <a href="{{ route('admin.students', ['status' => 'Officially Enrolled']) }}" 
           class="text-[#00568d] font-bold underline text-md hover:text-[#005288]/50 transition-colors inline-block mt-auto">
            View
        </a>
    </div>
</div>