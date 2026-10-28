@php
    $selectorId = 'favourite-selector-'.$usage;
    $fieldMap = $fieldMap ?? [];
@endphp

<section id="{{ $selectorId }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4" data-favourite-selector>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="font-bold text-amber-950">⭐ {{ $title }}</h2>
            <p class="mt-1 text-xs text-amber-900">Favourite hanya mengisi maklumat identiti/hubungan. Semak dan ubah nilai transaksi semasa sebelum menyimpan.</p>
        </div>
        <button type="button" data-clear class="hidden min-h-11 rounded-md border border-amber-400 bg-white px-3 text-sm font-bold text-amber-900">Kosongkan</button>
    </div>

    <input type="hidden" name="favourite_contact_id" value="{{ old('favourite_contact_id') }}" data-selected-id>
    <div class="relative mt-3">
        <label for="{{ $selectorId }}-search" class="sr-only">Cari Favourite</label>
        <input id="{{ $selectorId }}-search" type="search" autocomplete="off" placeholder="Cari nama, telefon atau e-mel..." class="min-h-11 w-full rounded-md border-amber-300 bg-white focus:border-emerald-500 focus:ring-emerald-500" data-search>
        <div data-results class="mt-2 grid max-h-64 gap-2 overflow-y-auto" aria-live="polite"></div>
    </div>
    <p data-message class="mt-3 hidden rounded-md bg-emerald-100 p-3 text-sm font-semibold text-emerald-900"></p>
</section>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-favourite-selector]').forEach((root) => {
                    const usage = root.id.replace('favourite-selector-', '');
                    const maps = @json([$usage => $fieldMap]);
                    const searchUrl = @json(route('admin.favourite-contacts.search'));
                    const prefillTemplate = @json(route('admin.favourite-contacts.prefill', ['favouriteContact' => '__CONTACT__']));
                    const search = root.querySelector('[data-search]');
                    const results = root.querySelector('[data-results]');
                    const selectedId = root.querySelector('[data-selected-id]');
                    const clearButton = root.querySelector('[data-clear]');
                    const message = root.querySelector('[data-message]');
                    let timer;

                    const setMessage = (text, error = false) => {
                        message.textContent = text;
                        message.classList.remove('hidden', 'bg-emerald-100', 'text-emerald-900', 'bg-red-100', 'text-red-900');
                        message.classList.add(error ? 'bg-red-100' : 'bg-emerald-100', error ? 'text-red-900' : 'text-emerald-900');
                    };

                    const loadResults = async () => {
                        results.textContent = 'Memuatkan Favourite...';
                        const url = new URL(searchUrl, window.location.origin);
                        url.searchParams.set('usage', usage);
                        if (search.value.trim()) url.searchParams.set('q', search.value.trim());

                        try {
                            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                            if (!response.ok) throw new Error('Carian Favourite tidak tersedia.');
                            const payload = await response.json();
                            results.textContent = '';
                            if (!payload.data.length) {
                                results.textContent = 'Tiada Favourite aktif ditemui.';
                                return;
                            }
                            payload.data.forEach((contact) => {
                                const button = document.createElement('button');
                                button.type = 'button';
                                button.className = 'min-h-11 rounded-md border border-amber-200 bg-white px-3 py-2 text-left hover:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500';
                                const name = document.createElement('span');
                                name.className = 'block font-bold text-gray-900';
                                name.textContent = contact.name;
                                const details = document.createElement('span');
                                details.className = 'block text-xs text-gray-600';
                                details.textContent = [contact.phone, contact.email, contact.identity_masked].filter(Boolean).join(' · ');
                                button.append(name, details);
                                button.addEventListener('click', () => selectContact(contact));
                                results.appendChild(button);
                            });
                        } catch (error) {
                            results.textContent = '';
                            setMessage(error.message, true);
                        }
                    };

                    const selectContact = async (contact) => {
                        const url = new URL(prefillTemplate.replace('__CONTACT__', contact.id), window.location.origin);
                        url.searchParams.set('usage', usage);
                        try {
                            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                            if (!response.ok) throw new Error('Maklumat Favourite tidak dapat dimuatkan.');
                            const payload = await response.json();
                            Object.entries(maps[usage] || {}).forEach(([source, target]) => {
                                const input = document.getElementById(target);
                                if (input) input.value = payload.data[source] ?? '';
                            });
                            selectedId.value = contact.id;
                            search.value = contact.name;
                            results.textContent = '';
                            clearButton.classList.remove('hidden');
                            setMessage(`Maklumat ${contact.name} telah dimasukkan. Nilai transaksi masih boleh diubah.`);
                        } catch (error) {
                            setMessage(error.message, true);
                        }
                    };

                    clearButton.addEventListener('click', () => {
                        Object.values(maps[usage] || {}).forEach((target) => {
                            const input = document.getElementById(target);
                            if (input) input.value = '';
                        });
                        selectedId.value = '';
                        search.value = '';
                        message.classList.add('hidden');
                        clearButton.classList.add('hidden');
                        loadResults();
                    });
                    search.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(loadResults, 250);
                    });
                    search.addEventListener('focus', loadResults);
                });
            });
        </script>
    @endpush
@endonce
