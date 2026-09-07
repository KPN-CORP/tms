<?php

namespace App\Services\Notification;

use App\Models\EmailNotificationSchedule;
use App\Models\KpnEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Menentukan penerima email sebuah Email Notification dari tabel employees (hcis).
 *
 * Aturan penyaringan:
 *  - Antar filter bersifat DAN: karyawan harus lolos semua filter yang diisi.
 *  - Di dalam satu filter bersifat ATAU: cukup cocok salah satu nilai yang dipilih.
 *  - Filter yang dikosongkan berarti tanpa batasan untuk aspek tersebut.
 *
 * Pemetaan filter -> kolom employees (diverifikasi terhadap data hcis):
 *  Business Unit -> group_company
 *  Unit          -> unit          (lihat unitValues(), butuh penyesuaian)
 *  Company       -> company_name  (BUKAN contribution_level_code)
 *  Location      -> office_area   (BUKAN work_area_code)
 *  Job Level     -> job_level
 */
class EmailRecipientResolver
{
    /** Alamat email penerima, unik dan sudah dibersihkan. */
    public function emailsFor(EmailNotificationSchedule $schedule): Collection
    {
        return $this->query($schedule)
            ->pluck('email')
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    /** Karyawan penerima (untuk pratinjau: nama, unit, job level). */
    public function employeesFor(EmailNotificationSchedule $schedule, int $limit = 0): Collection
    {
        $q = $this->query($schedule)->orderBy('fullname');

        return ($limit > 0 ? $q->limit($limit) : $q)
            ->get(['employee_id', 'fullname', 'email', 'group_company', 'unit', 'company_name', 'office_area', 'job_level']);
    }

    public function countFor(EmailNotificationSchedule $schedule): int
    {
        return $this->query($schedule)->distinct()->count('email');
    }

    private function query(EmailNotificationSchedule $schedule): Builder
    {
        return KpnEmployee::query()
            ->whereNull('deleted_at')
            // Tanpa alamat email, karyawan tidak mungkin dikirimi apa pun.
            ->whereNotNull('email')->where('email', '!=', '')
            ->when($this->clean($schedule->business_units), fn ($q, $v) => $q->whereIn('group_company', $v))
            ->when($this->unitValues($schedule), fn ($q, $v) => $q->whereIn('unit', $v))
            ->when($this->clean($schedule->companies), fn ($q, $v) => $q->whereIn('company_name', $v))
            ->when($this->clean($schedule->locations), fn ($q, $v) => $q->whereIn('office_area', $v))
            ->when($this->clean($schedule->job_levels), fn ($q, $v) => $q->whereIn('job_level', $v));
    }

    /**
     * Nilai employees.unit yang sepadan dengan Unit yang dipilih.
     *
     * Dropdown Unit memakai departments.department_name (sama seperti filter di
     * My Ideas), sedangkan employees.unit sering membawa kode dalam kurung —
     * mis. "HC Information System (CRPHC_ISD)". Karena itu satu nama unit
     * dicocokkan ke nilai mentahnya: sama persis, ATAU sama setelah keterangan
     * dalam kurung dibuang.
     *
     * Mengembalikan null bila filter Unit kosong (tanpa batasan), dan array
     * kosong bila terisi tetapi tidak ada satu pun unit karyawan yang cocok —
     * sehingga hasilnya nol penerima, bukan malah semua karyawan.
     */
    private function unitValues(EmailNotificationSchedule $schedule): ?array
    {
        $dipilih = $this->clean($schedule->units);
        if (! $dipilih) {
            return null;
        }

        $target = collect($dipilih)->map(fn ($u) => mb_strtolower(trim($u)));

        $cocok = KpnEmployee::query()
            ->whereNull('deleted_at')
            ->whereNotNull('unit')->where('unit', '!=', '')
            ->when($this->clean($schedule->business_units), fn ($q, $v) => $q->whereIn('group_company', $v))
            ->distinct()
            ->pluck('unit')
            ->filter(function ($unit) use ($target) {
                $mentah  = mb_strtolower(trim((string) $unit));
                $bersih  = mb_strtolower((string) KpnEmployee::stripUnit($unit));

                return $target->contains($mentah) || $target->contains($bersih);
            })
            ->values()
            ->all();

        // Array kosong tetap dikembalikan (bukan null) agar whereIn menghasilkan nol baris.
        return $cocok ?: ['__tidak_ada_unit_yang_cocok__'];
    }

    /** Buang nilai kosong; kembalikan null bila tidak ada isi sama sekali. */
    private function clean(?array $values): ?array
    {
        $bersih = collect($values ?? [])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $bersih ?: null;
    }
}
