@php
    $receiptRoute = auth()->check() ? route('receipts.index') : route('login');
    $accountRoute = auth()->check() ? route('profile.edit') : route('login');
    $financeItem = auth()->check() && auth()->user()->hasVoucherRole()
        ? ['label' => 'Baucar', 'href' => route('vouchers.dashboard'), 'active' => request()->routeIs('vouchers.*'), 'icon' => 'fund']
        : ['label' => 'Senarai Dana', 'href' => route('public.index').'#senarai-dana', 'active' => false, 'icon' => 'fund'];
    $items = [
        ['label' => 'Home', 'href' => route('public.index'), 'active' => request()->routeIs('public.index'), 'icon' => 'home'],
        ['label' => 'Portal Awam', 'href' => route('public.index').'#program-aktif', 'active' => request()->routeIs('dana.*'), 'icon' => 'heart'],
        ['label' => 'Resit Saya', 'href' => $receiptRoute, 'active' => request()->routeIs('receipts.*'), 'icon' => 'receipt'],
        $financeItem,
        ['label' => 'Akaun Saya', 'href' => $accountRoute, 'active' => request()->routeIs('profile.*'), 'icon' => 'user'],
    ];
@endphp

<nav aria-label="Navigasi utama mudah alih" class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] backdrop-blur sm:hidden" style="padding-bottom: env(safe-area-inset-bottom);">
    <div class="mx-auto grid max-w-lg grid-cols-5">
        @foreach($items as $item)
            <a href="{{ $item['href'] }}" aria-label="{{ $item['label'] }}" @if($item['active']) aria-current="page" @endif
               class="flex min-h-16 min-w-0 flex-col items-center justify-center gap-1 px-1 py-2 text-[10px] font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600 {{ $item['active'] ? 'text-emerald-800' : 'text-gray-500 hover:text-emerald-700' }}">
                @switch($item['icon'])
                    @case('home')
                        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10M9 20v-6h6v6"/></svg>
                        @break
                    @case('heart')
                        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>
                        @break
                    @case('receipt')
                        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/></svg>
                        @break
                    @case('fund')
                        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16v13H4zM7 7V4h10v3"/><path d="M8 12h8M8 16h5"/></svg>
                        @break
                    @default
                        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                @endswitch
                <span class="truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
