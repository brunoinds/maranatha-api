enum EReportStatus {
    Draft = 'Draft',
    Submitted = 'Submitted'
}
interface IReport{
    id: number;
    created_at: string;
    updated_at: string;
    user_id: number;
    title: string;
    from_date: string;
    to_date: string;
    project_code: string;
    status: EReportStatus;
    exported_pdf: string|null;
}

export { EReportStatus };
export type { IReport };

