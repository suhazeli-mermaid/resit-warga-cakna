@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white', 'id' => 'dropdown-menu'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div
    class="relative"
    x-data="{ open: false }"
    @click.outside="open = false"
    @close.stop="open = false"
    @keydown.escape.window="if (open) { open = false; $nextTick(() => $refs.trigger.focus()); }"
>
    <div>
        {{ $trigger }}
    </div>

    <div
        id="{{ $id }}"
        x-ref="menu"
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-[100] mt-2 {{ $width }} max-w-[calc(100vw-2rem)] rounded-md shadow-lg {{ $alignmentClasses }}"
        role="menu"
        aria-orientation="vertical"
        @click="open = false"
        @keydown.arrow-down.prevent="const items = [...$el.querySelectorAll('[role=menuitem]')]; const index = items.indexOf(document.activeElement); items[(index + 1) % items.length].focus()"
        @keydown.arrow-up.prevent="const items = [...$el.querySelectorAll('[role=menuitem]')]; const index = items.indexOf(document.activeElement); items[(index - 1 + items.length) % items.length].focus()"
        @keydown.home.prevent="const item = $el.querySelector('[role=menuitem]'); if (item) item.focus()"
        @keydown.end.prevent="const items = $el.querySelectorAll('[role=menuitem]'); if (items.length) items[items.length - 1].focus()"
    >
        <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
