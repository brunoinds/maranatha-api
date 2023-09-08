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
            this.doc.text("Country - Peru", pageWidth / 2, 24, { align: 'center' });
            
            this.doc.setFontSize(8).setFont('helvetica', 'normal');
            this.doc.text("Report Dates: ", (pageWidth / 2) - 22, 29, { align: 'center' });
            this.doc.text("Submitted by: ", (pageWidth / 2) - 22.05, 34, { align: 'center' });
            this.doc.text("Job: ", (pageWidth / 2) - 16, 39, { align: 'center' });


            this.doc.text("del 7 al 14 de julio de 2023", (pageWidth / 2) - 12, 29, { align: 'left' });
            this.doc.text("del 7 al 14 de julio de 2023", (pageWidth / 2) - 12, 34, { align: 'left' });
            this.doc.text("del 7 al 14 de julio de 2023", (pageWidth / 2) - 12, 39, { align: 'left' });


            (this.doc as any).autoTable({
                startY: 45,
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
                head: [['DATE', 'INVOICE/TICKET', 'INVOICE/TICKET DESCRIPTION', 'JOB', 'EXPENSE CODE', '#', 'TOTAL'].map((item) => {
                    return {content: item, styles: { valign: 'middle', halign: 'center' }}
                })],
                body: (() => {
                    const listRows:any = [];
                    //Generate array of 28 items:
                    Array.from(Array(28).keys()).forEach((index) => {
                        if (this.canvasItems[index]){
                            const invoice = this.canvasItems[index].invoice;
                            listRows.push([invoice.date,invoice.ticket_number, invoice.description, invoice.job_code, invoice.expense_code, index + 1, "S/ " + invoice.amount.toFixed(2)].map((item, i) => {
                                if (i == 2){
                                    return {content: item, styles: { valign: 'middle', halign: 'left' }}
                                }else{
                                    return {content: item, styles: { valign: 'middle', halign: 'center' }}
                                }
                            }))
                        }else{
                            listRows.push(['', '', '', '', '', index + 1, ''].map((item) => {
                                return {content: item, styles: { valign: 'middle', halign: 'center' }}
                            }));
                        }
                    })
                    return listRows;
                })(),
                foot: [
                    [
                        
                        {
                            content: 'Total',
                            dataKey: 'total-amount-label',
                            colSpan: 6,
                            styles: {
                                fillColor: [255, 255, 255],
                                textColor: 'black',
                                lineColor: 'black',
                                lineWidth: 0.1,
                                halign: 'right'
                            },
                        },
                        {
                            content: 'S/ ' + (() => {
                                let accumulator = 0;
                                this.invoices.forEach((invoice) => {
                                    accumulator += invoice.amount;
                                })
                                return accumulator.toFixed(2);
                            })(),
                            dataKey: 'total-amount-value',
                            styles: {
                                fillColor: [255, 255, 255],
                                textColor: 'black',
                                lineColor: 'black',
                                lineWidth: 0.1,
                                valign: 'middle', 
                                halign: 'right'
                            },
                        },
                    ],
                ],
                tableLineColor: [0, 0, 0],
                tableLineWidth: 0.5,
                didDrawPage: (data: any) => {
                    return;
                    setTimeout(() => {
                        const tableHeight = data.table.finalY;
                        //this.doc draw a rectangle below the table:
                        this.doc.setDrawColor(0, 0, 0);
                        this.doc.setFillColor(235, 235, 235);
                        console.log(tableHeight)
                        this.doc.rect(this.doc.internal.pageSize.getWidth() - 50, tableHeight + 5, pageWidth, 10, "F");
                        //this.doc draw a rectangle above the table:

                    }, 500)
                }
            })
            resolve(this.doc);

        })
        
    }
    private generateImagesPagesOnPDF(){
        return new Promise((resolve, reject) => {
            //Add each image from this.canvasItems.canvasBase64 to a new page on this.doc:
            this.canvasItems.forEach((canvasItem) => {
                this.doc.addPage();


                //If canvas is portrait, fit it to page:
                if (canvasItem.canvas.height > canvasItem.canvas.width){
                    //Fit canvas to page:
                    const pageWidth = this.doc.internal.pageSize.getWidth() as unknown as number;
                    const pageHeight = this.doc.internal.pageSize.getHeight() as unknown as number;
                    const canvasRatio = canvasItem.canvas.width / canvasItem.canvas.height;
                    const pageRatio = pageWidth / pageHeight;
                    if (canvasRatio > pageRatio){
                        //Fit canvas to page width:
                        const newCanvasHeight = canvasItem.canvas.height * (pageWidth / canvasItem.canvas.width);
                        this.doc.addImage(canvasItem.canvas, 'JPEG', 0, (pageHeight - newCanvasHeight) / 2, pageWidth, newCanvasHeight);
                    }else{
                        //Fit canvas to page height:
                        const newCanvasWidth = canvasItem.canvas.width * (pageHeight / canvasItem.canvas.height);
                        this.doc.addImage(canvasItem.canvas, 'JPEG', (pageWidth - newCanvasWidth) / 2, 0, newCanvasWidth, pageHeight);
                    }
                }else if (canvasItem.canvas.height <= canvasItem.canvas.width){
                    //Fit canvas to page:
                    const pageWidth = this.doc.internal.pageSize.getWidth() as unknown as number;
                    const pageHeight = this.doc.internal.pageSize.getHeight() as unknown as number;
                    const canvasRatio = canvasItem.canvas.width / canvasItem.canvas.height;
                    const pageRatio = pageWidth / pageHeight;
                    if (canvasRatio > pageRatio){
                        //Fit canvas to page width:
                        const newCanvasHeight = canvasItem.canvas.height * (pageWidth / canvasItem.canvas.width);
                        this.doc.addImage(canvasItem.canvas, 'JPEG', 0, (pageHeight - newCanvasHeight) / 2, pageWidth, newCanvasHeight);
                    }else{
                        //Fit canvas to page height:
                        const newCanvasWidth = canvasItem.canvas.width * (pageHeight / canvasItem.canvas.height);
                        this.doc.addImage(canvasItem.canvas, 'JPEG', (pageWidth - newCanvasWidth) / 2, 0, newCanvasWidth, pageHeight);
                    }
                }
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

                        const drawStroked = (text:string, x:number, y:number) => {
                            context.strokeStyle = 'black';
                            context.lineWidth = 4;
                            context.strokeText(text, x, y);
                            context.fillStyle = 'yellow';
                            context.fillText(text, x, y);
                        }
                        
                        
                        drawStroked(text, 10, canvasHeight - (index * 20));
                        //context.fillText(text, 10, canvasHeight - (index * 20));
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

