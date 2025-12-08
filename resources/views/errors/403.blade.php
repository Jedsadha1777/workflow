<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="mb-6">
            <svg class="mx-auto h-24 w-24 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Access Denied </h1>
        
        <p class="text-gray-600 mb-6 text-lg">
            You don't have permission to access this page.<br>
            Please log out and sign in with the correct role.
        </p>
        
        <div class="space-y-3">
            @php
                $user = auth()->user();
                $logoutUrl = null;

        
                if ($user && method_exists($user, 'isAdmin')) {
                    if ($user->isAdmin()) {
                        $logoutUrl = route('filament.admin.auth.logout');
                    } elseif ($user->isUser()) {
                        $logoutUrl = route('filament.app.auth.logout');
                    }
                }
            @endphp
            
            @if($logoutUrl)
                <form method="POST" action="{{ $logoutUrl }}" id="logoutForm" class="hidden">
                    @csrf
                </form>
                
                <button 
                    onclick="document.getElementById('logoutForm').submit()" 
                    class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 ease-in-out transform hover:scale-105"
                >
                    Log Out
                </button>
            @endif
            
            <a 
                href="{{ url()->previous() }}" 
                class="block w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg transition duration-200"
            >
                Go Back
            </a>
        </div>
        
        <div class="mt-6 text-sm text-gray-500">
            <p>Error Code: 403</p>
        </div>
    </div>
</body>
</html>