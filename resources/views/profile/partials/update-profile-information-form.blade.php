<section id="profile-information" class="scroll-mt-24">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Identiti Profil Pengguna
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Nama dan gelaran ini digunakan sebagai identiti individu pada dokumen rasmi. Jawatan Warga Cakna diselenggara oleh Admin.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Nama Penuh" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="salutation" value="Gelaran (pilihan)" />
            <select id="salutation" name="salutation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Pilih gelaran</option>
                @foreach(\App\Models\User::SALUTATIONS as $salutation)
                    <option value="{{ $salutation }}" @selected(old('salutation', $user->salutation) === $salutation)>{{ $salutation }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Pilih “Tiada” untuk memaparkan Nama Penuh tanpa gelaran.</p>
            <x-input-error class="mt-2" :messages="$errors->get('salutation')" />
        </div>

        <div>
            <x-input-label value="Jawatan Warga Cakna" />
            <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                {{ $user->organization_position ?: 'Belum ditetapkan oleh Admin' }}
            </div>
            <p class="mt-1 text-xs text-gray-500">Hubungi Admin jika jawatan organisasi ini perlu dikemas kini.</p>
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan Profil</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
