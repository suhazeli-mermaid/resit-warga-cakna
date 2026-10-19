<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Tukar Kata Laluan') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Kami akan menghantar pautan pengesahan sekali guna ke e-mel berdaftar anda. Kata laluan semasa tidak diperlukan.') }}
        </p>
    </header>

    @if (session('status') === 'password-reset-link-sent')
        <p class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-700" role="status">
            {{ __('Pautan tukar kata laluan telah dihantar ke e-mel berdaftar anda.') }}
        </p>
    @endif

    <x-input-error :messages="$errors->passwordReset->get('email')" class="mt-4" />

    <form method="POST" action="{{ route('account.password.email') }}" class="mt-6">
        @csrf

        <x-primary-button>
            {{ __('Hantar Pautan Pengesahan') }}
        </x-primary-button>
    </form>
</section>
