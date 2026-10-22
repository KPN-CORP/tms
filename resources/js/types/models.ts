/**
 * Bentuk data yang dikirim controller ke halaman Inertia.
 *
 * Berkas ini adalah kontrak antara PHP dan Vue: kalau payload controller
 * berubah tapi tipe di sini tidak, `npm run type-check` yang akan menegur --
 * bukan pengguna di produksi.
 */

/* ---- Status ------------------------------------------------------------- */

/** Sumber kebenaran label ada di App\Models\Project::STATUS_BADGES. */
export const PROJECT_STATUSES = [
    'draft',
    'submitted',
    'revision_required',
    'committee_review',
    'approved',
    'rejected',
    'ongoing',
    'delayed',
    'completion_review',
    'completed',
    'cancelled',
] as const;

export type ProjectStatus = (typeof PROJECT_STATUSES)[number];

/** Status Project Shell -- App\Models\Project::SHELL_STATUS_BADGES. */
export const SHELL_STATUSES = ['not_assigned', 'draft', 'assigned', 'cancelled'] as const;

export type ShellStatus = (typeof SHELL_STATUSES)[number];

export const IDEA_STATUSES = [
    'draft',
    'submitted',
    'review',
    'approved',
    'rejected',
    'project_created',
] as const;

export type IdeaStatus = (typeof IDEA_STATUSES)[number];

/** App\Models\CommitteeAssignment::TYPES. */
export type ApprovalType =
    | 'idea'
    | 'project_proposal'
    | 'team_change'
    | 'plan_indicator_change'
    | 'budget_change'
    | 'project_completion';

/** App\Models\ProjectUpdate::CHANGE_TYPES, termasuk yang tidak dikonfigurasi. */
export type ChangeType =
    | 'team_change'
    | 'plan_indicator_change'
    | 'budget_change'
    | 'leadership_change'
    | 'budget'
    | 'planning'
    | 'team'
    | 'general';

/* ---- Entitas ------------------------------------------------------------ */

export interface OrgRef {
    id: number;
    name: string;
}

export interface PersonRef {
    id: number;
    name: string;
    email?: string | null;
}

export interface Idea {
    id: number;
    idea_id: string;
    title: string;
    status: IdeaStatus;
    status_label: string;
    business_unit_name: string | null;
    department_name: string | null;
    submitter_name: string | null;
    current_layer: number | null;
    submitted_at: string | null;
    modified_at: string | null;
    created_at: string | null;
}

export interface Project {
    id: number;
    project_id: string;
    title: string;
    status: ProjectStatus;
    status_label: string;
    category: string | null;
    business_unit_name: string | null;
    department_name: string | null;
    leader_name: string | null;
    sponsor_name: string | null;
    total_budget: number | null;
    start_date: string | null;
    end_date: string | null;
    progress: number | null;
    created_at: string | null;
}

/* ---- Pembungkus daftar -------------------------------------------------- */

/** Bentuk paginator Laravel sesudah di-`toArray()`. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

/** Parameter daftar yang dipakai Concerns\HasListQuery. */
export interface ListQuery {
    q?: string;
    sort?: string;
    dir?: 'asc' | 'desc';
    per_page?: number | string;
    business_unit_id?: number | string;
    department_id?: number | string;
    status?: string;
    [key: string]: unknown;
}

/** Opsi untuk <SelectField> / <FilterBar>. */
export interface SelectOption {
    value: string | number;
    label: string;
    /** Dipakai dropdown bertingkat (Unit mengikuti Business Unit). */
    parent?: string | number | null;
}

/* ---- Badge SLA ---------------------------------------------------------- */

export type SlaState = 'on_time' | 'due_soon' | 'overdue';

export interface SlaBadge {
    state: SlaState;
    label: string;
    due_at?: string | null;
}
