<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profil Pengguna') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            @if($user->hasVoucherRole())
                <div id="digital-signature" class="scroll-mt-24 p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <h2 class="text-lg font-medium text-gray-900">Tandatangan Digital</h2>
                        <p class="mt-1 text-sm text-gray-600">PNG atau JPG/JPEG, maksimum 2 MB, dimensi 100 × 100 hingga 3000 × 3000 piksel. Disimpan secara peribadi. Snapshot berasingan dikunci pada baucar rasmi.</p>
                        @if(session('status') === 'signature-updated')<p class="mt-3 text-sm font-bold text-green-700">Tandatangan berjaya dikemas kini.</p>@endif
                        @if(session('status') === 'signature-deleted')<p class="mt-3 text-sm font-bold text-green-700">Tandatangan profil telah dipadam. Snapshot dokumen lama kekal.</p>@endif
                        <p class="mt-3 text-sm font-semibold {{ $user->hasDigitalSignature() ? 'text-green-700' : 'text-amber-700' }}">{{ $user->hasDigitalSignature() ? 'Tandatangan tersedia — '.$user->digital_signature_uploaded_at?->format('d/m/Y H:i') : 'Tandatangan belum dimuat naik.' }}</p>
                        @if($user->hasDigitalSignature())
                            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4"><div class="mb-2 text-xs font-bold uppercase text-gray-500">Pratonton Semasa</div><img src="{{ route('profile.signature.show') }}" alt="Tandatangan digital semasa" class="max-h-32 max-w-full rounded bg-white object-contain p-2"></div>
                        @endif
                        <form method="POST" enctype="multipart/form-data" action="{{ route('profile.signature.update') }}" class="mt-4 space-y-3">@csrf @method('PUT')<input type="file" name="digital_signature" accept="image/png,image/jpeg" required class="block w-full rounded-md border border-gray-300 p-3"><x-input-error :messages="$errors->get('digital_signature')" /><x-primary-button>{{ $user->hasDigitalSignature() ? 'Ganti Tandatangan' : 'Muat Naik Tandatangan' }}</x-primary-button></form>
                        @if($user->hasDigitalSignature())<form method="POST" action="{{ route('profile.signature.destroy') }}" class="mt-3" onsubmit="return confirm('Padam tandatangan profil? Snapshot pada dokumen lama tidak akan dipadam.');">@csrf @method('DELETE')<button class="min-h-11 rounded-md border border-red-300 px-4 text-sm font-bold text-red-700">Padam Tandatangan Profil</button></form>@endif
                    </div>
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
