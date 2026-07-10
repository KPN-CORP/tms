# Rencana Migrasi RBAC: Custom → Spatie laravel-permission

Status: **DRAFT / RENCANA** (belum ada kode yang diubah)
Target: Laravel 10.50 · PHP 8.1 · spatie/laravel-permission ^6.0

---

## 1. Tujuan & Keputusan

| Keputusan | Pilihan |
|-----------|---------|
| Paradigma role | **Hapus `active_role`** → pakai model native Spatie (izin = gabungan/union semua role user) |
| Sumber kebenaran RBAC | Tabel Spatie (`roles`, `permissions`, `model_has_roles`, `role_has_permissions`) + **seeder** |
| Menus (dinamis via DB) | **Dihapus** → sidebar dirender dengan `@can` per permission |
| Widgets | **Dihapus** (tidak dipakai) |
| Restrict / scope (BU/Company/Location/Employee) | **Dibuang dari role** → nanti pindah ke **atribut user** (BU/Unit/Location/Job Level), sesuai FR-002. Tabel `role_*` scope di-DROP |
| Super Admin | Akses penuh via `Gate::before` (auto lolos semua izin) |
| Izin panel admin | Tambah `role.manage` (Super Admin) & `user.manage` (Super Admin + Admin) |
| Penamaan permission | **Dot-notation** (`idea.create`, `proposal.submit`) — konvensi Spatie |
| DB | Boleh dirapikan demi kejelasan (lihat §4) |
| Rollout | **Bertahap** — mulai 2 role dulu, jangan semua sekaligus |

> **Keputusan sudah dikunci (2026-07-10):** union tanpa Switch Role · Gate::before Super Admin ·
> `role.manage`+`user.manage` · scope dibuang dari role (pindah ke atribut user, dikerjakan belakangan).

---

## 2. Kondisi Saat Ini (inventory yang terdampak)

**Tabel:**
- `roles` (id, name, slug, description, is_system_role, is_active)
- `permissions` (id, name, slug)
- `role_permissions`, `user_roles` (pivot custom)
- `menus`, `role_menus`, `permission_menus`
- `widgets`, `role_widgets`
- `role_business_units`, `role_companies`, `role_locations`, `role_employees`
- `users` (punya kolom redundan `role` string)

**Kode:**
- `app/Services/RBAC/*` → `RbacService`, `RoleService`, `PermissionService`, `MenuService`, `WidgetService`, `RoleRestrictionService`
- `app/Http/Middleware/PermissionMiddleware.php` (alias `permission:...`)
- `AppServiceProvider` → View::composer inject `activeRole`, `sidebarMenus`; Blade directive `@permission`
- `AuthenticatedSessionController` → set `session('active_role')` saat login
- `RoleSelectionController` → `go-admin`/`go-committee`/`go-employee` (switch active_role)
- `layouts/sidebar.blade.php` → render `$sidebarMenus`
- `Role`/`User` model → relasi & helper custom (`hasRole`, `hasPermission`)

---

## 3. Arsitektur Target

```
User ──HasRoles(trait Spatie)──► Roles ──► Permissions      (Spatie: pengecekan izin)
  │
  └── (opsional) Roles ──► role_business_units / companies / locations / employees   (custom: data scope)

Sidebar  = dirender per item dengan @can('...')          (menus DB dihapus)
Enforcement rute = middleware 'permission:...' Spatie      (PermissionMiddleware custom dihapus)
Scope data = RoleRestrictionService (union semua role user) (active_role dihapus)
```

Prinsip: **Spatie mengurus "boleh melakukan apa"**; kode custom hanya mengurus "boleh melihat data mana" (scope) yang memang di luar cakupan Spatie.

---

## 4. Refactor Database

### DROP (tidak dipakai lagi)
| Tabel/Kolom | Alasan |
|-------------|--------|
| `menus`, `role_menus`, `permission_menus` | Sidebar pindah ke `@can` |
| `widgets`, `role_widgets` | Tidak dipakai |
| `user_roles` | Diganti `model_has_roles` (Spatie) |
| `role_permissions` | Diganti `role_has_permissions` (Spatie) |
| `role_business_units`, `role_companies`, `role_locations`, `role_employees` | Scope pindah ke atribut user (bukan role) |
| `users.role` (string) | Redundan; role dikelola Spatie |
| `roles.slug` | Spatie pakai `name`; slug tak perlu setelah `active_role` hilang |
| `permissions.slug` | Sama; cukup `name` dot-notation |

### ADD (Spatie)
- Kolom `guard_name` di `roles` & `permissions` (isi `'web'`).
- Pivot Spatie: `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

### KEEP (tetap)
- `business_units`, `companies`, `locations`, `users`
- `roles`, `permissions` (skema dirapikan mengikuti Spatie)

### FUTURE (scope berbasis user — belum dikerjakan sekarang)
- Tambah atribut ke `users`: `business_unit_id`, `unit_id`/`company_id`, `location_id`, `job_level` (nullable).
- Scope data (FR-002) diturunkan dari atribut user ini, bukan dari role.

### Skema akhir `roles` & `permissions`
```
roles:        id, name, guard_name, description(nullable), is_active, timestamps
permissions:  id, name, guard_name, timestamps
```

> Catatan: karena penamaan & isi permission berubah total (dot-notation), migrasi data
> permission lama TIDAK disalin. Roles & permissions **di-seed ulang** dari nol
> (best practice Spatie). Hanya perlu memastikan user di-assign ulang ke role.

---

## 5. Konvensi Penamaan Permission

Format: `domain.action` (huruf kecil, titik sebagai pemisah). Contoh: `idea.create`, `proposal.submit`, `visibility.manage`.

Keuntungan: rapi, konsisten, mendukung wildcard Spatie bila nanti dibutuhkan (mis. `idea.*`).

---

## 6. Matriks Role → Permission

### Domain roles (dari Anda)
| Role | Permissions |
|------|-------------|
| **Employee** | `dashboard.view`, `guideline.view`, `idea.create`, `idea.submit`, `history.view` |
| **Committee** | `idea.review`, `idea.approve`, `idea.reject`, `project.create-shell`, `visibility.manage` |
| **Project Leader** | `proposal.edit`, `proposal.submit`, `success-indicator.manage`, `budget.manage`, `implementation.manage`, `team.manage` |
| **Project Sponsor** | `proposal.approve`, `proposal.revision`, `completion.approve` |
| **Project Team** | `project.update`, `budget.actual`, `completion.submit` |
| **Admin** | `committee.assign`, `sla.manage`, `reminder.manage`, `visibility.manage`, `project.bulk-upload` |
| **Super Admin** | `guideline.upload`, `project-category.manage`, `backup-approver.manage`, `job-level.manage`, `override.role` |

### ⚠️ Permission platform-admin yang PERLU ditambah
Matriks di atas fokus ke domain Idea/Project, tapi UI admin (kelola role/user) belum punya izin. Usulan tambahan:

| Role | Tambahan |
|------|----------|
| **Super Admin** | `role.manage`, `user.manage` (kelola RBAC & user) |
| **Admin** | `user.manage` (opsional) |

Rute admin sekarang `permission:manage-role` → jadi `permission:role.manage`.

> Perlu keputusan Anda: apakah Super Admin otomatis punya SEMUA izin (via Gate::before super-admin)
> atau tetap eksplisit per permission? Best practice Spatie: **Gate::before** untuk Super Admin.

---

## 7. Penanganan komponen yang berubah/dihapus

### 7.1 `active_role` — DIHAPUS
Dampak & penggantinya:
- `AuthenticatedSessionController` → tak perlu set/redirect berdasar active_role; langsung ke dashboard.
- `RoleSelectionController` + rute `go-admin`/`go-committee`/`go-employee` + view `select-role` → **dihapus** (tak ada lagi "switch role"; user memakai gabungan izin semua role-nya).
- `RoleService` (baca session) → **dihapus**.
- Sidebar "Switch Role" → dihapus.
- Pengecekan izin: `$user->can('idea.create')` (union semua role), bukan lagi per role aktif.

### 7.2 Menus & Widgets — DIHAPUS
- Sidebar dirender statis dengan gating `@can`:
  ```blade
  @can('dashboard.view')   <a href="...">Dashboard</a>       @endcan
  @can('role.manage')      <a href="...">Role Management</a> @endcan
  ```
- Hapus `MenuService`, `WidgetService`, `MenuController` (bila hanya untuk fitur ini), View::composer terkait.

### 7.3 Restrict / Scope — DIBUANG dari role, pindah ke atribut user
- Tabel `role_business_units`/`role_companies`/`role_locations`/`role_employees` di-DROP.
- `RoleRestrictionService` dihapus.
- Field restrict di form Create Role (`business_units[]`, `companies[]`, `locations[]`, `employees[]`) + Tom Select terkait dihapus.
- **Future** (belum sekarang): scope diturunkan dari atribut user (`users.business_unit_id`, `location_id`, `job_level`, dst) sesuai FR-002/FR-340. Dikerjakan setelah RBAC inti Spatie stabil.

---

## 8. Fase Migrasi (bertahap, tiap fase bisa dites)

**Fase 0 — Pengaman**
- `git init` + commit kondisi sekarang.
- `mysqldump tm_system > backup_pre_spatie.sql`.

**Fase 1 — Install Spatie**
- `composer require spatie/laravel-permission`
- publish config + migration Spatie.
- daftarkan alias middleware (`role`, `permission`, `role_or_permission`) di `Kernel.php`.
- `User` pakai trait `HasRoles`.

**Fase 2 — Refactor skema DB**
- Migrasi: tambah `guard_name`; buat pivot Spatie; drop tabel/kolom di §4.
- Jalankan migrasi Spatie (buat `model_has_roles`, dll).

**Fase 3 — Seeder RBAC (source of truth)**
- `RolesAndPermissionsSeeder`: buat semua permission (§6) + role + assign permission ke role.
- `Gate::before` untuk Super Admin (bila dipilih).
- Assign user contoh ke role.

**Fase 4 — Ganti enforcement**
- Rute: `permission:manage-role` → `permission:role.manage` (middleware Spatie).
- Blade: `@permission` → `@can`; hapus directive custom.
- Hapus `PermissionMiddleware`, `RbacService`, `PermissionService`.

**Fase 5 — Hapus active_role & menu/widget**
- Bersihkan `AuthenticatedSessionController`, `RoleSelectionController`, `RoleService`, `MenuService`, `WidgetService`, View::composer, sidebar dinamis.
- Render sidebar dengan `@can`.

**Fase 6 — Restrict/scope**
- Ubah `RoleRestrictionService` → berbasis User (union). Uji ulang scenario scope.

**Fase 7 — Bersih-bersih & test**
- `php artisan permission:cache-reset`.
- Uji: login tiap role, akses rute ber-izin, sidebar, scope data.

---

## 9. Perubahan Kode (ringkas, file-by-file)

| File | Aksi |
|------|------|
| `composer.json` | + spatie/laravel-permission |
| `app/Models/User.php` | + `HasRoles`, hapus `roles()/hasRole()/hasPermission()` custom |
| `app/Models/Role.php`, `Permission.php` | extends model Spatie; sisakan relasi scope di Role |
| `app/Http/Kernel.php` | daftarkan alias middleware Spatie |
| `routes/web.php` | ganti nama permission di middleware; hapus rute select-role/go-* |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | hapus logika active_role |
| `app/Http/Controllers/RoleSelectionController.php` | **hapus** |
| `app/Services/RBAC/RbacService,RoleService,PermissionService,MenuService,WidgetService` | **hapus** |
| `app/Services/RBAC/RoleRestrictionService.php` | refactor → berbasis User (union) |
| `app/Providers/AppServiceProvider.php` | hapus composer `sidebarMenus`/`activeRole` & directive `@permission` |
| `resources/views/layouts/sidebar.blade.php` | render statis + `@can` |
| `database/seeders/RolesAndPermissionsSeeder.php` | **baru** (source of truth) |
| `database/migrations/*` | migrasi refactor skema (§4) |
| `resources/views/admin/role/*` | sesuaikan store: `syncРermissions`/`syncRoles` Spatie |

---

## 10. Strategi Rollout Bertahap

Jangan seed & aktifkan semua role sekaligus. Urutan aman (vertical slice):

1. **Slice 1 — Employee + Committee** saja: seed 2 role + permission-nya, gating 1–2 rute & sidebar, verifikasi login kedua role.
2. **Slice 2 — Admin + Super Admin** (+ `role.manage`, `user.manage`, `Gate::before`): amankan panel admin.
3. **Slice 3 — Project Leader / Sponsor / Team**: tambah sisa domain project.
4. **Slice 4 — Scope/restrict** berbasis user (union) diaktifkan.

Tiap slice: seed → gating → test → commit.

---

## 11. Backup & Rollback
- Sebelum mulai: commit Git + `mysqldump`.
- Tiap fase = commit terpisah → mudah `git revert`.
- Rollback DB: `mysql tm_system < backup_pre_spatie.sql`.

---

## 12. Verifikasi / Test
- Login tiap role → cek `@can` benar (menu & tombol muncul sesuai izin).
- Akses rute ber-`permission:` → 403 bila tak berizin.
- User multi-role → izin = union (mis. user Employee+Committee bisa keduanya).
- Scope: role restrict BU=Corp → `allowedLocationIds` hanya lingkup Corp; kosong = semua.
- `php artisan permission:show` untuk audit matriks.

---

## 13. Risiko & Catatan
- **Konflik nama tabel**: `roles`/`permissions` sudah ada; migrasi harus drop/ubah hati-hati (backup wajib).
- **Kehilangan konsep active_role**: fitur "Switch Role" & FR terkait pemilihan konteks role hilang. Bila FR butuh pemilihan konteks (mis. Committee per-BU), pertimbangkan Spatie **Teams** — dicatat sebagai bahan diskusi, belum diputuskan.
- **Project Sponsor/Leader/Team**: di FR ini adalah peran **per-project** (di-assign saat pembuatan project, FR-115/251). Role global Spatie di sini memberi *kapabilitas*; penetapan per-project tetap butuh mekanisme terpisah. Jangan campur keduanya.
- **Super Admin all-access**: rekomendasi `Gate::before` agar tak perlu daftar semua izin manual.

---

## Lampiran A — Snippet kunci (referensi implementasi nanti)

Seeder (inti):
```php
$perms = ['dashboard.view','guideline.view','idea.create', /* ... */];
foreach ($perms as $p) Permission::firstOrCreate(['name' => $p]);

$employee = Role::firstOrCreate(['name' => 'Employee']);
$employee->syncPermissions(['dashboard.view','guideline.view','idea.create','idea.submit','history.view']);

$user->assignRole('Employee');
```

Gate super-admin (opsional):
```php
// AppServiceProvider::boot
Gate::before(fn ($user, $ability) => $user->hasRole('Super Admin') ? true : null);
```

Middleware rute:
```php
Route::middleware('permission:role.manage')->group(function () { /* admin roles */ });
```

Blade:
```blade
@can('idea.create') <a href="...">Create Idea</a> @endcan
@role('Super Admin') ... @endrole
```
