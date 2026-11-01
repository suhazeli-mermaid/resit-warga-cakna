<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pautan Akuan Tidak Tersedia</title>
    @if(is_file(public_path('hot')) || is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css','resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-gray-100 p-4 text-gray-900">
    <main class="mx-auto mt-12 max-w-xl rounded-2xl bg-white p-6 shadow sm:p-9">
        <div class="text-sm font-bold text-emerald-700">WARGA CAKNA</div>
        <h1 class="mt-2 text-2xl font-bold">Pautan Akuan Tidak Tersedia</h1>
        @if($reason === 'expired')
            <p class="mt-4 text-gray-700">Pautan ini telah tamat tempoh. Sila hubungi pihak Warga Cakna untuk mendapatkan pautan baharu.</p>
        @elseif($reason === 'used')
            <p class="mt-4 text-gray-700">Pautan ini telah digunakan dan akuan tidak boleh dihantar semula.</p>
        @else
            <p class="mt-4 text-gray-700">Pautan ini tidak sah atau telah digantikan. Sila gunakan pautan terbaharu yang dihantar kepada anda.</p>
        @endif
        <p class="mt-5 text-sm text-gray-500">Tiada akaun atau pendaftaran diperlukan untuk menggunakan pautan akuan yang sah.</p>
    </main>
</body>
</html>
