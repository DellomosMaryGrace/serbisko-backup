<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SerbIsko - Welcome</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Google Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        .custom-gradient { background: linear-gradient(90deg, #1b5e20 0%, #2e7d32 40%, #f3f4f6 100%); }
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent; bottom: 0; color: transparent; cursor: pointer;
            height: auto; left: 0; position: absolute; right: 0; top: 0; width: auto;
        }
    </style>
</head>
<body class="custom-gradient min-h-screen flex items-center justify-center p-8 md:p-16 relative overflow-hidden">

    <div class="absolute left-[-10%] top-[-10%] w-[60vh] h-[60vh] opacity-10 pointer-events-none">
        <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="w-full h-full fill-white">
            <circle cx="100" cy="100" r="90" />
        </svg>
    </div>

    <div class="w-full max-w-7xl grid grid-cols-1 lg:grid-cols-2 gap-12 items-center z-10">
        
        <div class="text-white space-y-2">
            <p class="text-lg md:text-xl font-medium opacity-90">Ready to join TNCHS Senior High?</p>
            <h1 class="text-5xl md:text-7xl font-bold tracking-tight">Welcome to SerbIsko</h1>
            <p class="text-2xl md:text-3xl font-light opacity-90">— your enrollment buddy!</p>
            <p class="pt-8 text-sm md:text-base opacity-80 max-w-md leading-relaxed">
                Submit your documents here to complete your enrollment. <br>It's quick, easy, and secure!
            </p>
        </div>

        <div class="w-full max-w-xl ml-auto">
            <h2 class="text-4xl font-bold text-blue-900 mb-8">Sign in to get started</h2>

            @if($errors->has('message'))
                <div class="bg-red-100 border-l-4 border-red-700 text-red-700 p-4 mb-6 rounded-r-xl shadow-sm">
                    <p class="font-bold text-sm">Access Denied / Session Expired</p>
                    <p class="text-xs">{{ $errors->first('message') }}</p>
                </div>
            @endif

            <form action="{{ url('/login') }}" method="POST" class="space-y-5"
                x-data="{ 
                    loading: false,
                    errors: {
                        last_name: {{ $errors->has('last_name') ? 'true' : 'false' }},
                        given_name: {{ $errors->has('given_name') ? 'true' : 'false' }},
                        middle_name: {{ $errors->has('middle_name') ? 'true' : 'false' }},
                        dob: {{ $errors->has('dob') ? 'true' : 'false' }},
                        password: {{ $errors->has('password') ? 'true' : 'false' }}
                    } 
                }"
                @submit="loading = true">
                
                @csrf

                <div class="grid grid-cols-3 gap-3">
                    <div class="flex flex-col">
                        <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Last Name</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" 
                            @input="errors.last_name = false"
                            :class="errors.last_name ? 'border-red-700' : 'border-green-700/30'"
                            class="w-full px-4 py-3 rounded-xl border-2 transition-all bg-white/50 focus:bg-white outline-none">
                        <template x-if="errors.last_name">
                            <span class="text-red-700 italic text-[10px] mt-1 ml-1">{{ $errors->first('last_name') }}</span>
                        </template>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Given Name</label>
                        <input type="text" name="given_name" value="{{ old('given_name') }}"
                            @input="errors.given_name = false"
                            :class="errors.given_name ? 'border-red-700' : 'border-green-700/30'"
                            class="w-full px-4 py-3 rounded-xl border-2 transition-all bg-white/50 focus:bg-white outline-none">
                        <template x-if="errors.given_name">
                            <span class="text-red-700 italic text-[10px] mt-1 ml-1">{{ $errors->first('given_name') }}</span>
                        </template>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-xs font-bold text-gray-700 mb-1 ml-1">
                            Middle Name <span class="text-gray-700 font-normal italic lowercase">(if applicable)</span>
                        </label>

                        <input type="text" name="middle_name" value="{{ old('middle_name') }}" 
                            placeholder="Middle Name" 
                            @input="errors.middle_name = false"
                            :class="errors.middle_name ? 'border-red-700' : 'border-green-700/30'"
                            class="w-full px-4 py-3 rounded-xl border-2 transition-all bg-white/50 focus:bg-white outline-none">

                        <template x-if="errors.middle_name">
                            <span class="text-red-700 italic text-[10px] mt-1 ml-1">
                                {{ $errors->first('middle_name') }}
                            </span>
                        </template>
                    </div>
                </div>

                <div class="flex flex-col">
                    <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Date of Birth</label>
                    <div class="relative">
                        <input type="date" name="dob" value="{{ old('dob') }}"
                            @change="errors.dob = false"
                            :class="errors.dob ? 'border-red-700' : 'border-green-700/30'"
                            class="w-full px-4 py-3 rounded-xl border-2 transition-all bg-white/50 focus:bg-white outline-none text-gray-600 appearance-none">
                        <div class="absolute right-4 top-1/2 transform -translate-y-1/2 pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <template x-if="errors.dob">
                        <span class="text-red-700 italic text-[10px] mt-1 ml-1">{{ $errors->first('dob') }}</span>
                    </template>
                </div>

                <div class="flex flex-col" x-data="{ show: false }">
                    <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Password</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="password" 
                            @input="errors.password = false"
                            :class="errors.password ? 'border-red-700' : 'border-green-700/30'"
                            class="w-full px-4 py-3 rounded-xl border-2 transition-all bg-white/50 focus:bg-white outline-none pr-12">
                        
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-blue-900">
                            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 014.132-5.403m5.417-1.071A10.05 10.05 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.403m-5.417 1.071L17.25 17.25M3.75 3.75l16.5 16.5" /></svg>
                        </button>
                    </div>
                    <template x-if="errors.password">
                        <span class="text-red-700 italic text-[10px] mt-1 ml-1">{{ $errors->first('password') }}</span>
                    </template>
                </div>

                <div class="pt-4">
                    <button type="submit" 
                            :disabled="loading"
                            class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold text-lg py-4 rounded-full shadow-lg transform transition flex items-center justify-center space-x-2 disabled:opacity-70 disabled:cursor-not-allowed"
                            :class="loading ? 'hover:translate-y-0' : 'hover:-translate-y-0.5'">
                        
                        <svg x-show="loading" x-cloak class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>

                        <span x-text="loading ? 'Signing in...' : 'Sign In'"></span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</body>
</html>