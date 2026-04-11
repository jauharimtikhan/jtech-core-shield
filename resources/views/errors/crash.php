<?php
date_default_timezone_set('Asia/Jakarta');
?>
<!DOCTYPE html>
<html lang="id" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Mengalami Kendala (Error 500)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD@400,1,0" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .glitch-bg {
            background: repeating-linear-gradient(0deg,
                    rgba(0, 0, 0, 0.15),
                    rgba(0, 0, 0, 0.15) 1px,
                    transparent 1px,
                    transparent 2px);
        }
    </style>
</head>

<body class="bg-[#0a0a0a] text-gray-200 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute inset-0 glitch-bg pointer-events-none opacity-50"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none"></div>

    <div class="max-w-2xl w-full bg-[#131313]/90 backdrop-blur-md border border-zinc-800 rounded-2xl shadow-2xl relative z-10 overflow-hidden">

        <div class="flex items-center gap-2 px-4 py-3 border-b border-zinc-800 bg-[#1a1a1a]">
            <div class="flex gap-1.5">
                <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
            </div>
            <p class="text-xs text-zinc-500 font-mono ml-2">sys_kernel_panic.log</p>
        </div>

        <div class="p-8 md:p-10">
            <div class="flex items-start gap-6">
                <div class="shrink-0 w-16 h-16 rounded-2xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-500">
                    <span class="material-symbols-outlined text-4xl">warning</span>
                </div>

                <div class="space-y-2">
                    <h1 class="text-2xl font-extrabold text-white tracking-tight">Fatal System Error</h1>
                    <p class="text-zinc-400 text-sm leading-relaxed">
                        Aplikasi tidak dapat melanjutkan proses karena terjadi kesalahan sistem yang kritis (Error 500). Sistem perlindungan J-Tech Core telah menghentikan eksekusi untuk mencegah kerusakan data.
                    </p>
                </div>
            </div>

            <div class="mt-8">
                <div class="flex justify-between items-end mb-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Diagnostic Details</p>
                    <p class="text-[10px] font-mono text-red-400/70">CRASH_ID: <?= strtoupper(uniqid('ERR-')) ?></p>
                </div>

                <div class="w-full bg-[#090909] border border-zinc-800 rounded-xl p-4 overflow-x-auto">
                    <code class="text-xs font-mono text-red-400 ">
                        <?= isset($exception) ? htmlspecialchars($exception->getMessage()) : 'Syntax error or unexpected logic failure.' ?>
                    </code>
                </div>
            </div>

            <div class="mt-8 flex flex-col sm:flex-row items-center gap-3">
                <button onclick="window.location.reload()" class="w-full sm:w-auto inline-flex justify-center items-center gap-2 bg-white text-black hover:bg-zinc-200 font-bold text-sm py-3 px-6 rounded-lg transition-all">
                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                    Coba Muat Ulang
                </button>
                <a href="mailto:support@jtech.com" class="w-full sm:w-auto inline-flex justify-center items-center gap-2 bg-zinc-800/50 text-white hover:bg-zinc-800 border border-zinc-700 font-semibold text-sm py-3 px-6 rounded-lg transition-all">
                    <span class="material-symbols-outlined text-[18px]">support_agent</span>
                    Laporkan Kendala
                </a>
            </div>
        </div>

        <div class="px-8 py-4 bg-[#1a1a1a] border-t border-zinc-800 flex justify-between items-center text-[10px] text-zinc-600 font-mono">
            <span>Powered by J-Tech Core Engine</span>
            <span>Server Time: <?= date('Y-m-d H:i:s') ?></span>
        </div>
    </div>

</body>

</html>