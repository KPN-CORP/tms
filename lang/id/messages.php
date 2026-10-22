<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pesan Aplikasi
    |--------------------------------------------------------------------------
    |
    | Teks yang dibuat server di luar jalur flash/validasi: alasan abort HTTP,
    | dan sedikit label yang dihitung controller alih-alih Vue.
    |
    */

    'abort' => [
        'idea_not_approved' => 'Idea belum disetujui.',
        'only_draft_editable' => 'Hanya idea berstatus draf yang bisa diubah.',
        'committee_only' => 'Halaman ini hanya untuk anggota komite.',
        'idea_layer_committee_only' => 'Hanya komite di layer aktif yang boleh memutuskan idea ini.',
        'project_layer_committee_only' => 'Hanya komite di layer aktif yang boleh memutuskan project ini.',
        'layer_unassigned' => 'Layer aktif belum punya komite yang ditugaskan.',
        'only_last_layer_creates_project' => 'Hanya layer komite terakhir yang boleh membuat project.',
        'no_shell_access' => 'Anda tidak punya akses ke project shell ini.',
        'revision_only_committee_review' => 'Perlu Revisi hanya berlaku untuk proposal yang sedang direview komite.',
        'only_assigned_approver' => 'Hanya penyetuju yang ditugaskan yang boleh memutuskan permintaan perubahan ini.',
        'no_approver_yet' => 'Permintaan ini belum punya penyetuju; statusnya menggantung sampai penyetujunya ditentukan.',
        'only_leader_submits_changes' => 'Hanya Project Leader dari project yang sedang berjalan yang boleh mengajukan perubahan.',
        'only_leader_applies_update' => 'Hanya Project Leader yang boleh menerapkan pembaruan yang sudah disetujui.',
        'only_team_requests_update' => 'Hanya anggota tim project yang sedang berjalan yang boleh mengajukan pembaruan.',
        'only_team_uploads' => 'Hanya anggota tim project yang boleh mengunggah lampiran.',
        'only_leader_or_team_edits' => 'Hanya Project Leader (saat draf/revisi) atau anggota tim selama project berjalan yang boleh mengubah ini.',
        'only_leader_or_admin_adds_members' => 'Hanya Project Leader atau administrator yang boleh menambah anggota tim.',
        'only_leader_or_admin_edits_members' => 'Hanya Project Leader atau administrator yang boleh mengubah anggota tim.',
        'only_leader_or_admin_removes_members' => 'Hanya Project Leader atau administrator yang boleh menghapus anggota tim.',
        'cancel_not_allowed' => 'Hanya Project Leader, Sponsor, layer komite terakhir dari idea, atau Super Admin yang boleh membatalkan.',
        'leadership_not_allowed' => 'Anda tidak diizinkan mengubah Project Sponsor atau Leader.',
        'file_not_found' => 'Berkas tidak ditemukan.',
        'no_attachment' => 'Tidak ada lampiran.',
    ],

    /*
    | Jenis permintaan perubahan, untuk flash message yang ditulis server. Sisi
    | Vue punya salinannya sendiri (locales/*.ts, `changeTypes`) karena label di
    | sana menetap di layar dan harus ikut berubah tanpa muat ulang.
    */
    'labels' => [
        'all_units_bu_wide' => 'Semua Unit (seluruh BU)',
        'all_units' => 'Semua Unit',
    ],

    'change_types' => [
        'team_change' => 'Perubahan Tim',
        'plan_indicator_change' => 'Perubahan Rencana & Indikator',
        'budget_change' => 'Perubahan Anggaran',
        'leadership_change' => 'Perubahan Project Sponsor & Leader',
    ],

    'budget' => [
        'empty_editable' => 'Belum ada anggaran.',
        'empty_final' => 'Tidak ada anggaran yang diajukan.',
    ],

    'validation' => [
        'concurrent_edit_by' => 'Data ini diubah oleh :name pada :time. Muat ulang halaman lalu terapkan kembali perubahan Anda supaya perubahannya tidak tertimpa.',
        'concurrent_edit' => 'Data ini diubah orang lain saat Anda sedang menyunting. Muat ulang halaman lalu terapkan kembali perubahan Anda.',
        'team_smaller_than_roles' => 'Maks. Orang dalam Tim (:max) tidak boleh kurang dari total Peran Wajib (:total).',
        'dev_login_invalid_key' => 'Kunci developer login tidak valid.',
        'user_not_found' => 'Pengguna tidak ditemukan.',
        'import_mimes' => 'Berkas harus .xlsx atau .csv. Berkas .xls lama tidak didukung - simpan ulang sebagai .xlsx.',
        'import_max' => 'Ukuran berkas tidak boleh lebih dari 2 MB.',
        'member_not_found' => 'Nama yang dipilih tidak ditemukan di direktori karyawan.',
    ],
];
