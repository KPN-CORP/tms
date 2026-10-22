/** Bentuk data khusus halaman Idea. */

export interface IdeaFormValues {
    idea_name: string;
    problem: string;
    description: string;
    expected_outcome: string;
    /** BU/Unit/Company/Location dikirim sebagai NAMA (snapshot hcis), bukan FK. */
    business_unit: string;
    department: string;
    company: string;
    location: string;
}

export interface EmployeeInfo {
    employee_id: string | null;
    name: string;
    business_unit: string | null;
    department: string | null;
}

export interface IdeaAttachment {
    id: number;
    file_name: string;
    urls: { download: string; view: string; destroy: string };
}
