<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SerbIsko - Welcome</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Google Sans', sans-serif;
        }
        /* Replicating the specific gradient from your image */
        .custom-gradient {
            background: linear-gradient(90deg, #1b5e20 0%, #2e7d32 40%, #f3f4f6 100%);
        }
        /* Custom date input icon positioning */
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
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
            <h1 class="text-5xl md:text-7xl font-bold tracking-tight">
                Welcome to SerbIsko
            </h1>
            <p class="text-2xl md:text-3xl font-light opacity-90">
                — your enrollment buddy!
            </p>
            
            <p class="pt-8 text-sm md:text-base opacity-80 max-w-md leading-relaxed">
                Submit your documents here to complete your enrollment.
                <br>It's quick, easy, and secure!
            </p>
        </div>

        <div class="w-full max-w-xl ml-auto">
            <h2 class="text-4xl font-bold text-blue-900 mb-8">Sign in to get started</h2>

            @if($errors->has('message'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-xl shadow-sm animate-pulse">
                    <p class="font-bold text-sm">Access Denied</p>
                    <p class="text-xs">{{ $errors->first('message') }}</p>
                </div>
            @endif

            <form action="{{ url('/login') }}" method="POST" class="space-y-5">
                @csrf

                <div class="grid grid-cols-3 gap-3">
                    <div class="flex flex-col">
                        <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Last Name</label>
                        <input type="text" name="last_name" placeholder="Last Name" 
                               class="w-full px-4 py-3 rounded-xl border-2 border-green-700/30 bg-white/50 focus:bg-white focus:ring-2 focus:ring-green-600 focus:border-transparent outline-none transition-all placeholder-gray-400">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Given Name</label>
                        <input type="text" name="given_name" placeholder="Given Name" 
                               class="w-full px-4 py-3 rounded-xl border-2 border-green-700/30 bg-white/50 focus:bg-white focus:ring-2 focus:ring-green-600 focus:border-transparent outline-none transition-all placeholder-gray-400">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Middle Name</label>
                        <input type="text" name="middle_name" placeholder="Middle Name" 
                               class="w-full px-4 py-3 rounded-xl border-2 border-green-700/30 bg-white/50 focus:bg-white focus:ring-2 focus:ring-green-600 focus:border-transparent outline-none transition-all placeholder-gray-400">
                    </div>
                </div>

                <div class="flex flex-col relative">
                    <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Date of Birth</label>
                    <div class="relative">
                        <input type="date" name="dob" 
                               class="w-full px-4 py-3 rounded-xl border-2 border-green-700/30 bg-white/50 focus:bg-white focus:ring-2 focus:ring-green-600 focus:border-transparent outline-none transition-all text-gray-600 placeholder-gray-400 appearance-none">
                        <div class="absolute right-4 top-1/2 transform -translate-y-1/2 pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col">
                    <label class="text-xs font-bold text-gray-700 mb-1 ml-1">Password</label>
                    <input type="password" name="password" placeholder="Your Password" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-green-700/30 bg-white/50 focus:bg-white focus:ring-2 focus:ring-green-600 focus:border-transparent outline-none transition-all placeholder-gray-400">
                </div>

                <div class="pt-4">
                    <button type="submit" 
                            class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold text-lg py-4 rounded-full shadow-lg transform transition hover:-translate-y-0.5 hover:shadow-xl">
                        Sign In
                    </button>
                </div>

            </form>
        </div>
    </div>

</body>
</html>