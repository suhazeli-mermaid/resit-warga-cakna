@php
    $items = [
        ['label' => 'Dashboard Baucar', 'route' => 'vouchers.dashboard', 'active' => request()->routeIs('vouchers.dashboard')],
        ['label' => 'Senarai Baucar', 'route' => 'vouchers.index', 'active' => request()->routeIs('vouchers.index', 'vouchers.show', 'vouchers.create', 'vouchers.edit', 'vouchers.document', 'vouchers.pdf')],
        ['label' => 'Surat Akuan Penerimaan', 'route' => 'vouchers.acknowledgements.index', 'active' => request()->routeIs('vouchers.acknowledgements.*')],
        ['label' => 'Arkib', 'route' => 'vouchers.archive.index', 'active' => request()->routeIs('vouchers.archive.index')],
    ];
@endphp

<nav aria-label="Navigasi Baucar Bayaran" class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
    <div class="flex min-w-max gap-2 border-b border-gray-200 pb-2">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}" @if($item['active']) aria-current="page" @endif
               class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-bold {{ $item['active'] ? 'bg-emerald-800 text-white' : 'bg-white text-gray-700 shadow-sm hover:bg-emerald-50 hover:text-emerald-800' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
