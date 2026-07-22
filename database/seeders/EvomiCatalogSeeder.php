<?php

namespace Database\Seeders;

use App\Models\Disclaimer;
use App\Models\Kurir;
use App\Models\Promo;
use App\Models\User;
use Illuminate\Database\Seeder;

class EvomiCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdminFromEnv();

        if (!User::where('email', 'demo@evomi.com')->exists()) {
            User::create([
                'name' => 'Demo User',
                'email' => 'demo@evomi.com',
                'password' => 'password123',
                'is_admin' => false,
                'nama_lengkap' => 'Pengguna Demo',
            ]);
        }

        // Produk dikelola ProductSeeder (gambar + urutan lengkap)

        if (!Kurir::exists()) {
            Kurir::create([
                'nama' => 'JNE Reguler',
                'jenis' => 'REG',
                'harga' => 20000,
                'destinasi' => 'Seluruh Indonesia',
            ]);
            Kurir::create([
                'nama' => 'SiCepat Halu',
                'jenis' => 'HALU',
                'harga' => 18000,
                'destinasi' => 'Jabodetabek & Jawa',
            ]);
        }

        if (!Promo::exists()) {
            Promo::create([
                'harga_promo' => 15000,
                'persentase_promo' => 8,
                'tanggal_berlaku_promo' => now()->addMonths(3)->toDateString(),
            ]);
        }

        if (!Disclaimer::exists()) {
            Disclaimer::create([
                'deskripsi' => 'Warna dan aroma aktual dapat sedikit berbeda tergantung batch produksi.',
            ]);
            Disclaimer::create([
                'deskripsi' => 'Simpan di tempat sejuk, jauh dari sinar matahari langsung.',
            ]);
        }
    }

    /**
     * Buat/update admin dari EVOMI_ADMIN_* di .env (pola Arcanisia).
     */
    private function seedAdminFromEnv(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $email = config('evomi.development_admin.email');
        $password = config('evomi.development_admin.password');

        if (!is_string($email) || $email === '' || !is_string($password) || $password === '') {
            $this->command?->warn(
                'EVOMI_ADMIN_EMAIL / EVOMI_ADMIN_PASSWORD belum di-set di .env — admin tidak di-seed.',
            );

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('evomi.development_admin.name') ?: 'Evomi Admin',
                'password' => $password,
                'is_admin' => true,
                'nama_lengkap' => config('evomi.development_admin.name') ?: 'Evomi Admin',
                'email_verified_at' => now(),
            ],
        );
    }
}
