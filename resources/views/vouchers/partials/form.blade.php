@csrf
@if(isset($voucher)) @method('PUT') @endif
@unless(isset($voucher))
    @include('favourite-contacts.selector', [
        'usage' => 'voucher',
        'title' => 'Favourite Penerima',
        'fieldMap' => [
            'name' => 'recipient_name',
            'identity_document' => 'recipient_ic',
            'phone' => 'recipient_phone',
            'email' => 'recipient_email',
            'address' => 'recipient_address',
            'bank_name' => 'bank_name',
            'bank_account_no' => 'bank_account_no',
        ],
    ])
    <div class="h-5"></div>
@endunless
<div class="grid gap-5 md:grid-cols-2">
    <div>
        <x-input-label for="voucher_date" value="Tarikh Baucar" />
        <x-text-input id="voucher_date" name="voucher_date" type="date" class="mt-1 block w-full" :value="old('voucher_date', isset($voucher) ? $voucher->voucher_date->format('Y-m-d') : today()->format('Y-m-d'))" required />
        <x-input-error :messages="$errors->get('voucher_date')" class="mt-2" />
    </div>
    @if(isset($applicants))
        <div>
            <x-input-label for="applicant_id" value="AJK Pemohon" />
            <select id="applicant_id" name="applicant_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @foreach($applicants as $applicant)
                    <option value="{{ $applicant->id }}" @selected((int) old('applicant_id', auth()->id()) === $applicant->id)>{{ $applicant->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <x-input-label for="recipient_name" value="Nama Penerima" />
        <x-text-input id="recipient_name" name="recipient_name" class="mt-1 block w-full" :value="old('recipient_name', $voucher->recipient_name ?? '')" required />
        <x-input-error :messages="$errors->get('recipient_name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="recipient_phone" value="No. Telefon" />
        <x-text-input id="recipient_phone" name="recipient_phone" class="mt-1 block w-full" :value="old('recipient_phone', $voucher->recipient_phone ?? '')" required />
        <x-input-error :messages="$errors->get('recipient_phone')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="recipient_email" value="E-mel" />
        <x-text-input id="recipient_email" name="recipient_email" type="email" class="mt-1 block w-full" :value="old('recipient_email', $voucher->recipient_email ?? '')" required />
        <x-input-error :messages="$errors->get('recipient_email')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="recipient_ic" value="No. Kad Pengenalan (pilihan, terenkripsi)" />
        <x-text-input id="recipient_ic" name="recipient_ic" class="mt-1 block w-full" :value="old('recipient_ic', $voucher->recipient_ic ?? '')" />
    </div>
    <div class="md:col-span-2">
        <x-input-label for="recipient_address" value="Alamat (pilihan)" />
        <textarea id="recipient_address" name="recipient_address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('recipient_address', $voucher->recipient_address ?? '') }}</textarea>
    </div>
    <div>
        <x-input-label for="bank_name" value="Nama Bank (pilihan)" />
        <x-text-input id="bank_name" name="bank_name" class="mt-1 block w-full" :value="old('bank_name', $voucher->bank_name ?? '')" />
    </div>
    <div>
        <x-input-label for="bank_account_no" value="No. Akaun Bank (pilihan, terenkripsi)" />
        <x-text-input id="bank_account_no" name="bank_account_no" class="mt-1 block w-full" :value="old('bank_account_no', $voucher->bank_account_no ?? '')" />
    </div>
    <div class="md:col-span-2">
        <x-input-label for="purpose" value="Tujuan Baucar Bayaran" />
        <textarea id="purpose" name="purpose" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>{{ old('purpose', $voucher->purpose ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('purpose')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="amount" value="Jumlah Bayaran (RM)" />
        <x-text-input id="amount" name="amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" :value="old('amount', $voucher->amount ?? '')" required />
        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="committee_approval_date" value="Tarikh Kelulusan AJK (jika ada)" />
        <x-text-input id="committee_approval_date" name="committee_approval_date" type="date" class="mt-1 block w-full" :value="old('committee_approval_date', isset($voucher) ? $voucher->committee_approval_date?->format('Y-m-d') : '')" />
    </div>
</div>
@unless(isset($voucher))
    @if(auth()->user()->canManageFavourites())
        <label class="mt-6 flex min-h-11 items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm font-bold text-amber-950"><input type="checkbox" name="save_as_favourite" value="1" @checked(old('save_as_favourite')) class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600"> ⭐ Simpan / kemas kini sebagai Favourite organisasi</label>
    @endif
    @if(auth()->user()->canManageFavourites() && session('favourite_duplicate_candidate'))
        @php($candidate = session('favourite_duplicate_candidate'))
        <div class="mt-4 rounded-lg border border-amber-400 bg-amber-50 p-4 text-sm text-amber-950">
            <p class="font-bold">Kemungkinan Favourite pendua: {{ $candidate['name'] }}</p>
            <p>{{ $candidate['phone'] ?: 'Tiada telefon' }} · {{ $candidate['email'] ?: 'Tiada e-mel' }}</p>
            <input type="hidden" name="duplicate_contact_id" value="{{ $candidate['id'] }}">
            <label class="mt-3 flex min-h-11 items-center gap-2 font-bold"><input type="checkbox" name="confirm_favourite_duplicate" value="1" class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600"> Gunakan dan kemas kini Favourite sedia ada ini</label>
        </div>
    @endif
@endunless
<div class="mt-6 flex flex-wrap gap-3">
    <x-primary-button>{{ isset($voucher) ? 'Simpan Perubahan' : 'Cipta Draf Baucar' }}</x-primary-button>
    <a href="{{ route('vouchers.index') }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-semibold text-gray-700">Kembali</a>
</div>
