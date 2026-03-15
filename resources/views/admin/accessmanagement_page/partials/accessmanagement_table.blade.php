<div class="overflow-x-auto overflow-y-visible pb-44">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="border-b border-gray-400">
                <th class="px-10 py-5 text-left text-[#003918] text-[11px] font-bold uppercase">Full Name</th>
                <th class="px-10 py-5 text-center text-[#003918] text-[11px] font-bold uppercase">Role</th>
                <th class="px-10 py-5 text-[#003918] text-[11px] font-bold uppercase text-center">Status</th>
                <th class="px-10 py-5 text-[#003918] text-[11px] font-bold uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($staff as $user)
                @php
                    $roleLabel = 'Facilitator';
                    $badgeColor = 'bg-[#00923F]';

                    if ($user->role === 'super_admin') {
                        $roleLabel = 'Super Admin';
                        $badgeColor = 'bg-[#048F81]';
                    } elseif ($user->role === 'admin') {
                        $roleLabel = 'Administrator';
                        $badgeColor = 'bg-[#005288]';
                    }

                    $currentRole = session('user_role');
                    $isArchived = request('role') === 'Archived';
                @endphp

                <tr class="hover:bg-gray-50/30 transition-colors relative">
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
                        <div class="flex justify-center items-center" 
                            x-data="{ isOnline: {{ \Illuminate\Support\Facades\Cache::has('user-is-online-' . $user->id) ? 'true' : 'false' }} }" 
                            x-init="setInterval(() => { 
                                fetch('/admin/check-user-status/{{ $user->id }}')
                                    .then(res => res.json())
                                    .then(data => isOnline = data.online) 
                            }, 10000)"> 
                            
                            <template x-if="isOnline">
                                <span class="flex items-center gap-1.5 text-[10px] font-bold text-green-600 uppercase">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse"></span>
                                    Online
                                </span>
                            </template>

                            <template x-if="!isOnline">
                                <span class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400 uppercase">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    Offline
                                </span>
                            </template>
                        </div>
                    </td>
                    <td class="px-10 py-4 relative z-20">
                        <div class="flex items-center justify-center gap-8">
                            
                            @if($user->role !== 'super_admin')
                                
                                @if($isArchived)
                                    <form action="{{ route('admin.restoreUser', $user->id) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button" 
                                            @click="$dispatch('open-confirm-modal', { 
                                                url: '{{ route('admin.restoreUser', $user->id) }}',
                                                title: 'Restore Access?',
                                                message: 'This will allow the user to log back into the system immediately.',
                                                buttonText: 'RESTORE',
                                                buttonClass: 'bg-[#00923F] hover:bg-[#003918]'
                                            })"
                                            class="flex items-center gap-2 text-[10px] font-bold text-blue-600 uppercase hover:underline">
                                            Restore Access
                                        </button>
                                    </form>
                                @else
                                    <div class="relative inline-block text-left">
                                        <button type="button" 
                                                onclick="toggleDropdown(event, 'dropdown-{{ $user->id }}')"
                                                class="flex items-center gap-2 text-[10px] font-bold text-[#00923F] uppercase hover:underline">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            EDIT ROLE
                                        </button>

                                        <div id="dropdown-{{ $user->id }}" 
                                            class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-[100]">
                                            <div class="py-1" role="menu">
                                                <form action="{{ route('admin.updateRole', $user->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    
                                                    <button type="submit" name="role" value="admin" 
                                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $user->role == 'admin' ? 'bg-blue-50 font-bold' : '' }}">
                                                        Administrator
                                                    </button>

                                                    <button type="submit" name="role" value="facilitator" 
                                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $user->role == 'facilitator' ? 'bg-blue-50 font-bold' : '' }}">
                                                        Facilitator
                                                    </button>

                                                    <button type="submit" name="role" value="super_admin" 
                                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $user->role == 'super_admin' ? 'bg-blue-50 font-bold' : '' }}">
                                                        Super Admin
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    @if($currentRole === 'super_admin' || ($currentRole === 'admin' && $user->role === 'facilitator'))
                                        <button type="button" 
                                            @click="$dispatch('open-confirm-modal', { 
                                                url: '{{ route('admin.destroyUser', $user->id) }}',
                                                title: 'Are you sure?',
                                                message: 'This action will revoke this user\'s access.',
                                                buttonText: 'REVOKE',
                                                buttonClass: 'bg-red-600 hover:bg-red-700'
                                            })"
                                            class="flex items-center gap-2 text-[10px] font-bold text-red-600 uppercase hover:underline">
                                            Revoke Access
                                        </button>
                                    @endif
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

<div x-data="{ open: false, postUrl: '', title: '', message: '', buttonText: '', buttonClass: '' }" 
     @open-confirm-modal.window="
        open = true; 
        postUrl = $event.detail.url; 
        title = $event.detail.title; 
        message = $event.detail.message; 
        buttonText = $event.detail.buttonText;
        buttonClass = $event.detail.buttonClass;
     "
     x-show="open" 
     x-cloak
     class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    
    <div @click="open = false" class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 text-center">
        <h3 class="text-xl font-black text-[#003918] uppercase tracking-tight mb-2" x-text="title"></h3>
        <p class="text-gray-500 text-sm mb-8" x-text="message"></p>

        <div class="flex gap-4">
            <button @click="open = false" class="flex-1 px-6 py-3 bg-[#E8EEF4] text-gray-600 font-bold rounded-xl hover:bg-gray-200 transition-colors">CANCEL</button>
            <form :action="postUrl" method="POST" class="flex-1">
                @csrf
                <template x-if="buttonText === 'REVOKE'"><input type="hidden" name="_method" value="DELETE"></template>
                <template x-if="buttonText === 'RESTORE'"><input type="hidden" name="_method" value="PATCH"></template>
                
                <button type="submit" 
                        :class="buttonClass"
                        class="w-full px-6 py-3 text-white font-bold rounded-xl shadow-md transition-all" 
                        x-text="buttonText"></button>
            </form>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
function toggleDropdown(event, id) {
    // Prevent the click from bubbling up to the window listener
    event.stopPropagation();
    
    const dropdown = document.getElementById(id);
    
    // Hide all other open dropdowns
    document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
        if (el.id !== id) el.classList.add('hidden');
    });

    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

// Close dropdown if clicking anywhere else on the page
window.addEventListener('click', function(event) {
    // Only close if the click wasn't inside the dropdown itself
    if (!event.target.closest('[id^="dropdown-"]')) {
        document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
            el.classList.add('hidden');
        });
    }
});

</script>