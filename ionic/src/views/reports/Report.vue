<template>
    <ion-page ref="page">
        <ion-header>
            <ion-toolbar>
                <ion-title>{{ report?.title }}</ion-title>
                <IonButtons slot="start">
                    <IonBackButton defaultHref="/reports"></IonBackButton>
                </IonButtons>

                <IonButtons slot="end">
                    <ion-button @click="editReport" v-if="report.status == 'Draft'">
                        <ion-icon :icon="ellipsisHorizontal"></ion-icon>
                    </ion-button>
                </IonButtons>
                <ion-progress-bar v-if="isLoading" type="indeterminate"></ion-progress-bar>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <article v-if="reportData != null">
                <ion-list>
                    <ion-item>
                        <ion-label>
                            <h1><b>{{ report.title }}</b></h1>
                            <p>{{reportType}}s</p>
                            <p>{{report.from_date}} - {{ report.to_date }}</p>
                        </ion-label>
                        <ion-chip color="danger" v-if="report.status == 'Draft'">
                            <ion-icon :icon="alertCircleOutline"></ion-icon>
                            <ion-label>{{reportStatus}}</ion-label>
                        </ion-chip>
                        <ion-chip color="success" v-if="report.status == 'Submitted'">
                            <ion-icon :icon="checkmarkCircleOutline"></ion-icon>
                            <ion-label>{{reportStatus}}</ion-label>
                        </ion-chip>
                    </ion-item>
                </ion-list>

                <article class="ion-padding"  v-if="report.status == 'Draft'" >
                    <ion-button color="success" expand="block" @click="sendReport" :disabled="invoices.length == 0">
                        <ion-label>
                            Cerrar y enviar reporte
                        </ion-label>
                        <ion-icon slot="start" :icon="send"></ion-icon>
                    </ion-button>
                </article>

                <article class="ion-padding" v-if="report.status == 'Submitted'">
                    <ion-button color="primary" expand="block" @click="downloadPdfAndExcelFiles">
                        <ion-label>
                            Descargar PDF y Excel
                        </ion-label>
                        <ion-icon slot="start" :icon="arrowDown"></ion-icon>
                    </ion-button>
                </article>

                <br>

                <ion-list-header style="font-size: 15px">{{ reportType }}s del reporte ({{ invoices.length }} / 28)</ion-list-header>
                
                <section class="ion-padding">
                    <ion-button expand="block" fill="outline" @click="addInvoice" :disabled="invoices.length == 28" v-if="report.status == 'Draft'"> 
                        <ion-icon slot="start" :icon="add"></ion-icon>
                        Añadir {{ reportType }}
                    </ion-button>
                </section>
                <ion-list>
                    <ion-item v-for="invoice in invoices" :key="invoice.id" @click="openInvoice(invoice)" button :detail="true" :disabled="report.status != 'Draft'">
                        <ion-label>
                            <h2><b>{{ invoice.description }}</b></h2>
                            <h3>{{ invoice.date }}</h3>
                            <p>{{ invoice.jobName }}</p>
                        </ion-label>
                        <ion-label slot="end">
                            <h3>S./ {{ invoice.amount }}</h3>
                        </ion-label>
                    </ion-item>
                </ion-list>

                <br>
                <article class="ion-padding" v-if="report.status != 'Draft'">
                    <ion-button color="danger" expand="block" fill="outline" @click="undoSendReport">
                        <ion-label>
                            Cancelar envío y reaperturar reporte
                        </ion-label>
                        <ion-icon slot="start" :icon="lockOpen"></ion-icon>
                    </ion-button>
                </article>
            </article>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle,IonChip, IonContent, IonIcon, IonListHeader, IonButton, IonList, IonItem, IonLabel, IonProgressBar, modalController, IonBackButton, IonButtons, actionSheetController, toastController, alertController } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { computed, ref } from 'vue';
import { add, addOutline, pencilOutline, send, trashBinOutline, lockClosed, ellipsisHorizontal, closeCircleOutline, closeOutline, arrowDown, lockOpen, alertCircleOutline, lockOpenSharp, checkmarkCircleOutline } from 'ionicons/icons';
import { IReport } from '../../interfaces/ReportInterfaces';

import { useRoute } from 'vue-router';
import { IInvoice } from '@/interfaces/InvoiceInterfaces';
import { DateTime } from 'luxon';
import { JobsList } from '@/utils/JobsAndProjects/JobsAndProjects';
import { PDFCreator } from '@/utils/PDFCreator/PDFCreator';
import { Dialog } from '@/utils/Dialog/Dialog';
import EditReport from '@/dialogs/EditReport/EditReport.vue';
import router from '@/router';

import NewInvoice from '@/dialogs/NewInvoice/NewInvoice.vue';
import { Session } from '@/utils/Session/Session';


const reportData = ref<IReport|null>(null);
const invoicesData = ref<Array<IInvoice>>([]);
const isLoading = ref<boolean>(true);
const reportId = ref<string|null>(null);
const page = ref<HTMLElement|null>(null);


const reportType = computed(() => {
    return report.value.type === "Bill" ? "Boleta" : "Factura";
})
const reportStatus = computed(() => {
    return report.value.status === "Draft" ? "Pendiente" : "Enviado";
})
const invoices = computed(() => {
    return invoicesData.value.map((invoice) => {
        return {
            ...invoice,
            date: DateTime.fromISO(invoice.date).toLocaleString(DateTime.DATE_MED),
            jobName: JobsList.find((job) => job.code === invoice.job_code)?.name
        }
    })
});
const report = computed(() => {
    return {
        ...reportData.value,
        from_date: DateTime.fromISO(reportData.value?.from_date as unknown as string).toLocaleString(DateTime.DATE_MED),
        to_date: DateTime.fromISO(reportData.value?.to_date as unknown as string).toLocaleString(DateTime.DATE_MED),
        type: reportData.value?.type,
        status: reportData.value?.status
    }
});

const addInvoice = async () => {
    Dialog.show(NewInvoice, {
        props:{
            reportId: reportId.value,
            type: report.value.type,
            autoShowCamera: false
        },
        onLoaded($this) {
            $this.on('created', (event:any) => {
                loadReportInvoices();
            })
        },
        modalControllerOptions: {
            presentingElement: page,
            showBackdrop: true,
        }
    })
}

const editReport = async () => {
    Dialog.show(EditReport, {
        props: {
            reportId: reportId.value
        },
        modalControllerOptions: {
            presentingElement: page as unknown as HTMLElement,
            swipeToClose: true
        },
        onLoaded($this) {
            $this.on('updated', (event:any) => {
                initialize();
            })
            $this.on('deleted', (event:any) => {
                router.back();
            })
        }
    })
}
const deleteInvoice = async (invoice:IInvoice) => {
    await RequestAPI.delete(`/invoices/${invoice.id}`);
    loadReportInvoices();
    toastController.create({
        message: reportType + ' eliminada con exito!',
        duration: 2000
    }).then((toast) => {
        toast.present();
    })
}
const openInvoice = async (invoice:IInvoice) => {
    await actionSheetController.create({
        header: 'Opciones',
        buttons: [
            {
                text: 'Eliminar ' + reportType.value,
                role: 'destructive',
                handler: () => {
                    deleteInvoice(invoice)
                }
            },
            {
                text: 'Cancelar',
                role: 'cancel',
                handler: () => {
                    console.log('Cancel clicked');
                }
            }
        ]
    }).then((actionSheet) => {
        actionSheet.present();
    })
}
const loadReport = async () => {
    const routeParams = useRoute();
    if (reportId.value === null){
        reportId.value = routeParams.params.id as string;
    }
    const reportFetched = await RequestAPI.get(`/reports/${reportId.value}`);

    isLoading.value = false;
    reportData.value = reportFetched;
};
const loadReportInvoices = async () => {
    const invoicesFetched = await RequestAPI.get(`/reports/${reportId.value}/invoices`);
    invoicesData.value = invoicesFetched;
}


const createExportPDF = async () => {
    const instance = new PDFCreator({
        report: reportData.value as IReport,
        invoices: invoices.value,
        textContents: {
            submittedBy: (await Session.getCurrentSession())?.name() as unknown as string,
            fromDateToDate: `del ${report.value.from_date}  hasta el ${report.value.to_date}`,
        }
    });
    const base64String = (await instance.create()).split('data:application/pdf;filename=generated.pdf;base64,')[1];
    return base64String;
}

const undoSendReport = async () => {
    isLoading.value = true;

    const pdfResponse = await RequestAPI.patch(`/reports/${reportId.value}`, {
        status: 'Draft'
    })
    isLoading.value = false;
    toastController.create({
        message: 'Reporte reaperturado con exito!',
        duration: 2000
    }).then((toast) => {
        toast.present();
    })
    initialize();
}
const sendReport = async () => {
    isLoading.value = true;

    const pdfBase64 = await createExportPDF();

    const pdfResponse = await RequestAPI.post(`/reports/${reportId.value}/pdf-upload`, {
        pdf: pdfBase64
    }) as unknown as {message: string, image: {id: string, url: string}};

    const updateReportResponse = await RequestAPI.patch(`/reports/${reportId.value}`, {
        status: 'Submitted'
    })
    isLoading.value = false;

    const alert = await alertController.create({
        header: '¡Éxito!',
        message: 'El reporte ha sido enviado con exito. El moderador lo revisará y lo aprobará en breve.',
        buttons: [
            {
                text: 'OK',
                role: 'cancel'
            }
        ]
    })
    alert.present();
    initialize();
}

const downloadPdfAndExcelFiles = async () => {
    const pdfDownloadUrl = `${RequestAPI.variables.rootUrl}/reports/${reportId.value}/pdf-download`;
    const excelDownloadUrl = `${RequestAPI.variables.rootUrl}/reports/${reportId.value}/excel-download`;

    window.open(pdfDownloadUrl);
    window.open(excelDownloadUrl);
}

const initialize = async () => {
    await loadReport();
    await loadReportInvoices();
}
initialize();
</script>
