<?php

namespace App\Http\Controllers;

use App\Models\EmailNotificationSchedule;
use App\Models\SlaSetting;
use App\Services\Notification\EmailRecipientResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin Setting > SLA > Email Notifications.
 *
 * Form pesan notifikasi per SLA: pilih SLA yang aktif, beri judul, batasi
 * sasaran lewat filter organisasi, tentukan rentang tanggal & hari pengulangan,
 * lalu tulis isi pesannya.
 */
class EmailNotificationController extends Controller
{
    /** Batas baris penerima yang dikirim ke layar konfirmasi (jumlah total tetap akurat). */
    private const MAX_DAFTAR = 500;

    public function index(Request $request)
    {
        return view('admin.email-notification.index', [
            'schedule' => $request->filled('edit')
                ? EmailNotificationSchedule::findOrFail($request->integer('edit'))
                : null,
            'slaOptions' => $this->slaOptions(),
            'days'       => EmailNotificationSchedule::DAYS,
        ]);
    }

    /**
     * Pratinjau penerima: jumlah + contoh nama, dihitung dari filter yang sedang
     * dipilih di form (belum tersimpan). Dipanggil lewat AJAX setiap filter berubah
     * supaya admin tahu email akan menyasar siapa sebelum menekan Submit.
     */
    public function recipients(Request $request, EmailRecipientResolver $resolver)
    {
        $draft = new EmailNotificationSchedule([
            'business_units' => $request->input('business_units', []),
            'units'          => $request->input('units', []),
            'companies'      => $request->input('companies', []),
            'locations'      => $request->input('locations', []),
            'job_levels'     => $request->input('job_levels', []),
        ]);

        // limit kecil dipakai panel ringkas (5 contoh); daftar lengkap saat Submit
        // memakai limit besar, dibatasi MAX_DAFTAR agar respons tidak membengkak.
        $limit = (int) $request->input('limit', 5);
        $limit = $limit > 0 ? min($limit, self::MAX_DAFTAR) : 5;

        $total = $resolver->countFor($draft);

        return response()->json([
            'total'      => $total,
            'limit'      => $limit,
            'truncated'  => $total > $limit,
            'sample'     => $resolver->employeesFor($draft, 5)
                ->map(fn ($e) => trim($e->fullname . ' - ' . $e->email))
                ->values(),
            'recipients' => $resolver->employeesFor($draft, $limit)->map(fn ($e) => [
                'name'      => $e->fullname,
                'email'     => $e->email,
                'unit'      => $e->unit,
                'job_level' => $e->job_level,
                'bu'        => $e->group_company,
            ])->values(),
            'unfiltered' => ! array_filter([
                $request->input('business_units'), $request->input('units'),
                $request->input('companies'), $request->input('locations'),
                $request->input('job_levels'),
            ]),
        ]);
    }

    public function store(Request $request, EmailRecipientResolver $resolver)
    {
        $schedule = EmailNotificationSchedule::create($this->validated($request));

        return redirect()->route('admin.email-notifications.index')
            ->with($this->flashTerkirim($schedule, $resolver));
    }

    public function update(Request $request, EmailNotificationSchedule $notification, EmailRecipientResolver $resolver)
    {
        $notification->update($this->validated($request));

        return redirect()->route('admin.email-notifications.index')
            ->with($this->flashTerkirim($notification, $resolver));
    }

    /**
     * Pesan untuk dialog setelah Confirm & Submit.
     *
     * PENTING: di titik ini TIDAK ada email yang dikirim — jadwal hanya disimpan
     * (belum ada Mail/Job/command yang membaca email_notification_schedules).
     * Teks "sent" dipakai atas permintaan agar admin sudah mendapat umpan balik
     * seperti nanti saat pengiriman benar-benar aktif; ubah dua string di bawah
     * ini saja bila kelak ingin kembali memakai kalimat "saved".
     *
     * Jumlah penerima dihitung ulang dari filter yang TERSIMPAN, jadi angkanya
     * pasti sama dengan yang tampil di dialog Confirm Recipients.
     */
    private function flashTerkirim(EmailNotificationSchedule $schedule, EmailRecipientResolver $resolver): array
    {
        $jumlah = $resolver->countFor($schedule);

        return [
            'sent_count' => $jumlah,
            'success'    => $jumlah > 0
                ? "Email successfully sent to {$jumlah} employee(s)."
                : 'Saved, but no employee matches these filters — so nobody received this email.',
        ];
    }

    /**
     * SLA yang boleh dipilih = hanya yang statusnya Active, sesuai permintaan:
     * dropdown menampilkan SLA aktif saja.
     */
    private function slaOptions(): \Illuminate\Support\Collection
    {
        return SlaSetting::where('is_active', true)
            ->orderBy('approval_type')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->id => trim(
                $s->typeLabel()
                . ($s->status ? ' — ' . $s->statusLabel() : '')
                . " ({$s->days} days)"
            )]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'sla_setting_id'   => ['required', Rule::in($this->slaOptions()->keys()->all())],
            'title'            => ['required', 'string', 'max:255'],
            'business_units'   => ['nullable', 'array'],
            'business_units.*' => ['string', 'max:255'],
            'units'            => ['nullable', 'array'],
            'units.*'          => ['string', 'max:255'],
            'companies'        => ['nullable', 'array'],
            'companies.*'      => ['string', 'max:255'],
            'locations'        => ['nullable', 'array'],
            'locations.*'      => ['string', 'max:255'],
            'job_levels'       => ['nullable', 'array'],
            'job_levels.*'     => ['string', 'max:255'],
            'start_date'       => ['nullable', 'date'],
            // Tanggal mulai tidak boleh melewati tanggal selesai (aturan yang sama
            // seperti Planned/Actual date di Project).
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'attach_detail'    => ['nullable', 'boolean'],
            'repeat_days'      => ['nullable', 'array'],
            'repeat_days.*'    => [Rule::in(array_keys(EmailNotificationSchedule::DAYS))],
            'message'          => ['nullable', 'string', 'max:20000'],
        ], [
            'sla_setting_id.required' => 'Please choose an active SLA.',
            'end_date.after_or_equal' => 'End Date cannot be earlier than Start Date.',
        ]);

        $data['attach_detail'] = $request->boolean('attach_detail');
        foreach (['business_units', 'units', 'companies', 'locations', 'job_levels', 'repeat_days'] as $f) {
            $data[$f] = array_values(array_unique($data[$f] ?? []));
        }

        return $data;
    }
}
