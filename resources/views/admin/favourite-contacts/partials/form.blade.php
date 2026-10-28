@csrf
@if(isset($contact)) @method('PUT') @endif

<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-input-label for="name" value="Nama" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $contact->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="identity_document" value="No. KP / Pasport" />
        <x-text-input id="identity_document" name="identity_document" class="mt-1 block w-full" :value="old('identity_document', isset($contact) ? $contact->identityValue() : '')" />
        <p class="mt-1 text-xs text-gray-500">Disimpan terenkripsi untuk rekod baharu/yang dikemas kini.</p>
    </div>
    <div>
        <x-input-label for="phone" value="No. Telefon" />
        <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $contact->phone ?? '')" />
    </div>
    <div>
        <x-input-label for="email" value="E-mel" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $contact->email ?? '')" />
    </div>
    <div>
        <x-input-label for="bank_name" value="Nama Bank" />
        <x-text-input id="bank_name" name="bank_name" class="mt-1 block w-full" :value="old('bank_name', $contact->bank_name ?? '')" />
    </div>
    <div class="md:col-span-2">
        <x-input-label for="address" value="Alamat" />
        <textarea id="address" name="address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('address', $contact->address ?? '') }}</textarea>
    </div>
    <div>
        <x-input-label for="bank_account_no" value="No. Akaun Bank" />
        <x-text-input id="bank_account_no" name="bank_account_no" class="mt-1 block w-full" :value="old('bank_account_no', $contact->bank_account_no ?? '')" />
        <p class="mt-1 text-xs text-gray-500">Disimpan terenkripsi dan dimask dalam listing.</p>
    </div>
</div>

<fieldset class="mt-6 rounded-xl border border-gray-200 p-4">
    <legend class="px-2 font-bold">Kategori Favourite</legend>
    <div class="grid gap-3 sm:grid-cols-3">
        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_donor_favourite" value="1" @checked(old('is_donor_favourite', $contact->is_donor_favourite ?? true)) class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600"> Pembayar / Penderma</label>
        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_voucher_recipient_favourite" value="1" @checked(old('is_voucher_recipient_favourite', $contact->is_voucher_recipient_favourite ?? false)) class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600"> Penerima Baucar</label>
        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $contact->is_active ?? true)) class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600"> Aktif</label>
    </div>
    <x-input-error :messages="$errors->get('usage')" class="mt-2" />
</fieldset>

<div class="mt-6 flex flex-wrap gap-3">
    <x-primary-button>{{ isset($contact) ? 'Simpan Perubahan' : 'Cipta Favourite' }}</x-primary-button>
    <a href="{{ route('admin.favourite-contacts.index') }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-semibold text-gray-700">Kembali</a>
</div>
