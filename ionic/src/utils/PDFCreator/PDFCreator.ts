import { IInvoice } from "@/interfaces/InvoiceInterfaces";
import { IReport } from "@/interfaces/ReportInterfaces";
import { RequestAPI } from "@/utils/Requests/RequestAPI";
import { jsPDF } from "jspdf";
import 'jspdf-autotable';


interface PDFCreatorOptions{
    report: IReport,
    invoices: Array<IInvoice>;
}
class PDFCreator{
    private doc: jsPDF;
    private canvasItems: Array<{
        invoice: IInvoice,
        imageBase64: string|null,
        canvas: HTMLCanvasElement,
        canvasBase64: string|null
    }>;
    private report: IReport;
    private invoices: Array<IInvoice>;
    constructor(options: PDFCreatorOptions){
        this.doc = new jsPDF();
        this.canvasItems = [];
        this.invoices = options.invoices;
        this.report = options.report;
    }


    public async create(){
        await this.loadImages();
        await this.writeOnImages();
        await this.generateTableOnPDF();
        await this.generateImagesPagesOnPDF();

        //Save PDF and download it:
        this.doc.save('a4.pdf');
        
        //Open PDF on new tab:
        window.open(this.doc.output('bloburl'), '_blank');
    }


    private generateTableOnPDF(){
        return new Promise((resolve, reject) => {

            const pageWidth = this.doc.internal.pageSize.getWidth() as unknown as number;
            this.doc.setFontSize(13).setFont('helvetica', 'bold');
            this.doc.text("MARANATHA", pageWidth / 2, 10, { align: 'center'});
            this.doc.setFontSize(10);
            this.doc.text("EXPENSE REPORT", pageWidth / 2, 17, { align: 'center'});
            this.doc.setFontSize(9).setFont('helvetica', 'normal');
            this.doc.text("Country - Peru", pageWidth / 2, 22.2, { align: 'center' });

            (this.doc as any).autoTable({
                startY: 50,
                theme: 'grid',
                headStyles: {
                    fillColor: [235, 235, 235],
                    textColor: [0, 0, 0],
                    fontStyle: 'bold',
                    lineColor: [0, 0, 0],
                    lineWidth: 0.1,
                    fontSize: 8
                },
                bodyStyles: {lineColor: [0, 0, 0], fontSize: 8},
                head: [['DATE', 'INVOICE/TICKET', 'INVOICE/TICKET DESCRIPTION', 'JOB', 'EXPENSE CODE', '', 'Total']],
                body: (() => {
                    const listRows:any = [];
                    //Generate array of 28 items:
                    Array.from(Array(28).keys()).forEach((index) => {
                        if (this.canvasItems[index]){
                            const invoice = this.canvasItems[index].invoice;
                            listRows.push([invoice.date, invoice.ticket_number, invoice.description, invoice.job_code, invoice.expense_code, index + 1, invoice.amount])
                        }else{
                            listRows.push(['', '', '', '', '', index + 1, '']);
                        }
                    })
                    return listRows;
                })(),
                tableLineColor: [0, 0, 0],
                tableLineWidth: 0.5,
            })
            resolve(this.doc);
        })
        
    }
    private generateImagesPagesOnPDF(){
        return new Promise((resolve, reject) => {
            //Add each image from this.canvasItems.canvasBase64 to a new page on this.doc:
            this.canvasItems.forEach((canvasItem) => {
                this.doc.addPage();
                this.doc.addImage(canvasItem.canvas, 'JPEG', 0, 0, 210, 297);
            })
            resolve(this.doc);
        })
    }

    private async loadImages(){
        return new Promise((resolve, reject) => {
            const promises = this.invoices.map((invoice) => {
                return new Promise((resolve, reject) => {
                    RequestAPI.getStorageInBase64('/invoices/' + invoice.image).then((imageBase64) => {
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d') as unknown as CanvasRenderingContext2D;
                        const image = new Image();
                        image.src = imageBase64;
                        image.onload = () => {
                            canvas.width = image.width;
                            canvas.height = image.height;
                            context.drawImage(image, 0, 0);

                            const canvasItem = {
                                invoice: invoice,
                                imageBase64: imageBase64,
                                canvas: canvas,
                                canvasBase64: null
                            }
                            this.canvasItems.push(canvasItem)
                            resolve(canvasItem);
                        }
                    })
                })
            })
            
            Promise.all(promises).then((canvasItems) => {
                resolve(this.canvasItems);
            })
        })
    }
    private async writeOnImages(){
        return new Promise((resolve, reject) => {
            const promises = this.canvasItems.map((canvasItem) => {
                return new Promise((resolve, reject) => {
                    let context = canvasItem.canvas.getContext('2d') as unknown as CanvasRenderingContext2D;
                    context.fillStyle = 'black';
                    context.font = 'bold 20px Arial';

                    const textsToWrite: Array<string> = [
                        `${canvasItem.invoice.description}`,
                        `Job: ${canvasItem.invoice.job_code} | Expense: ${canvasItem.invoice.expense_code}`,
                        `Date: ${canvasItem.invoice.date} | Ticket: ${canvasItem.invoice.ticket_number}`
                    ];


                    textsToWrite.reverse().forEach((text, index) => {
                        const canvasHeight = canvasItem.canvas.height - 20;
                        context.fillText(text, 10, canvasHeight - (index * 20));
                    })

                    canvasItem.canvasBase64 = canvasItem.canvas.toDataURL('image/png');
                    resolve(canvasItem);
                })
            })

            Promise.all(promises).then((canvasItems) => {
                resolve(this.canvasItems);
            })
        })
    }
}

export { PDFCreator };
export type { PDFCreatorOptions };

