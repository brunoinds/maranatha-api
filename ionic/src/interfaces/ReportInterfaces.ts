enum EReportStatus {
    Draft = 'Draft',
    Submitted = 'Submitted'
}
enum EReportType {
    Bill = 'Bill',
    Facture = 'Facture'
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
    type: EReportType;
}

export { EReportStatus, EReportType };
export type { IReport };

