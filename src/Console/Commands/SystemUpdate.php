<?php

namespace Jtech\CoreShield\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class SystemUpdate extends Command
{
    protected $signature = 'app:update';
    protected $description = 'Cek dan install pembaruan sistem dari server lisensi.';

    private string $serverUrl = 'https://preview-client.jtechpanel.dpdns.org/api/v1';

    public function handle()
    {
        $this->info('Mengecek versi terbaru...');
        $currentVersion = env('APP_VERSION', '1.0.0');
        $licenseKey = env('LICENSE_KEY');
        $hwid = $this->getMachineId();

        // 1. Cek Update
        $response = Http::post("{$this->serverUrl}/update/check", [
            'license_key' => $licenseKey,
            'hwid' => $hwid,
            'current_version' => $currentVersion
        ]);

        if (!$response->successful() || !$response->json('update_available')) {
            $this->info('Sistem Anda sudah dalam versi terbaru (' . $currentVersion . ').');
            return;
        }

        $targetVersion = $response->json('latest_version');
        $this->warn("Versi baru ditemukan: v{$targetVersion}");
        $this->info("Changelog: " . $response->json('changelog'));

        if (!$this->confirm('Apakah Anda ingin mengupdate sistem sekarang?')) {
            return;
        }

        // 2. Minta URL Download Zip Update
        $this->info('Meminta akses download...');
        $downloadRes = Http::post("{$this->serverUrl}/update/download", [
            'license_key' => $licenseKey,
            'hwid' => $hwid,
            'target_version' => $targetVersion
        ]);

        $downloadUrl = $downloadRes->json('download_url');

        if (!$downloadUrl) {
            $this->error('Gagal mendapatkan URL download dari server.');
            return;
        }

        // Pastikan direktori updates ada
        $updateDir = storage_path('app/updates');
        if (!file_exists($updateDir)) {
            mkdir($updateDir, 0755, true);
        }

        $tempZipPath = $updateDir . "/v{$targetVersion}.zip";

        // 3. Proses Download (Streaming / Sink)
        $this->info('Mendownload patch update. Mohon tunggu...');
        $downloadStream = Http::timeout(300)->sink($tempZipPath)->get($downloadUrl);

        if (!$downloadStream->successful()) {
            $this->error("Gagal mendownload update. HTTP Status: " . $downloadStream->status());
            if (file_exists($tempZipPath)) unlink($tempZipPath);
            return;
        }

        if (filesize($tempZipPath) === 0) {
            $this->error('File zip yang didownload kosong (0 bytes).');
            unlink($tempZipPath);
            return;
        }

        $this->info('Mengekstrak dan menerapkan update...');

        $zip = new ZipArchive;
        $res = $zip->open($tempZipPath);

        if ($res === TRUE) {
            // Ekstrak langsung ke root folder project
            $zip->extractTo(base_path());
            $zip->close();

            // Hapus file temp zip
            unlink($tempZipPath);

            // --- BARU: Update versi di .env ---
            $this->info('Memperbarui versi di environment (APP_VERSION)...');
            $this->updateEnvVersion($targetVersion);

            // 4. Post-Update Scripts
            $this->call('migrate', ['--force' => true]);
            $this->call('optimize:clear'); // Ini penting biar config ngebaca .env yang baru diubah!

            $this->info("Berhasil! Sistem telah diupdate ke versi v{$targetVersion}.");
        } else {
            $errorMsg = $this->getZipErrorString($res);
            $this->error("Gagal mengekstrak file zip. Kode Error: {$res} ({$errorMsg})");
            if (file_exists($tempZipPath)) unlink($tempZipPath);
        }
    }

    /**
     * Fungsi Helper untuk menulis ulang APP_VERSION di .env
     */
    private function updateEnvVersion(string $newVersion): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->warn('File .env tidak ditemukan. Gagal mengupdate APP_VERSION.');
            return;
        }

        $envContent = file_get_contents($envPath);

        // Cek apakah key APP_VERSION sudah ada di .env menggunakan Regex
        if (preg_match('/^APP_VERSION=/m', $envContent)) {
            // Kalau ada, timpa nilainya
            $envContent = preg_replace(
                '/^APP_VERSION=.*$/m',
                'APP_VERSION=' . $newVersion,
                $envContent
            );
        } else {
            // Kalau belum ada, tambahkan di baris paling bawah
            $envContent .= PHP_EOL . 'APP_VERSION=' . $newVersion . PHP_EOL;
        }

        // Tulis ulang file .env
        file_put_contents($envPath, $envContent);
    }

    private function getMachineId()
    {
        $os = strtoupper(substr(PHP_OS, 0, 3));

        if ($os === 'WIN') {
            $output = null;
            $retval = null;
            exec('wmic csproduct get uuid 2>&1', $output, $retval);
            if ($retval === 0 && isset($output[1])) {
                return trim($output[1]);
            }
        } else {
            $paths = ['/etc/machine-id', '/var/lib/dbus/machine-id'];
            foreach ($paths as $path) {
                if (is_readable($path)) {
                    $id = trim(file_get_contents($path));
                    if (!empty($id)) {
                        return $id;
                    }
                }
            }
        }

        $fallbackPath = storage_path('app/.hwid');
        if (!file_exists($fallbackPath)) {
            $uuid = \Illuminate\Support\Str::uuid()->toString();
            file_put_contents($fallbackPath, $uuid);
            return $uuid;
        }

        return trim(file_get_contents($fallbackPath));
    }

    private function getZipErrorString(int $code): string
    {
        return match ($code) {
            ZipArchive::ER_EXISTS => 'File sudah ada.',
            ZipArchive::ER_INCONS => 'Inkonsistensi file zip.',
            ZipArchive::ER_INVAL => 'Argumen tidak valid.',
            ZipArchive::ER_MEMORY => 'Malloc gagal / kehabisan memori.',
            ZipArchive::ER_NOENT => 'File tidak ditemukan.',
            ZipArchive::ER_NOZIP => 'Bukan file zip.',
            ZipArchive::ER_OPEN => 'Tidak dapat membuka file.',
            ZipArchive::ER_READ => 'Gagal membaca file.',
            ZipArchive::ER_SEEK => 'Gagal mencari (seek) file.',
            default => 'Kesalahan tidak diketahui.',
        };
    }
}
