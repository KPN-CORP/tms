<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Palang pengaman: suite ini TIDAK BOLEH menyentuh database sungguhan.
     *
     * Kelas tes bawaan Breeze memakai RefreshDatabase, yang menjalankan
     * migrate:fresh pada koneksi default. Kalau koneksi itu masih MySQL, yang
     * terhapus adalah tm_system beserta seluruh isinya -- roles, permissions,
     * committee assignment, ide, project. Ini sudah pernah terjadi.
     *
     * phpunit.xml sudah mengarahkan koneksi default ke sqlite in-memory, tapi
     * dua baris itu gampang ikut terkomentari lagi. Pemeriksaan di sini membuat
     * kelalaian tersebut berhenti sebagai kegagalan tes, bukan sebagai database
     * yang terlanjur kosong.
     *
     * Ekstensinya diperiksa lebih dulu di sini karena belum butuh aplikasi;
     * pemeriksaan koneksi menyusul di setUpTraits(), satu-satunya titik yang
     * sudah punya config TAPI belum menjalankan RefreshDatabase.
     */
    protected function setUp(): void
    {
        // Driver-nya ada di php.ini, bukan di repo ini. Tanpa pesan ini yang
        // muncul hanya "could not find driver" dari PDO, yang gampang disalah-
        // artikan sebagai phpunit.xml yang salah -- lalu "diperbaiki" dengan
        // mengembalikannya ke MySQL, persis kecelakaan yang dicegah di bawah.
        if (! extension_loaded('pdo_sqlite')) {
            throw new RuntimeException(
                'Tes dibatalkan: ekstensi PHP pdo_sqlite belum aktif, jadi database tes tidak bisa dibuat. '
                . 'Hapus tanda ";" pada baris "extension=pdo_sqlite" (dan "extension=sqlite3") di php.ini, '
                . 'lalu jalankan ulang. JANGAN mengarahkan phpunit.xml kembali ke MySQL: '
                . 'RefreshDatabase akan menghapus isi tm_system.'
            );
        }

        parent::setUp();
    }

    /** Dipanggil sesudah aplikasi dibuat, sebelum trait (termasuk RefreshDatabase) berjalan. */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                "Tes dibatalkan: koneksi default '{$connection}' memakai driver '{$driver}', bukan sqlite. "
                . 'RefreshDatabase akan menghapus isi database itu. '
                . 'Kembalikan DB_CONNECTION=sqlite dan DB_DATABASE=:memory: di phpunit.xml '
                . 'sebelum menjalankan tes lagi.'
            );
        }

        return parent::setUpTraits();
    }
}
