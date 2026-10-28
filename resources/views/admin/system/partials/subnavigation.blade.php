<nav aria-label="Navigasi Sistem" class="rounded-xl border border-gray-200 bg-white p-2 shadow-sm">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.updates.index') }}"
           class="min-h-11 rounded-lg px-4 py-2.5 text-sm font-bold {{ request()->routeIs('admin.updates.*') ? 'bg-emerald-700 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            Smart Update Manager
        </a>
        <a href="{{ route('admin.email-logs.index') }}"
           class="min-h-11 rounded-lg px-4 py-2.5 text-sm font-bold {{ request()->routeIs('admin.email-logs.*') ? 'bg-emerald-700 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            Log E-mel
        </a>
    </div>
</nav>
