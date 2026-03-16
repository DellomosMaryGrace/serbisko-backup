<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifying Document...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Google Sans', sans-serif; }
        .bg-custom-gradient { background: linear-gradient(180deg, #FFFFFF 0%, #E8F5E9 40%, #1b5e20 100%); }
        @keyframes loadingBar { 0% { width: 0%; } 100% { width: 100%; } }
        .animate-loading-bar { animation: loadingBar 10s ease-in-out forwards; }
    </style>
</head>
<body class="bg-custom-gradient min-h-screen flex flex-col items-center justify-center p-4">

    <div id="loading-card" class="bg-white p-10 rounded-3xl shadow-2xl text-center max-w-lg w-full transform transition-all duration-500 scale-100 opacity-100 absolute">
        <h2 class="text-3xl font-bold text-blue-900 mb-2">Analyzing Document...</h2>
        <p class="text-gray-600 mb-8 font-medium">Please wait while the AI checks your records.</p>
        <div class="flex justify-center mb-8">
            <svg class="animate-spin h-16 w-16 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </div>
        <p class="text-sm text-gray-400">This usually takes about 10 to 20 seconds...</p>
    </div>

    <div id="admin-review-card" class="bg-white p-10 rounded-3xl shadow-2xl text-center max-w-lg w-full hidden absolute transform transition-all duration-500 scale-95 opacity-0 border-4 border-yellow-400">
        <div class="flex justify-center mb-6">
            <div class="h-20 w-20 bg-yellow-100 rounded-full flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-600 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
        </div>
        <h2 class="text-3xl font-bold text-yellow-700 mb-2">Manual Verification Required</h2>
        <p class="text-gray-600 mb-4 font-medium">The AI couldn't verify this document automatically. An Administrator is reviewing it now.</p>
        <p class="text-sm text-gray-400 font-bold animate-pulse">Please do not leave the kiosk...</p>
    </div>

    <div id="success-card" class="bg-white p-10 rounded-3xl shadow-2xl text-center max-w-lg w-full hidden absolute transform transition-all duration-500 scale-95 opacity-0">
        <div class="flex justify-center mb-6">
            <div class="h-20 w-20 bg-green-100 rounded-full flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </div>
        </div>
        <h2 id="success-title" class="text-3xl font-bold text-green-700 mb-2">Document Verified!</h2>
    </div>

    <div id="storing-card" class="bg-white p-10 rounded-3xl shadow-2xl text-center max-w-lg w-full hidden absolute transform transition-all duration-500 scale-95 opacity-0">
        <div class="w-full bg-gray-200 rounded-full h-3 mb-4 overflow-hidden mt-10">
            <div id="progress-bar" class="bg-blue-600 h-3 rounded-full w-0"></div>
        </div>
        <p id="storing-subtext" class="text-sm text-gray-500 font-bold">Please do not close this page...</p>
    </div>

    <div id="error-card" class="bg-white p-10 rounded-3xl shadow-2xl text-center max-w-lg w-full hidden absolute transform transition-all duration-500 scale-95 opacity-0">
        <h2 class="text-3xl font-bold text-red-700 mb-2">Scanning Incomplete</h2>
        <p id="attempt-info" class="text-blue-900 font-bold text-sm mb-4"></p>
        <p id="error-message" class="text-gray-600 mb-8 font-medium">Please reposition the document.</p>
        <button onclick="window.location.href='/student/capture'" class="bg-blue-900 text-white font-bold py-3 px-8 rounded-full shadow-md hover:bg-blue-800 w-full">TRY AGAIN</button>
    </div>

    <div id="halted-card" class="bg-white p-10 rounded-3xl shadow-2xl text-center max-w-lg w-full hidden absolute transform transition-all duration-500 scale-95 opacity-0 border-4 border-red-600">
        <div class="flex justify-center mb-6">
            <div class="h-20 w-20 bg-red-100 rounded-full flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
        </div>
        <h2 class="text-3xl font-bold text-red-700 mb-2">Not Verified</h2>
        <p class="text-gray-700 mb-6 font-medium text-lg">Your document was declined by the administrator. Please retrieve your document from the scanner.</p>
        <p class="text-red-600 font-bold bg-red-50 p-3 rounded-lg border border-red-200">Enrollment flow halted. Redirecting to login...</p>
    </div>

    <script>
        let pollInterval;

        function checkStatus() {
            fetch('/student/check-scan-status')
                .then(response => response.json())
                .then(data => {
                    // Success conditions (AI or Manual Admin)
                    if (data.status === 'verified_lis' || data.status === 'verified' || data.status === 'manual_approved') {
                        clearInterval(pollInterval);
                        document.getElementById('success-title').innerText = data.current_doc + " Verified!";
                        document.getElementById('storing-subtext').innerText = data.next_url.includes('thankyou') ? "All documents complete! Finishing up..." : "Preparing next document scan...";

                        showCard('success-card');

                        // Start rejection polling during the 10s wait
                        const rejectInterval = setInterval(() => {
                            fetch('/student/check-rejection')
                                .then(r => r.json())
                                .then(rejData => {
                                    if (rejData.rejected) {
                                        console.log("PAPER_REJECTED recorded in background.");
                                        clearInterval(rejectInterval); // Stop once recorded
                                    }
                                });
                        }, 2000);

                        setTimeout(() => {
                            showCard('storing-card');
                            document.getElementById('progress-bar').classList.add('animate-loading-bar');
                            setTimeout(() => { 
                                clearInterval(rejectInterval);
                                window.location.href = data.next_url; 
                            }, 10000); 
                        }, 2000); 
                    } 
                    // Waiting for Admin
                    else if (data.status === 'manual_verification') {
                        showCard('admin-review-card'); // Stays active, keeps polling!
                    } 
                    // Declined by Admin (Halt flow)
                    else if (data.status === 'manual_declined') {
                        clearInterval(pollInterval);
                        showCard('halted-card');
                        setTimeout(() => { window.location.href = '/logout'; }, 7000); // 7 seconds to read the message, then kick out
                    }
                    // Standard AI Failure
                    else if (data.status === 'failed_lis' || data.status === 'failed') {
                        clearInterval(pollInterval);
                        showCard('error-card');
                        if(data.remarks) document.getElementById('error-message').innerText = data.remarks;
                        if(data.attempts) document.getElementById('attempt-info').innerText = "Attempt " + data.attempts + " of 3";
                    }
                })
                .catch(err => console.error("Error checking status:", err));
        }

        function showCard(cardId) {
            const cards = ['loading-card', 'admin-review-card', 'success-card', 'storing-card', 'error-card', 'halted-card'];
            cards.forEach(id => {
                const el = document.getElementById(id);
                if (!el.classList.contains('hidden') && id !== cardId) {
                    el.classList.remove('scale-100', 'opacity-100');
                    el.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => el.classList.add('hidden'), 500);
                }
            });
            setTimeout(() => {
                const target = document.getElementById(cardId);
                target.classList.remove('hidden');
                requestAnimationFrame(() => {
                    target.classList.remove('scale-95', 'opacity-0');
                    target.classList.add('scale-100', 'opacity-100');
                });
            }, 500); 
        }

        pollInterval = setInterval(checkStatus, 2000);
    </script>
</body>
</html>