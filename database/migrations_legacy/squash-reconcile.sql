-- =====================================================================
-- Rekonsiliasi SQUASH migrasi TMS (tm_system / mysql)
--
-- Jalankan SEKALI di database yang SUDAH berisi skema lama (staging &
-- produksi), SETELAH menarik commit squash ini dan SEBELUM menjalankan
-- `php artisan migrate`.
--
-- Isinya: menukar 61 baris migrasi lama di tabel `migrations` dengan 12
-- baris migrasi konsolidasi yang baru, sehingga `php artisan migrate`
-- menjadi no-op dan tidak mencoba membuat ulang tabel yang sudah ada.
--
-- Baris 2019_12_14_000001_create_personal_access_tokens_table SENGAJA
-- tidak dihapus: migrasi itu milik paket laravel/sanctum, bukan proyek ini.
--
-- JANGAN dijalankan di database kosong — di sana cukup `migrate:fresh`.
-- Backup dulu: mysqldump nama_db migrations > migrations_backup.sql
-- =====================================================================

START TRANSACTION;

SET @batch := (SELECT COALESCE(MAX(batch), 0) FROM migrations);

DELETE FROM migrations WHERE migration IN (
  '2014_10_12_000000_create_users_table',
  '2014_10_12_100000_create_password_reset_tokens_table',
  '2019_08_19_000000_create_failed_jobs_table',
  '2026_06_08_015057_add_role_to_users_table',
  '2026_07_10_020000_drop_legacy_rbac_tables',
  '2026_07_10_021131_create_permission_tables',
  '2026_07_10_030000_create_role_scope_pivots',
  '2026_07_10_040000_add_user_id_to_ideas',
  '2026_07_14_010000_create_committee_workflow_tables',
  '2026_07_15_010000_add_status_to_projects',
  '2026_07_15_020000_enhance_proposal_fields',
  '2026_07_15_030000_generalize_approval_flow',
  '2026_07_15_040000_add_completion_to_projects',
  '2026_07_17_010000_create_project_updates',
  '2026_07_17_020000_create_project_categories',
  '2026_07_17_030000_complete_partials_types_budget',
  '2026_07_17_040000_add_department_to_committee_assignments',
  '2026_07_19_010000_add_job_level_to_users',
  '2026_07_19_020000_create_idea_attachments',
  '2026_07_20_010000_add_company_location_to_ideas',
  '2026_07_31_010000_create_guidelines',
  '2026_07_31_020000_create_activity_logs',
  '2026_08_03_010000_create_sla_settings',
  '2026_08_05_010000_add_org_names_to_ideas',
  '2026_08_05_020000_add_location_company_names_to_ideas',
  '2026_08_05_030000_make_user_org_nullable',
  '2026_08_07_010000_drop_user_foreign_keys',
  '2026_08_12_000000_add_notes_to_projects_table',
  '2026_08_13_000000_sync_staging_drop_fks_and_add_columns',
  '2026_08_14_000000_add_access_flags_to_guidelines',
  '2026_08_15_000000_add_require_role_to_project_categories',
  '2026_08_16_000000_project_categories_required_roles_json',
  '2026_08_17_000000_add_status_to_sla_settings',
  '2026_08_18_000000_sla_settings_drop_unique_approval_type',
  '2026_08_20_000000_add_achievement_value_to_implementation_indicators',
  '2026_08_21_000000_widen_item_on_project_budgets',
  '2026_08_22_000000_add_attachment_to_implementation_plans',
  '2026_08_23_000000_add_budget_tier_to_committee_assignments',
  '2026_08_24_000000_shift_project_proposal_committee_layers',
  '2026_08_25_000000_add_budget_amount_to_committee_assignments',
  '2026_08_26_000000_update_committee_unique_index_for_budget',
  '2026_08_27_000000_budget_range_on_committee_assignments',
  '2026_08_28_000000_normalize_committee_budget_values',
  '2026_08_29_000000_add_actual_status_to_projects',
  '2026_08_31_120000_add_attachment_audit_to_implementation_plans',
  '2026_09_01_090000_allow_empty_idea_draft',
  '2026_09_01_100000_add_exact_value_indicator_type',
  '2026_09_02_090000_create_implementation_plan_attachments',
  '2026_09_02_100000_create_project_budget_attachments',
  '2026_09_02_110000_extend_project_updates_for_staging',
  '2026_09_02_120000_add_change_types_to_committee_assignments',
  '2026_09_03_090000_rename_revision_to_revision_required',
  '2026_09_03_100000_add_dashboard_act_as_permission',
  '2026_09_03_110000_create_email_notifications_table',
  '2026_09_03_120000_create_email_notification_schedules_table',
  '2026_09_03_130000_add_units_to_email_notification_schedules',
  '2026_09_07_090000_add_revision_and_before_to_project_updates',
  '2026_09_07_100000_add_report_view_permission',
  '2026_09_09_090000_add_shell_draft_to_projects',
  '2026_09_10_090000_add_on_behalf_of_to_approvals',
  '2026_09_11_090000_add_leadership_change_to_project_updates'
);

INSERT INTO migrations (migration, batch) VALUES
  ('2026_01_01_000001_create_framework_tables', @batch),
  ('2026_01_01_000002_create_permission_tables', @batch),
  ('2026_01_01_000003_create_organization_tables', @batch),
  ('2026_01_01_000004_create_role_scope_tables', @batch),
  ('2026_01_01_000005_create_idea_tables', @batch),
  ('2026_01_01_000006_create_project_categories_table', @batch),
  ('2026_01_01_000007_create_project_tables', @batch),
  ('2026_01_01_000008_create_project_detail_tables', @batch),
  ('2026_01_01_000009_create_project_updates_table', @batch),
  ('2026_01_01_000010_create_committee_assignments_table', @batch),
  ('2026_01_01_000011_create_configuration_tables', @batch),
  ('2026_01_01_000012_create_email_notification_tables', @batch);

COMMIT;

-- Verifikasi: `php artisan migrate:status` harus menampilkan semua Ran,
-- dan `php artisan migrate` tidak menjalankan apa pun.
