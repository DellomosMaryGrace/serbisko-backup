<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex min-h-screen">

    <aside class="w-[300px] h-screen bg-[#F6FFFA] p-8 flex flex-col border-r border-green-100 shadow-sm sticky top-0">
        
        <div class="mb-10 px-2">
            <h1 class="text-[#003918] text-4xl font-[800] tracking-tight leading-tight">Serblsko</h1>
            <p class="text-[#003918] text-sm font-medium opacity-70 mt-1">
                Enrollment Management System
            </p>
        </div>

        <nav class="flex-1 overflow-y-auto">
            <ul class="space-y-1.5 list-none p-0">
                
                @php
                    function isActive($route) {
                        return request()->routeIs($route)
                            ? 'bg-[#00923F]/10 text-[#00923F] border-l-4 border-[#00923F]'
                            : 'text-[#003918] hover:bg-[#00923F]/5';
                    }
                @endphp

                <li>
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-10 px-4 py-3 text-[#003918] font-semibold rounded-xl hover:bg-[#00923F]/5 transition-colors group {{ isActive('admin.dashboard') }}">
                        <div class="w-6 flex justify-center shrink-0">
                            <svg class="w-5 h-5 fill-current opacity-80 group-hover:opacity-100" viewBox="0 0 16 16">
                                <path d="M6 1H2a1 1 0 0 0-1 1v5.6a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1m7.8 0H9.9a1 1 0 0 0-1 1v2.1a1 1 0 0 0 1 1h3.9a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1m-.1 6.8h-3.9a1 1 0 0 0-1 1v5.6a1 1 0 0 0 1 1h3.9a1 1 0 0 0 1-1V8.2a1 1 0 0 0-1-1m-7.8 2.8H2a1 1 0 0 0-1 1v2.1a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-2.1a1 1 0 0 0-1-1"/>
                            </svg>
                        </div>
                        <span>Dashboard</span>
                    </a>
                </li>

                <ul class="space-y-1.5 list-none p-0">
                    <li class="rounded-xl transition-colors group {{ request()->is('admin/students*') ? 'bg-[#00923F]/10 text-[#00923F] border-l-4 border-[#00923F]' : 'text-[#003918] hover:bg-[#00923F]/5' }}">
                        <a href="{{ route('admin.students') }}" class="flex items-center gap-10 px-4 py-3 font-semibold">
                            <div class="w-6 flex justify-center shrink-0">
                                <svg class="w-5 h-5 fill-current opacity-80 group-hover:opacity-100" viewBox="0 0 20 20">
                                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                </svg>
                            </div>
                            <span>Students</span>
                        </a>
                    </li>
                </ul>

                <li>
                    <a href="{{ route('admin.verification') }}" class="flex items-center gap-10 px-4 py-3 text-[#003918] font-semibold rounded-xl hover:bg-[#00923F]/5 transition-colors group {{ isActive('admin.verification') }}">
                        <div class="w-6 flex justify-center shrink-0">
                            <svg class="w-6 h-6 fill-current opacity-80 group-hover:opacity-100" viewBox="0 0 24 24">
                                <path d="M8 2a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V8.36a2 2 0 0 0-.46-1.28l-2.28-2.74l-1.55-1.69A2 2 0 0 0 15.23 2zm10.7 6l-1.95-2.34l-1.25-1.36V7a1 1 0 0 0 1 1z"/>
                            </svg>
                        </div>
                        <span>Verification</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.requirementhub') }}" class="flex items-center gap-10 px-4 py-3 text-[#003918] font-semibold rounded-xl hover:bg-[#00923F]/5 transition-colors group {{ isActive('admin.requirementhub') }}">
                        <div class="w-6 flex justify-center shrink-0">
                            <svg class="w-6 h-6 fill-current opacity-80 group-hover:opacity-100" viewBox="0 0 24 24">
                                <path d="M15.25 2h-6.5A6.76 6.76 0 0 0 2 8.75v6.5A6.75 6.75 0 0 0 8.75 22h6.5A6.75 6.75 0 0 0 22 15.25v-6.5A6.76 6.76 0 0 0 15.25 2M8.04 17.48a1.37 1.37 0 1 1 1.37-1.37a1.36 1.36 0 0 1-1.37 1.37m0-4.11A1.37 1.37 0 1 1 9.41 12a1.36 1.36 0 0 1-1.37 1.42zm0-4.11a1.37 1.37 0 1 1 1.37-1.37a1.36 1.36 0 0 1-1.37 1.37m8.15 7.6h-4.52a.75.75 0 1 1 0-1.5h4.52a.75.75 0 1 1 0 1.5m0-4.11h-4.52a.75.75 0 1 1 0-1.5h4.52a.75.75 0 1 1 0 1.5m0-4.11h-4.52a.75.75 0 0 1 0-1.5h4.52a.75.75 0 1 1 0 1.5" />
                            </svg>
                        </div>
                        <span class="leading-tight">Academic Hub</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.systemsync') }}" class="flex items-center gap-10 px-4 py-3 text-[#003918] font-semibold rounded-xl hover:bg-[#00923F]/5 transition-colors group {{ isActive('admin.systemsync') }}">
                        <div class="w-6 flex justify-center shrink-0">
                            <svg class="w-6 h-6 fill-current opacity-80 group-hover:opacity-100" viewBox="0 0 30 30">
                                <path d="M15.0127 5V1.25L10.0127 6.25L15.0127 11.25V7.5C19.1502 7.5 22.5127 10.8625 22.5127 15C22.5127 16.2625 22.2002 17.4625 21.6377 18.5L23.4627 20.325C24.4755 18.7336 25.0132 16.8863 25.0127 15C25.0127 9.475 20.5377 5 15.0127 5ZM15.0127 22.5C10.8752 22.5 7.5127 19.1375 7.5127 15C7.5127 13.7375 7.8252 12.5375 8.3877 11.5L6.5627 9.675C5.54989 11.2664 5.01217 13.1137 5.0127 15C5.0127 20.525 9.48769 25 15.0127 25V28.75L20.0127 23.75L15.0127 18.75V22.5Z" />
                            </svg>
                        </div>
                        <span class="leading-tight">System Sync</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="pt-6 border-t border-green-300">
            <a href="{{ route('admin.accessmanagement') }}" class="flex items-center gap-10 px-4 py-3 text-[#003918] font-semibold rounded-xl hover:bg-[#00923F]/5 transition-colors group {{ isActive('admin.accessmanagement') }}">
                <div class="w-6 flex justify-center shrink-0">
                    <svg class="w-6 h-6 fill-current opacity-80" viewBox="0 0 24 24">
                        <path d="M19.6 3C18 3 13.1 1 12 1s-6 2-7.6 2c-1.4 0-2.4 1.2-2.4 2.6V12c0 5.6 4.7 9.8 8.6 11.7.9.4 1.9.4 2.8 0 3.9-1.9 8.6-6.1 8.6-11.7V5.6C22 4.2 21 3 19.6 3zm-7.6 16.5c-2.3 0-4.1-1.8-4.1-4.1s1.8-4.1 4.1-4.1s4.1 1.8 4.1 4.1s-1.8 4.1-4.1 4.1zm0-10.2c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3z"/>
                    </svg>
                </div>
                <span>Access Management</span>
            </a>
            <a href="{{ route('admin.accountsettings') }}" class="flex items-center gap-10 px-4 py-3 text-[#003918] font-semibold rounded-xl hover:bg-[#00923F]/5 transition-colors group {{ isActive('admin.accountsettings') }}">
                <div class="w-6 flex justify-center shrink-0">
                    <svg class="w-6 h-6 fill-current opacity-80" viewBox="0 0 24 24">
                        <path d="m9.25 22l-.4-3.2q-.32-.12-.61-.3t-.56-.37L4.7 19.37l-2.75-4.75l2.57-1.95q0-.16 0-.33v-.67q0-.16.03-.34L1.95 9.37l2.75-4.75l2.97 1.25q.28-.2.58-.37t.6-.3l.4-3.2h5.5l.4 3.2q.33.12.61.3t.56.37l2.98-1.25l2.75 4.75l-2.58 1.95q.03.17.03.34v.67q0 .16-.05.34l2.58 1.95l-2.75 4.75l-2.95-1.25q-.28.2-.58.37t-.6.3l-.4 3.2zm2.8-6.5q1.45 0 2.47-1.02t1.03-2.48t-1.03-2.47t-2.47-1.03q-1.48 0-2.49 1.03t-1.01 2.47t1.01 2.48t2.49 1.02"/>
                    </svg>
                </div>
                <span>Account Settings</span>
            </a>
            <a href="/logout" class="flex items-center gap-10 px-4 py-3 text-[#003918] font-semibold rounded-xl hover:bg-red-50 hover:text-red-700 transition-colors group">
                <div class="w-6 flex justify-center shrink-0">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 14 14">
                        <path fill-rule="evenodd" d="M2.23 1.358a.4.4 0 0 1 .27-.109h7c.103 0 .2.04.27.109a.35.35 0 0 1 .105.247v.962a.625.625 0 1 0 1.25 0v-.962c0-.43-.174-.84-.48-1.14A1.64 1.64 0 0 0 9.5 0h-7c-.427 0-.84.167-1.145.466s-.48.71-.48 1.14v10.79c0 .43.174.839.48 1.14c.3.3.72.46 1.14.46h7c.43 0 .84-.16 1.14-.46s.48-.71.48-1.14v-.96a.625.625 0 1 0-1.25 0v.96c0 .09-.04.18-.11.25a.4.4 0 0 1-.27.11h-7a.4.4 0 0 1-.27-.11a.35.35 0 0 1-.1-.25V1.6c0-.09.04-.18.11-.25m8.03 3.06l-.38.58v1.38H5.5a.625.625 0 1 0 0 1.25h4.38V9a.625.625 0 0 0 1.07.44l2-2a.625.625 0 0 0 0-.88l-2-2a.625.625 0 0 0-.68-.13" clip-rule="evenodd"/>
                    </svg>
                </div>
                <span>Logout</span>
            </a>
        </div>
    </aside>

</body>
</html>