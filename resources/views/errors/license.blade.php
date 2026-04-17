<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Terkunci - Lisensi Tidak Valid</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD@400,1,0" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-[#0e0e0e] text-gray-200 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-[#1c1b1b] border border-red-500/20 rounded-2xl p-8 shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-32 bg-red-600/20 blur-[50px] rounded-full pointer-events-none"></div>

        <div class="relative z-10 flex flex-col items-center text-center">
            
            <div class="w-16 h-16 bg-red-500/10 border border-red-500/20 rounded-full flex items-center justify-center mb-6 text-red-500">
                <span class="material-symbols-outlined text-3xl">lock</span>
            </div>

            <h1 class="text-2xl font-bold text-white mb-2 tracking-tight">Sistem Terkunci</h1>
            <p class="text-gray-400 text-sm mb-6 leading-relaxed">
                Akses ke aplikasi ini ditangguhkan oleh server lisensi pusat.
            </p>

            <div class="w-full bg-[#131313] border border-gray-800 rounded-xl p-4 mb-8">
                <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Keterangan Error</p>
                <p class="text-sm font-medium text-red-400">
                    {{ $pesanError ?? 'Sistem tidak dapat memverifikasi keabsahan lisensi Anda.' }}
                </p>
            </div>

@isset($pembayaran_url)
<a href="{{ $pembayaran_url }}" class="w-full inline-flex items-center justify-center gap-2 bg-white text-black hover:bg-gray-200 font-semibold text-sm py-3 px-4 rounded-lg transition-colors">
    <span class="material-symbols-outlined text-xl">wallet</span>
    Bayar Sekarang
</a>
  
@else
<a href="mailto:support@jtech.com" class="w-full inline-flex items-center justify-center gap-2 bg-white text-black hover:bg-gray-200 font-semibold text-sm py-3 px-4 rounded-lg transition-colors">
    <span class="material-symbols-outlined text-xl">support_agent</span>
    Hubungi Dukungan J-Tech
</a>
@endisset

            <div class="mt-6 flex items-center gap-2 text-xs text-gray-600">
                <span class="material-symbols-outlined text-[14px]">shield</span>
                <span>Protected by Jtech Lisensi</span>
            </div>
        </div>
    </div>

</body>
</html>