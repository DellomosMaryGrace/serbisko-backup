<div class="overflow-x-auto">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="border-b border-gray-400">
                <th class="px-10 py-5 text-left text-[#003918] text-[11px] font-bold uppercase">Full Name</th>
                <th class="px-10 py-5 text-center text-[#003918] text-[11px] font-bold uppercase">Role</th>
                <th class="px-10 py-5 text-[#003918] text-[11px] font-bold uppercase text-center">Access</th>
                <th class="px-10 py-5 text-[#003918] text-[11px] font-bold uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($staff as $user)
                @php
                    // 1. Determine Badge Color based on role
                    $roleLabel = 'Facilitator';
                    $badgeColor = 'bg-[#00923F]'; // Default Green

                    if ($user->role === 'super_admin') {
                        $roleLabel = 'Super Admin';
                        $badgeColor = 'bg-[#048F81]'; // Distinct Teal for Super Admin
                    } elseif ($user->role === 'admin') {
                        $roleLabel = 'Administrator';
                        $badgeColor = 'bg-[#005288]'; // Blue
                    }

                    // 2. Access variables from Session
                    $currentRole = session('user_role');
                @endphp

                <tr class="hover:bg-gray-50/30 transition-colors">
                    <td class="px-10 py-4 text-[13px] font-medium text-gray-700">
                        {{ $user->first_name }} {{ $user->middle_name }} {{ $user->last_name }} {{ $user->extension_name }}
                    </td>
                    <td class="px-10 py-4">
                        <div class="flex flex-col items-center gap-2">
                            <span class="w-32 py-1.5 {{ $badgeColor }} rounded-full text-[10px] font-bold text-white text-center uppercase">
                                {{ $roleLabel }}
                            </span>
                        </div>
                    </td>
                    <td class="px-10 py-4">
                        <div class="flex justify-center">
                            @if($user->role !== 'super_admin')
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" class="sr-only peer" {{ $user->active ? 'checked' : '' }}>
                                    <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#00923F]"></div>
                                </label>
                            @endif
                        </div>
                    </td>
                    <td class="px-10 py-4">
                        <div class="flex items-center justify-center gap-8">
                            
                            {{-- Hide all actions if row is a Super Admin --}}
                            @if($user->role !== 'super_admin')
                                
                                {{-- EDIT ROLE: Visible to Super Admin and Admin --}}
                                <button class="flex items-center gap-2 text-[10px] font-bold text-[#00923F] uppercase hover:underline">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit Role
                                </button>

                                {{-- REVOKE ACCESS: Super Admin can revoke anyone; Admin can only revoke Facilitators --}}
                                @if($currentRole === 'super_admin' || ($currentRole === 'admin' && $user->role === 'facilitator'))
                                    <form action="{{ route('admin.destroyUser', $user->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="flex items-center gap-2 text-[10px] font-bold text-red-600 uppercase hover:underline">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="23" y2="14"/><line x1="23" y1="8" x2="17" y2="14"/>
                                            </svg>
                                            Revoke Access
                                        </button>
                                    </form>
                                @endif

                            @else
                                <span class="text-[10px] font-bold text-gray-400 uppercase italic">Protected Account</span>
                            @endif

                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>