<?php

namespace Jtech\CoreShield\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CoreAppMiddleware
{
    // Config Lisensi
    private string $serverUrl = 'https://preview-client.jtechpanel.dpdns.org/api/v1/license/verify';
    private string $appVersion; // Versi aplikasi client saat ini

    public function handle(Request $request, Closure $next): Response
    {
        $this->appVersion = env('APP_VERSION', '1.0.0');

        // Gunakan Cache biar gak nembak API tiap ada request. Cache diset 1 jam (3600 detik).
        $licenseData = Cache::remember('app_license_status', 3600, function () use ($request) {
            return $this->verifyWithServer($request);
        });
        // $licenseData = $this->verifyWithServer($request);

        // Kalau lisensi gak valid, tampilkan custom view!
        if (!$licenseData['is_valid']) {
            // Mengembalikan custom view HTTP 403 dengan membawa pesan error
            return response()->view('coreshield::errors.license', [
                'pesanError' => $licenseData['message'],
                'pembayaran_url' => $licenseData['pembayaran_url'] ?? null
            ], 403);
        }

        try {
            $encryptedPayload = env('DB_SECURE_PAYLOAD');
            $unlockKey = $licenseData['data']['unlock_key'] ?? null;

            if ($encryptedPayload && $unlockKey) {
                // Dekripsi password (pakai AES-256-CBC)
                $realDatabaseName = $this->decryptDatabasePassword($encryptedPayload, $unlockKey);

                // Suntikkan NAMA DATABASE ke memori Laravel
                config(['database.connections.mysql.database' => $realDatabaseName]);
                \Illuminate\Support\Facades\DB::purge('mysql');
            } else {
                // Kalau variabel env hilang, paksa error
                return response()->view('coreshield::errors.error', [
                    'exception' => new \Exception("Sistem Terkunci: Integritas keamanan database rusak.")
                ], 500);
            }
        } catch (\Exception $e) {
            Log::emergency('Gagal membuka gembok database: ' . $e->getMessage());
            return response()->view('coreshield::errors.error', [
                'exception' => $e
            ], 500);
        }

        return $next($request);
    }

    private function decryptDatabasePassword(string $encryptedPayload, string $key): string
    {
        // Parse payload (karena kita akan enkripsi pakai json)
        $data = json_decode(base64_decode($encryptedPayload), true);

        if (!isset($data['iv']) || !isset($data['value'])) {
            throw new \Exception('Format payload enkripsi tidak valid.');
        }

        $iv = base64_decode($data['iv']);
        $encryptedValue = $data['value'];

        // Pastikan panjang key sesuai untuk AES-256 (32 karakter)
        $encryptionKey = hash('sha256', $key, true);

        $decrypted = openssl_decrypt($encryptedValue, 'AES-256-CBC', $encryptionKey, 0, $iv);

        if ($decrypted === false) {
            throw new \Exception('Kunci dekripsi salah atau payload rusak.');
        }

        return $decrypted;
    }

    /**
     * Berkomunikasi dengan License Server & Verifikasi RSA Signature
     * Return array: ['is_valid' => bool, 'message' => string]
     */
    private function verifyWithServer(Request $request): array
    {
        try {
            $licenseKey = env('LICENSE_KEY'); // Harusnya taruh di .env client

            if (!$licenseKey) {
                Log::error('Pengecekan lisensi gagal: LICENSE_KEY tidak ditemukan di .env');
                return ['is_valid' => false, 'message' => 'Kunci Lisensi tidak dikonfigurasi pada sistem.'];
            }

            // 1. Dapatkan Hardware ID unik
            $hwid = $this->getMachineId();
            Log::debug("HWID: {$hwid}");

            // 2. Tembak API License Server
            $response = Http::timeout(10)->post($this->serverUrl, [
                'license_key' => $licenseKey,
                'hwid'        => $hwid,
                'domain'      => $request->getHost(),
                'app_version' => $this->appVersion,
                'hostname'    => gethostname()
            ]);

            if (!$response->successful()) {
                Log::error('Server Lisensi tidak dapat dihubungi atau mengembalikan error: ' . json_encode($response->json(), JSON_PRETTY_PRINT));
                return ['is_valid' => false, 'message' => 'Gagal terhubung ke Server Lisensi utama.'];
            }

            $payload = $response->json();
            Log::info($payload);

            // Pastikan format response sesuai
            if (!isset($payload['data']) || !isset($payload['signature'])) {
                Log::error('Pengecekan lisensi gagal: Format respons dari server tidak valid.');
                return ['is_valid' => false, 'message' => 'Menerima respons tidak valid dari server lisensi.'];
            }


            // 3. Verifikasi Signature RSA (Anti API Spoofing)
            $publicKeyPath = storage_path('app/keys/licence-public.pem');

            if (!file_exists($publicKeyPath)) {
                Log::critical('Pengecekan lisensi gagal: Public key tidak ditemukan!');
                return ['is_valid' => false, 'message' => 'File keamanan (Public Key) sistem hilang.'];
            }

            $publicKey = file_get_contents($publicKeyPath);
            $dataString = json_encode($payload['data']);
            $signature = base64_decode($payload['signature']);

            // Proses verifikasi signature
            $isSignatureValid = openssl_verify($dataString, $signature, $publicKey, OPENSSL_ALGO_SHA256);

            if ($isSignatureValid !== 1) {
                Log::critical('KRITIS: Signature respons lisensi TIDAK VALID! Kemungkinan serangan spoofing.');
                return ['is_valid' => false, 'message' => 'Integritas lisensi terganggu. Autentikasi ditolak.'];
            }

            // 4. Cek status is_valid dari server
            if ($payload['data']['is_valid'] !== true) {
                $serverReason = $payload['data']['reason'] ?? 'Lisensi ditolak oleh server.';
                $urlPembayaran = $payload['data']['pembayaran_url'] ?? null;
                Log::error('Pengecekan lisensi gagal: ' . $serverReason);

                // Kembalikan alasan spesifik dari server (misal: "Pembayaran belum lunas")
                return ['is_valid' => false, 'message' => $serverReason, 'pembayaran_url' => $urlPembayaran];
            }

            return ['is_valid' => true, 'message' => 'Lisensi Valid'];
        } catch (\Exception $e) {
            Log::critical('Pengecualian pengecekan lisensi: ' . $e->getMessage());
            // Kalau gagal konek (misal karena jaringan), untuk amannya kita anggap false.
            return ['is_valid' => false, 'message' => 'Terjadi kesalahan sistem saat memverifikasi lisensi.'];
        }
    }

    /**
     * Fungsi Helper untuk Mengambil Hardware ID / Machine UUID 
     */
    private function getMachineId(): string
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
}
