<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Resit Derma - WARGA CAKNA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Top Navigation Bar -->
    <nav class="bg-emerald-800 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="{{ asset('images/logo-warga-cakna.png') }}" class="h-10 w-auto bg-white rounded-full p-1" alt="WARGA CAKNA Logo">
                <div>
                    <h1 class="text-lg font-bold leading-tight">WARGA CAKNA</h1>
                    <p class="text-xs text-emerald-200">Sistem Pengurusan Resit & Kewangan</p>
                </div>
            </div>
            <a href="{{ route('receipts.create') }}" class="bg-yellow-400 hover:bg-yellow-500 text-emerald-950 font-bold px-4 py-2 rounded-lg text-sm shadow transition">
                + Cipta Resit Baharu
            </a>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if(session('success'))
            <div class="bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded mb-6 flex justify-between items-center shadow-sm">
                <span>{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="font-bold text-emerald-900">&times;</button>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800">Senarai Resit Derma</h2>
                <span class="text-sm text-gray-500">Jumlah Rekod: {{ $receipts->total() }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-emerald-800 text-white uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">No. Resit</th>
                            <th class="px-6 py-3">Nama Penderma</th>
                            <th class="px-6 py-3">Tarikh</th>
                            <th class="px-6 py-3">Kaedah Bayaran</th>
                            <th class="px-6 py-3 text-right">Jumlah (RM)</th>
                            <th class="px-6 py-3 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($receipts as $receipt)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-mono font-bold text-emerald-700">{{ $receipt->receipt_no }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900">{{ $receipt->donor_name }}</div>
                                    <div class="text-xs text-gray-500">{{ $receipt->donor_phone ?? 'Tiada No. Tel' }}</div>
                                </td>
                                <td class="px-6 py-4">{{ \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 uppercase font-semibold text-xs text-gray-700">
                                    <span class="bg-gray-100 border border-gray-300 px-2 py-1 rounded">
                                        {{ $receipt->payment_method }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-emerald-700">RM {{ number_format($receipt->amount, 2) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <a href="{{ route('receipts.pdf', $receipt->id) }}" class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1.5 rounded text-xs transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Muat Turun PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                    Tiada rekod resit derma dijumpai. Sila klik butang di atas untuk cipta resit baharu.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                {{ $receipts->links() }}
            </div>
        </div>
    </main>

</body>
</html>