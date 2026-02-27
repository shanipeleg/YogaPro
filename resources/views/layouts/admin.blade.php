<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — YogaPro</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

<nav class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-6 text-sm sticky top-0 z-10">
    <span class="font-bold text-gray-900 mr-2">YogaPro Admin</span>

    <a href="{{ route('admin.dashboard') }}"
       class="{{ request()->routeIs('admin.dashboard') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">
        Dashboard
    </a>
    <a href="{{ route('admin.queue') }}"
       class="{{ request()->routeIs('admin.queue') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">
        Queue
        @if(($navPendingJobs ?? 0) > 0 || ($navFailedJobs ?? 0) > 0)
            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium {{ ($navFailedJobs ?? 0) > 0 ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                {{ ($navFailedJobs ?? 0) > 0 ? ($navFailedJobs ?? 0).' failed' : ($navPendingJobs ?? 0) }}
            </span>
        @endif
    </a>
    <a href="{{ route('admin.videos') }}"
       class="{{ request()->routeIs('admin.videos*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">
        Videos
    </a>
    <a href="{{ route('admin.moves') }}"
       class="{{ request()->routeIs('admin.moves*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">
        Poses
    </a>
    <a href="{{ route('admin.logs') }}"
       class="{{ request()->routeIs('admin.logs*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">
        Logs
    </a>
    <a href="{{ route('admin.channel') }}"
       class="{{ request()->routeIs('admin.channel*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">
        Channel
    </a>
</nav>

<div class="max-w-7xl mx-auto px-4 py-6">
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif

    @yield('content')
</div>

<script>
// Spinner on artisan trigger buttons
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-trigger]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Running…';
                btn.classList.add('opacity-60');
            }
        });
    });
});
</script>
</body>
</html>
