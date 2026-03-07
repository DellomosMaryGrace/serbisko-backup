<div class="bg-[#F7FBF9]/50 rounded-2xl shadow-lg overflow-hidden border-t-8 border-[#1a8a44] mb-10">
    <div class="bg-white px-4 py-3 flex justify-between items-center pr-4 relative">
        <h2 class="text-xl font-black text-[#003918] uppercase tracking-tighter">
            Recent Kiosk Submissions
        </h2>
        <a href="{{ route('admin.students', ['status' => 'Document Verified', 'grade_level' => request('grade_level')]) }}"
        id="view-all-link"
        class="text-[#0c4222] text-sm underline hover:text-[#00923F] transition">
            View All
        </a>
    </div>

    <div class="p-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[11px] md:text-xs font-bold text-[#003918] uppercase border-b-2 border-black py-4 px-2 text-sm">
                        <th class="pb-2 px-2">Full Name</th>
                        <th class="pb-2 px-2 text-center">Grade Level</th>
                        <th class="pb-2 px-2 text-center">Track</th>
                        <th class="pb-2 px-2 text-center">Cluster</th>
                        <th class="pb-2 px-2 text-center">Submission Time & Date</th>
                        <th class="pb-2 px-2 text-center">Requirements Status</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600">
                    @forelse($recentKioskSubmissions as $submission)
                        <tr class="border-b border-gray-200 hover:bg-gray-100 transition-colors">
                            {{-- Full Name --}}
                            <td class="py-4 px-2 text-left text-[12px] text-gray-700 font-bold uppercase">
                                {{ $submission->first_name }} 
                                {{ $submission->middle_name ? substr($submission->middle_name, 0, 1) . '.' : '' }} 
                                {{ $submission->last_name }} 
                                {{ $submission->user->extension_name ?? '' }}
                            </td>

                            {{-- Grade Level --}}
                            <td class="py-4 px-2 text-center text-[12px] text-gray-600 font-medium">
                                {{ $submission->grade_level ?? '—' }}
                            </td>

                            {{-- Track --}}
                            <td class="py-4 px-2 text-center text-[12px] text-gray-600 font-medium">
                                {{ $submission->track ?? '—' }}
                            </td>

                            {{-- Cluster --}}
                            <td class="py-4 px-2 text-center text-[12px] text-gray-600 font-medium uppercase">
                                {{ $submission->cluster ?? '—' }}
                            </td>

                            {{-- Time and Date using completed_at --}}
                            <td class="py-4 px-2 text-center text-xs md:text-sm">
                                @if(isset($submission->completed_at) && $submission->completed_at)
                                    <div class="font-bold text-[#00568d]">
                                        {{ \Carbon\Carbon::parse($submission->completed_at)->format('h:i A') }}
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        {{ \Carbon\Carbon::parse($submission->completed_at)->format('M d, Y') }}
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Requirements Status --}}
                            <td class="py-4 px-2 text-center">
                                @if($submission->status === 'Officially Enrolled')
                                    <span class="inline-block bg-[#009444] text-white text-[10px] font-black px-4 py-1.5 rounded-lg shadow-sm min-w-[110px] uppercase tracking-widest">
                                        Complete
                                    </span>
                                @else
                                    <span class="inline-block bg-[#D32F2F] text-white text-[10px] font-black px-2 py-1 rounded-lg shadow-sm min-w-[110px] uppercase tracking-widest">
                                        Incomplete
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-gray-400 italic">
                                No recent kiosk submissions found.
                            </td>
                        </tr>
                    @endforelse

                    {{-- Dynamic Spacing Rows --}}
                    @if($recentKioskSubmissions->count() > 0 && $recentKioskSubmissions->count() < 5)
                        @for ($i = 0; $i < (5 - $recentKioskSubmissions->count()); $i++)
                            <tr class="border-b border-gray-50">
                                <td colspan="5" class="py-6"></td>
                            </tr>
                        @endfor
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewAllLink = document.getElementById('view-all-link');
    const buttons = document.querySelectorAll('.segmented-control-button');

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // 1. Get the grade from the data-grade attribute
            const gradeLevel = this.dataset.grade; 
            
            // 2. Base URL (Ensure it uses the correct status)
            let baseUrl = "{{ route('admin.students', ['status' => 'Document Verified']) }}";
            
            // 3. Update the href based on selection
            if (gradeLevel && gradeLevel !== 'All') {
                viewAllLink.href = `${baseUrl}&grade_level=${encodeURIComponent(gradeLevel)}`;
            } else {
                viewAllLink.href = baseUrl;
            }
            
            // Optional: Log to console to verify it's changing
            console.log("Updated Link to:", viewAllLink.href);
        });
    });
});
</script>