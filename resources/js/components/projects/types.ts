/**
 * Bentuk payload halaman Project Detail.
 *
 * Nama field sengaja sama dengan kolom database dan dengan nama input yang
 * diterima ProjectController, supaya form Vue bisa dikirim apa adanya ke
 * endpoint yang sudah ada.
 */

import type { ProjectStatus, SelectOption } from '@/types/models';

/** Lampiran satu baris (plan / budget / project). */
export interface RowAttachment {
    id: number;
    file_name: string;
    /** Buka di tab baru; unduhan menembak controller langsung. */
    view_url: string;
    destroy_url: string;
}

/** Nilai yang menunggu persetujuan untuk satu baris, dari ProjectChangeStagingService. */
export interface PendingChange {
    status: 'pending' | 'draft' | string;
    fields: Record<string, unknown>;
}

export interface TeamMember {
    id: number;
    user_id: number | null;
    name: string | null;
    role: string | null;
    version: string | null;
    urls: { update: string; destroy: string };
}

export interface ImplementationPlanRow {
    id: number;
    activity: string | null;
    planning_start: string | null;
    planning_end: string | null;
    actual_start: string | null;
    actual_end: string | null;
    actual_days: number | null;
    pic_user_ids: number[];
    pic_names: string;
    remarks: string | null;
    status_label: string;
    attachments: RowAttachment[];
    pending: PendingChange | null;
    version: string | null;
    urls: { update: string; destroy: string };
}

export interface IndicatorRow {
    id: number;
    indicator: string | null;
    description: string | null;
    baseline: number | string | null;
    achievement_value: number | string | null;
    achievement: number | string | null;
    uom: string | null;
    weightage: number | string | null;
    type: string | null;
    improvement: number | null;
    version: string | null;
    urls: { update: string; destroy: string };
}

export interface BudgetRow {
    id: number;
    item: string | null;
    qty: number | string | null;
    uom: string | null;
    unit_price: number | string | null;
    planned_total: number;
    actual_qty: number | string | null;
    actual_price: number | string | null;
    actual_cost: number | null;
    attachments: RowAttachment[];
    version: string | null;
    urls: { update: string; destroy: string };
}

export interface ProjectAttachmentRow {
    id: number;
    file_name: string;
    file_size: number | null;
    /** null untuk tipe yang tidak bisa dibuka di browser. */
    view_url: string | null;
    download_url: string;
    destroy_url: string | null;
}

/** Satu field dalam perbandingan before/after sebuah change request. */
export interface DiffField {
    row: string;
    label: string;
    field: string;
    before: unknown;
    after: unknown;
}

export interface ChangeReview {
    id: number;
    /** Kunci terjemahan; label di bawahnya adalah teks Inggris cadangan. */
    change_type: string;
    label: string;
    requester: string | null;
    requested_at: string | null;
    approver_role: string | null;
    current_layer: number | null;
    review_note: string | null;
    /** Nama penilai yang diwakili; null bila ia penilainya sendiri. */
    on_behalf_of: string | null;
    diff: DiffField[];
    urls: { approve: string; revision: string; reject: string };
}

export interface ApprovalRow {
    id: number;
    layer: number;
    decision: string;
    note: string | null;
    decided_by: string | null;
    on_behalf_of: string | null;
    created_at: string | null;
}

export interface StatusLogRow {
    id: number;
    old_status: string | null;
    new_status: string;
    remarks: string | null;
    changed_by: string | null;
    created_at: string | null;
}

export interface ProjectDetail {
    id: number;
    project_id: string;
    project_name: string | null;
    project_category: string | null;
    project_scope: string | null;
    expected_outcome: string | null;
    project_summary: string | null;
    status: ProjectStatus;
    /** Snapshot status untuk badge; di fase proposal tidak mengikuti eksekusi. */
    badge_status: ProjectStatus;
    badge_label: string;
    current_layer: number | null;
    created_at: string | null;
    leader_id: number | null;
    leader_name: string | null;
    sponsor_id: number | null;
    sponsor_name: string | null;
    category_code: string | null;
}

export interface IdeaSummary {
    idea_id: string;
    idea_name: string | null;
    problem: string | null;
    submitter: string | null;
    business_unit_name: string | null;
    department_name: string | null;
}

/** Gabungan izin yang menentukan apa yang boleh dilakukan di halaman ini. */
export interface ProjectAbilities {
    canEdit: boolean;
    viewOnly: boolean;
    isLeader: boolean;
    isSponsor: boolean;
    isReviewer: boolean;
    canSubmitCompletion: boolean;
    canCancel: boolean;
    canUploadAttachment: boolean;
    canEditRow: boolean;
    canDeleteRow: boolean;
    canSubmitChanges: boolean;
    canEditTeam: boolean;
    proposalReadOnly: boolean;
}

export interface UomGroup {
    label: string;
    options: SelectOption[];
}

/** Slot role yang masih kurang untuk kategori project ini. */
export interface RoleSlot {
    role: string;
    remaining: number;
}

/** URL yang dipakai halaman Project Detail; ditulis eksplisit agar tidak ada kunci yang terlewat. */
export interface ShowUrls {
    index: string;
    report: string;
    userSearch: string;
    leadership: string;
    membersStore: string;
    plansStore: string;
    indicatorsStore: string;
    budgetsStore: string;
    attachmentsStore: string;
    draft: string;
    submit: string;
    submitChanges: string;
    cancel: string;
    sponsorApprove: string;
    sponsorRevision: string;
    reviewApprove: string;
    reviewRevision: string;
    reviewReject: string;
}
