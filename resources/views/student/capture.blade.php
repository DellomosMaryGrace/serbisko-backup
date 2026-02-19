<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SerbIsko - Capture Document</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Google Sans', sans-serif; }
        .bg-custom-gradient {
            background: linear-gradient(180deg, #FFFFFF 0%, #E8F5E9 40%, #1b5e20 100%);
        }
    </style>
</head>
<body class="bg-custom-gradient min-h-screen flex flex-col items-center justify-center p-4">

    <div class="absolute top-4 left-4 flex flex-col gap-2 z-50">
        
        <div id="status-ocr" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-full text-xs font-bold flex items-center gap-2 shadow-sm border border-gray-200 transition-colors duration-300">
            <span class="indicator-dot w-2 h-2 bg-gray-400 rounded-full"></span>
            <span class="indicator-text">OCR Engine: Checking...</span>
        </div>

        <div id="status-lis" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-full text-xs font-bold flex items-center gap-2 shadow-sm border border-gray-200 transition-colors duration-300">
            <span class="indicator-dot w-2 h-2 bg-gray-400 rounded-full"></span>
            <span class="indicator-text">LIS Verifier: Checking...</span>
        </div>

        <div id="status-arduino" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-full text-xs font-bold flex items-center gap-2 shadow-sm border border-gray-200 transition-colors duration-300">
            <span class="indicator-dot w-2 h-2 bg-gray-400 rounded-full"></span>
            <span class="indicator-text">Arduino Link: Checking...</span>
        </div>

    </div>

    <div class="text-center mb-8 max-w-2xl mt-16 lg:mt-0">
        <h1 class="text-3xl md:text-4xl font-bold text-green-900 mb-2">
            Capture your <span class="text-blue-900">{{ session('current_doc', 'Report Card') }}</span>
        </h1>
        <p class="text-gray-600 text-sm md:text-base">
            We will analyze the document. If applicable, we will verify details with DepEd LIS.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 w-full max-w-6xl items-center">

        <div class="bg-white p-8 rounded-3xl shadow-lg border border-gray-100 h-full flex flex-col justify-center">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Instructions:</h3>
            
            <ul class="space-y-6">
                <li class="flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-green-900 text-white flex items-center justify-center font-bold flex-shrink-0">1</div>
                    <p class="text-gray-600 mt-1">Position your document within the camera view.</p>
                </li>
                <li class="flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-green-900 text-white flex items-center justify-center font-bold flex-shrink-0">2</div>
                    <p class="text-gray-600 mt-1">Ensure adequate lighting and hold steady.</p>
                </li>
                <li class="flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-green-900 text-white flex items-center justify-center font-bold flex-shrink-0">3</div>
                    <p class="text-gray-600 mt-1">Click "CAPTURE" when ready.</p>
                </li>
            </ul>
        </div>

        <div class="relative">
            <div class="bg-black rounded-3xl overflow-hidden shadow-2xl border-4 border-blue-900 aspect-video relative group">
                
                <video id="camera-stream" autoplay playsinline class="w-full h-full object-cover transform scale-x-[-1]"></video>
                <canvas id="capture-canvas" class="hidden w-full h-full object-cover"></canvas>

                <div id="camera-message" class="absolute inset-0 flex items-center justify-center text-white text-center p-4 bg-black/50">
                    <p>Requesting camera access...</p>
                </div>
            </div>

            <div class="mt-8 flex justify-center">
                <form id="uploadForm" action="{{ url('/student/save-image') }}" method="POST">
                    @csrf
                    <input type="hidden" name="image_data" id="image-data">
                    <input type="hidden" name="document_type" value="{{ session('current_doc', 'Report Card') }}">

                    <button type="button" id="capture-btn" class="bg-blue-900 text-white text-lg font-bold py-4 px-16 rounded-full shadow-lg hover:bg-blue-800 transition transform hover:scale-105 tracking-wide">
                        CAPTURE
                    </button>
                    
                    <div id="action-buttons" class="hidden gap-4">
                        <button type="button" id="retake-btn" class="bg-gray-200 text-gray-800 font-bold py-3 px-8 rounded-full hover:bg-gray-300">
                            RETAKE
                        </button>
                        <button type="submit" class="bg-green-700 text-white font-bold py-3 px-8 rounded-full hover:bg-green-800 shadow-lg">
                            SUBMIT PHOTO
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ==========================================
        // 1. LIVE SERVER PINGING LOGIC
        // ==========================================
        
        // Define the ports for your python services
        // NOTE: If you changed OCR to port 9001, update it here!
        const SERVERS = {
            ocr: { url: 'http://localhost:5000/ocr', method: 'POST', label: 'OCR Engine' },
            lis: { url: 'http://localhost:5001/health', method: 'GET', label: 'LIS Verifier' },
            arduino: { url: 'http://localhost:8080/api/status', method: 'GET', label: 'Arduino Link' }
        };

        function updateBadge(id, isOnline, label) {
            const badge = document.getElementById(`status-${id}`);
            const dot = badge.querySelector('.indicator-dot');
            const text = badge.querySelector('.indicator-text');

            text.textContent = `${label}: ${isOnline ? 'Online' : 'Offline'}`;

            if (isOnline) {
                // Green Online Styling
                badge.className = "bg-green-100 text-green-700 border-green-200 px-4 py-2 rounded-full text-xs font-bold flex items-center gap-2 shadow-sm border transition-colors duration-300";
                dot.className = "indicator-dot w-2 h-2 bg-green-500 rounded-full animate-pulse";
            } else {
                // Red Offline Styling
                badge.className = "bg-red-100 text-red-700 border-red-200 px-4 py-2 rounded-full text-xs font-bold flex items-center gap-2 shadow-sm border transition-colors duration-300";
                dot.className = "indicator-dot w-2 h-2 bg-red-600 rounded-full";
            }
        }

        async function pingServer(id, config) {
            try {
                // Set a 2-second timeout so it doesn't hang forever if the server is off
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 2000);
                
                const response = await fetch(config.url, { 
                    method: config.method, 
                    signal: controller.signal 
                });
                
                clearTimeout(timeoutId);

                // Even if OCR returns 400 (Bad Request because we sent no image), it means the server is ALIVE
                if (response.status === 200 || response.status === 400 || response.status === 405) {
                    updateBadge(id, true, config.label);
                } else {
                    updateBadge(id, false, config.label);
                }
            } catch (error) {
                // Network error means server is completely offline
                updateBadge(id, false, config.label);
            }
        }

        function checkAllServers() {
            pingServer('ocr', SERVERS.ocr);
            pingServer('lis', SERVERS.lis);
            pingServer('arduino', SERVERS.arduino);
        }

        // Check immediately, then every 3 seconds
        checkAllServers();
        setInterval(checkAllServers, 3000);


        // ==========================================
        // 2. CAMERA CAPTURE LOGIC
        // ==========================================
        const video = document.getElementById('camera-stream');
        const canvas = document.getElementById('capture-canvas');
        const message = document.getElementById('camera-message');
        const captureBtn = document.getElementById('capture-btn');
        const actionButtons = document.getElementById('action-buttons');
        const retakeBtn = document.getElementById('retake-btn');
        const imageDataInput = document.getElementById('image-data');

        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                message.classList.add('hidden');
            } catch (err) {
                message.textContent = "⚠️ Camera access denied or not available.";
                message.classList.remove('hidden');
            }
        }

        startCamera();

        captureBtn.addEventListener('click', () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            const dataURL = canvas.toDataURL('image/jpeg');
            imageDataInput.value = dataURL;

            video.classList.add('hidden');
            canvas.classList.remove('hidden');
            captureBtn.classList.add('hidden');
            actionButtons.classList.remove('hidden');
            actionButtons.classList.add('flex');
        });

        retakeBtn.addEventListener('click', () => {
            video.classList.remove('hidden');
            canvas.classList.add('hidden');
            captureBtn.classList.remove('hidden');
            actionButtons.classList.add('hidden');
            actionButtons.classList.remove('flex');
        });
    </script>
</body>
</html>