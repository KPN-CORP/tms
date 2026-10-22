# Migrasi lama (sebelum squash)

Isi folder ini adalah **62 file migrasi historis** yang dipakai sampai sebelum
squash. Semuanya sudah **tidak dijalankan** lagi: Laravel hanya membaca
`database/migrations/*.php` (non-rekursif), jadi folder ini aman disimpan
sebagai arsip.

## Kenapa di-squash

Riwayat lama tidak pernah bisa `php artisan migrate:fresh` dari nol. Dua belas
tabel inti **tidak pernah punya migrasi `create`** — dibuat manual langsung di
database (warisan aplikasi lama):

`business_units`, `companies`, `locations`, `departments`, `ideas`, `projects`,
`implementation_plans`, `implementation_indicators`, `project_budgets`,
`project_members`, `project_attachments`, `project_status_logs`.

Akibatnya migrasi pertama yang menyentuh tabel-tabel itu selalu gagal, mis.:

```
SQLSTATE[HY000]: General error: 1824 Failed to open the referenced table 'business_units'
  (alter table `role_business_units` add constraint ... references `business_units` (`id`))
```

Selain itu riwayat lama membawa beberapa masalah lain:

- `2026_08_13_..._sync_staging_drop_fks_and_add_columns` **membuang SELURUH
  foreign key** di database default — database kehilangan integritas referensial.
- Banyak migrasi hanya menambal migrasi sebelumnya (enum `projects.status`
  di-`MODIFY` lima kali, kolom budget committee lahir sebagai `budget_tier`,
  berubah jadi `budget_amount`, lalu jadi `budget_min`/`budget_max`).
- `2019_12_14_000001_create_personal_access_tokens_table` menduplikasi migrasi
  bawaan paket `laravel/sanctum`.

## Penggantinya

`database/migrations/2026_01_01_0000NN_*.php` — 12 berkas, satu kelompok tabel
per berkas, urut sesuai ketergantungan FK, berisi **bentuk akhir** skema.
Foreign key antar-tabel tm_system dipulihkan; kolom yang menunjuk user HCIS
(`kpncorp`) sengaja tanpa FK karena beda database.

## Database yang sudah berisi data (staging / produksi)

Jangan `migrate:fresh` di sana. Jalankan **sekali**
[`squash-reconcile.sql`](squash-reconcile.sql) — skrip itu menukar 61 baris
migrasi lama di tabel `migrations` dengan 12 baris yang baru, sehingga
`php artisan migrate` kembali menjadi no-op.

Catatan: skrip itu hanya menyamakan *catatan* migrasi, bukan struktur tabel.
Foreign key yang dulu dibuang `sync_staging_drop_fks` **tidak** ikut kembali di
database lama; bandingkan dengan hasil `migrate:fresh` bila ingin menyamakannya.
