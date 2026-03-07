<div id="student-table-container" class="border-b border-gray-400">
    <div class="max-h-[410px] overflow-y-auto custom-scrollbar">
        <table class="w-full bg-white table-fixed"> 
            <thead class="sticky top-0 z-10 bg-white">
                <tr class="border-b border-gray-400">
                    <th class="py-3 px-4 text-left text-[#003918] text-[11px] font-bold uppercase w-[12%]">LRN</th>
                    <th class="py-3 px-4 text-left text-[#003918] text-[11px] font-bold uppercase w-[18%] relative group">
                        <div class="flex items-center gap-1">
                            Full Name
                            <button onclick="toggleSortMenu()" class="hover:bg-gray-200 p-1 rounded transition-colors focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="currentColor" d="m18 21l-4-4h3V7h-3l4-4l4 4h-3v10h3M2 19v-2h10v2M2 13v-2h7v2M2 7V5h4v2z"/></svg>
                            </button>
                        </div>

                        <div id="sortMenu" class="hidden absolute left-4 mt-2 w-40 bg-white border border-gray-200 rounded-md shadow-xl z-[50] normal-case font-medium">
                            <div class="py-1">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'az']) }}" class="block px-4 py-2 text-[10px] text-gray-700 hover:bg-[#00923F] hover:text-white {{ request('sort') == 'az' ? 'bg-gray-100 font-bold' : '' }}">Sort A-Z</a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'za']) }}" class="block px-4 py-2 text-[10px] text-gray-700 hover:bg-[#00923F] hover:text-white {{ request('sort') == 'za' ? 'bg-gray-100 font-bold' : '' }}">Sort Z-A</a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" class="block px-4 py-2 text-[10px] text-gray-700 hover:bg-[#00923F] hover:text-white {{ request('sort') == 'newest' ? 'bg-gray-100 font-bold' : '' }}">Newest First</a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}" class="block px-4 py-2 text-[10px] text-gray-700 hover:bg-[#00923F] hover:text-white {{ request('sort') == 'oldest' ? 'bg-gray-100 font-bold' : '' }}">Oldest First</a>
                            </div>
                        </div>
                    </th>
                    <th class="py-3 px-4 text-left text-[#003918] text-[11px] font-bold uppercase w-[10%]">Student Type</th>
                    <th class="py-3 px-4 text-center text-[#003918] text-[11px] font-bold uppercase w-[8%] leading-tight">Grade <br> Level</th>
                    <th class="py-3 px-4 text-left text-[#003918] text-[11px] font-bold uppercase w-[10%]">Track</th>
                    <th class="py-3 px-4 text-left text-[#003918] text-[11px] font-bold uppercase w-[10%]">Cluster</th>
                    <th class="py-3 px-4 text-left text-[#003918] text-[11px] font-bold uppercase w-[15%]">Status</th>
                    <th class="py-3 px-4 text-center text-[#003918] text-[11px] font-bold uppercase w-[9%] leading-tight">Requirement <br> Status</th>
                    <th class="py-3 px-4 text-center text-[#003918] text-[11px] font-bold uppercase w-[7%]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($students as $student)
                    @php
                        // 1. Handle Middle Initial
                        $middleInitial = !empty($student->middle_name) 
                            ? strtoupper(substr($student->middle_name, 0, 1)) . '.' 
                            : '';

                        // 2. Format First Name
                        $firstNameTitle = ucwords(strtolower($student->first_name));

                        // 3. Access Extension (Assuming it's in the students table)
                        // If it is in the 'users' table, use: $student->user->extension_name
                        $extension = $student->extension_name ?? '';
                        $nameWithInitial = trim("{$firstNameTitle} {$middleInitial}");

                        // 4. Combine and Clean Spacing
                        $coloredPart = !empty($extension) 
                                ? "{$nameWithInitial}, {$extension}" 
                                : $nameWithInitial;
                    @endphp
                    <tr class="hover:bg-gray-100 transition-colors">
                        <td class="py-3 px-4 text-left text-[11px] text-gray-600 font-medium">{{ $student->lrn }}</td>
                        <td class="py-3 px-4 text-left text-[11px] text-[#003918] font-bold truncate">
                            <span class="uppercase">{{ $student->last_name }},</span>
                            <span class="text-[#003918]/75 font-medium uppercase"> {{ $coloredPart }}</span>
                        </td>
                        <td class="py-3 px-4 text-left text-[11px] text-gray-600">{{ $student->display_status }}</td>
                        <td class="py-3 px-4 text-center text-[11px] text-gray-600">{{ $student->display_grade }}</td>
                        <td class="py-3 px-4 text-left text-[11px] text-gray-600 truncate">{{ $student->display_track }}</td>
                        <td class="py-3 px-4 text-left text-[11px] text-gray-600 truncate">{{ $student->display_cluster }}</td>
                        <td class="py-3 px-4 text-left">
                            <span class="px-2 py-1 rounded-full text-[9px] font-bold uppercase border {{ $student->status_style }}">
                                {{ $student->enrollment_category }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center text-[11px] text-gray-600">—</td>
                        <td class="py-3 px-4 text-center">
                            <a href="{{ route('admin.studentpage.profilepage', ['lrn' => $student->lrn]) }}" 
                               class="text-[10px] font-bold uppercase tracking-wider text-[#005288] hover:text-[#00923F] hover:underline transition-colors">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-20 text-center">
                            <div class="flex flex-col items-center justify-center text-gray-400">
                                <svg class="w-12 h-12 mb-3 opacity-20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                                <p class="text-sm font-bold uppercase tracking-widest opacity-60">
                                    {{ request('search') ? 'No records matching "' . request('search') . '"' : 'No records found' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f9fafb; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #00923F; }
</style>