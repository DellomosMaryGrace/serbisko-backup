<!DOCTYPE html>
<html lang="en">
  
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'sans-serif'],
            },
          },
        },
      }
    </script>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
    
    
</head>

<body class="bg-white flex h-screen overflow-hidden font-sans">

    @include('includes.sidebar')

    <div class="flex-1 flex flex-col min-w-0 bg-white shadow-2xl overflow-hidden">
        
        @include('includes.header')

        <main class="px-16 flex-1 overflow-y-auto bg-white">
              <div class="max-w-[1600px] mx-auto">
                  @yield('content')
              </div>
        </main>
    </div>
</body>

</html>