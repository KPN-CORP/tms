import type { IdeaStatus, ProjectStatus, ShellStatus } from '@/types/models';

/**
 * Status -> nada warna semantik.
 *
 * LABEL tetap milik PHP (Project::STATUS_BADGES / SHELL_STATUS_BADGES); server
 * mengirimkannya sebagai `status_label`. Yang ada di sini hanya pemetaan WARNA,
 * supaya kelas Tailwind tidak lagi ditulis di dalam PHP dan tema gelap ikut
 * bekerja sendiri lewat token.
 *
 * Status yang tidak dikenal jatuh ke 'neutral' -- sama seperti fallback abu-abu
 * di model.
 */

export type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'danger' | 'brand' | 'accent';

const PROJECT_TONES: Record<ProjectStatus, Tone> = {
    draft: 'neutral',
    submitted: 'info',
    revision_required: 'warning',
    committee_review: 'warning',
    approved: 'success',
    rejected: 'danger',
    ongoing: 'info',
    delayed: 'warning',
    completion_review: 'accent',
    completed: 'success',
    cancelled: 'danger',
};

const SHELL_TONES: Record<ShellStatus, Tone> = {
    not_assigned: 'neutral',
    draft: 'warning',
    assigned: 'success',
    cancelled: 'danger',
};

const IDEA_TONES: Record<IdeaStatus, Tone> = {
    draft: 'neutral',
    submitted: 'info',
    review: 'warning',
    approved: 'success',
    rejected: 'danger',
    project_created: 'brand',
};

export function projectTone(status: string | null | undefined): Tone {
    return PROJECT_TONES[status as ProjectStatus] ?? 'neutral';
}

export function shellTone(status: string | null | undefined): Tone {
    return SHELL_TONES[status as ShellStatus] ?? 'neutral';
}

export function ideaTone(status: string | null | undefined): Tone {
    return IDEA_TONES[status as IdeaStatus] ?? 'neutral';
}

/** Nada untuk badge SLA (SlaService: informasi saja, tidak mengirim apa pun). */
export function slaTone(state: string | null | undefined): Tone {
    if (state === 'overdue') {
        return 'danger';
    }

    if (state === 'due_soon') {
        return 'warning';
    }

    return 'success';
}
