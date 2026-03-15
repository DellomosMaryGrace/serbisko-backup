<header class="flex justify-between items-center px-16 py-8 bg-transparent w-full">
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <div class="page-title">
        <h1 class="text-[#00923F] text-5xl font-[800] tracking-tight">
            @yield('page_title', 'Dashboard') 
        </h1>
    </div>

    <div class="flex-1 flex justify-end px-6">
        @yield('header_content')
    </div>

    <div class="flex items-center gap-8">
        <button class="text-[#00923F] p-2 hover:bg-[#00923F]/5 rounded-full transition-colors relative">
            <svg class="w-9 h-9 fill-current" viewBox="0 0 24 24">
                <path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2m6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1z"/>
            </svg>
            <span class="absolute top-2 right-2.5 w-2 h-2 bg-red-500 rounded-full border-2 border-[#F1F3F2]"></span>
        </button>

        <div class="h-10 w-[1.5px] bg-gray-300/60"></div>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" @click.away="open = false" 
                    class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-[#00923F]/5 transition-all duration-200 focus:outline-none group">
                
                <div class="text-right">
                    <p class="text-[#003918] font-bold text-sm leading-tight">
                        {{ auth()->user()?->first_name ?? 'Guest' }} {{ auth()->user()?->last_name }}
                    </p>
                    <p class="text-gray-500 text-[11px] font-semibold uppercase tracking-wide">
                        {{ str_replace('_', ' ', auth()->user()?->role ?? 'No Role') }}
                    </p>
                </div>

                <svg class="w-5 h-5 text-gray-400 group-hover:text-[#00923F] transition-transform duration-200" 
                    :class="{'rotate-180': open}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" 
                x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl py-2 z-50 border border-gray-100 overflow-hidden">
                
                <div class="px-4 py-2 border-b border-gray-50 mb-1">
                    <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Account Actions</p>
                </div>

                <a href="{{ route('admin.accountsettings') }}" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-green-50 hover:text-[#00923F] transition-colors">
                    <svg class="w-4 h-4 mr-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Change Password
                </a>
                
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4 mr-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Log Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>