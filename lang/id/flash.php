<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flash Message
    |--------------------------------------------------------------------------
    |
    | Pesan sekali tampil sesudah redirect. Kuncinya harus sama persis dengan
    | lang/en/flash.php: kunci yang hilang jatuh ke teks Inggris tanpa error,
    | dan itu yang paling sulit terlihat.
    |
    */

    'ideas' => [
        'draft_saved' => 'Draf :id tersimpan.',
        'draft_updated' => 'Draf :id diperbarui.',
        'submitted' => 'Idea :id diajukan.',
        'draft_deleted' => 'Draf :id dihapus.',
        'approved' => 'Idea :id disetujui untuk layer Anda.',
        'approved_on_behalf' => 'Idea :id disetujui atas nama :name.',
        'rejected' => 'Idea :id ditolak.',
        'rejected_on_behalf' => 'Idea :id ditolak atas nama :name.',
        'attachment_deleted' => 'Lampiran dihapus.',
    ],

    'projects' => [
        'shell_draft_saved' => 'Project shell tersimpan sebagai draf.',
        'shell_created' => 'Project shell dibuat (:id).',
        'shell_draft_deleted' => 'Draf project shell dihapus.',
        'proposal_draft_saved' => 'Proposal Project tersimpan sebagai draf.',
        'needs_plan_indicator' => 'Minimal 1 Rencana Implementasi dan 1 Indikator Keberhasilan harus ada sebelum diajukan.',
        'weight_must_be_100' => 'Total bobot Indikator Keberhasilan harus 100% (saat ini :total%).',
        'proposal_submitted' => 'Proposal diajukan ke Project Sponsor.',
        'sponsor_approved' => 'Proposal disetujui oleh Sponsor.',
        'sponsor_revision' => 'Proposal dikembalikan ke Project Leader untuk direvisi.',
        'review_approved' => 'Keputusan tersimpan untuk :id.',
        'review_approved_on_behalf' => ':id disetujui atas nama :name.',
        'review_revision' => 'Perlu revisi: :id dikembalikan ke Project Leader.',
        'review_revision_on_behalf' => 'Perlu revisi atas nama :name: :id dikembalikan ke Project Leader.',
        'review_rejected' => 'Ditolak: :id.',
        'review_rejected_on_behalf' => 'Ditolak atas nama :name: :id.',
        'completion_needs_indicator' => 'Minimal 1 Indikator Keberhasilan harus ada sebelum mengajukan penyelesaian.',
        'completion_submitted' => 'Permintaan penyelesaian diajukan.',
        'cancel_not_allowed' => 'Project yang sudah Selesai/Dibatalkan tidak bisa dibatalkan lagi.',
        'cancelled' => 'Project dibatalkan.',
        'no_pending_change' => 'Tidak ada perubahan tertahan yang bisa diajukan.',
        'changes_submitted' => 'Diajukan untuk persetujuan: :items.',
        'saved_with_draft' => 'Tersimpan. :count field proposal ditahan sebagai draf perubahan dan baru berlaku setelah disetujui - tekan Perbarui Project untuk mengajukannya.',
        'leadership_unchanged' => 'Tidak ada perubahan pada Sponsor maupun Leader.',
        'leadership_updated' => 'Sponsor/Leader diperbarui.',
        'leadership_submitted' => 'Perubahan Sponsor/Leader diajukan untuk disetujui oleh :name.',
        'leadership_pending' => 'Perubahan Sponsor/Leader diajukan. Permintaannya menggantung sampai penyetujunya ditentukan.',
        'activity_added' => 'Aktivitas ditambahkan.',
        'activity_updated' => 'Aktivitas diperbarui.',
        'activity_removed' => 'Aktivitas dihapus.',
        'actual_updated' => 'Realisasi implementasi diperbarui.',
        'attachment_removed' => 'Lampiran dihapus.',
        'attachment_deleted' => 'Lampiran dihapus.',
        'attachments_uploaded' => ':count lampiran diunggah.',
        'indicator_added' => 'Indikator ditambahkan.',
        'indicator_updated' => 'Indikator diperbarui.',
        'indicator_removed' => 'Indikator dihapus.',
        'achievement_updated' => 'Pencapaian diperbarui.',
        'indicator_achievement_updated' => 'Pencapaian indikator diperbarui.',
        'budget_added' => 'Anggaran ditambahkan.',
        'budget_updated' => 'Anggaran diperbarui.',
        'budget_removed' => 'Item anggaran dihapus.',
        'actual_budget_updated' => 'Anggaran aktual diperbarui.',
        'members_added' => ':count anggota tim ditambahkan.',
        'member_updated' => 'Anggota tim diperbarui.',
        'member_removed' => 'Anggota tim dihapus.',
    ],

    'updates' => [
        'auto_approved' => 'Permintaan pembaruan disetujui otomatis. Silakan ubah lalu Terapkan.',
        'submitted_to' => 'Permintaan pembaruan diajukan ke :approver.',
        'approver_committee' => 'Komite',
        'approver_sponsor' => 'Sponsor',
        'approved_applied' => ':label disetujui dan diterapkan ke project.',
        'approved_forwarded' => ':label disetujui; diteruskan ke layer :layer.',
        'legacy_approved' => 'Permintaan pembaruan disetujui. Project Leader bisa mengubah lalu Terapkan.',
        'revision' => ':label dikembalikan ke Project Leader untuk direvisi.',
        'rejected' => ':label ditolak.',
        'legacy_rejected' => 'Permintaan pembaruan ditolak.',
        'applied' => 'Perubahan diterapkan (pembaruan ditutup).',
        'auto_approved_note' => 'Disetujui otomatis (diajukan oleh Sponsor).',
    ],

    'committee' => [
        'saved' => 'Penugasan komite tersimpan.',
        'deleted' => 'Penugasan komite dihapus.',
    ],

    'guidelines' => [
        'uploaded' => 'Panduan ":title" diunggah.',
        'access_updated' => 'Hak akses ":title" diperbarui.',
        'activated' => 'Panduan diaktifkan.',
        'deactivated' => 'Panduan dinonaktifkan.',
        'deleted' => 'Panduan ":title" dihapus.',
    ],

    'categories' => [
        'created' => 'Kategori :code dibuat.',
        'updated' => 'Kategori :code diperbarui.',
        'activated' => 'Kategori diaktifkan.',
        'deactivated' => 'Kategori dinonaktifkan.',
        'in_use' => 'Kategori :code masih dipakai project - tidak bisa dihapus. Arsipkan saja.',
        'deleted' => 'Kategori :code dihapus.',
    ],

    'roles' => [
        'created' => 'Peran ":name" berhasil dibuat.',
        'updated' => 'Peran ":name" berhasil diperbarui.',
        'deleted' => 'Peran ":name" dihapus.',
        'users_updated' => 'Pengguna untuk peran ":name" diperbarui.',
    ],

    'sla' => [
        'added' => 'SLA ditambahkan.',
        'updated' => 'SLA diperbarui.',
        'restored' => 'SLA dipulihkan.',
        'archived' => 'SLA diarsipkan.',
        'deleted' => 'SLA ":label" dihapus.',
    ],

    'users' => [
        'created' => 'Pengguna ":name" dibuat.',
        'updated' => 'Pengguna ":name" diperbarui.',
        'deleted' => 'Pengguna ":name" dihapus.',
        'cannot_delete_self' => 'Anda tidak bisa menghapus akun sendiri.',
    ],
];
