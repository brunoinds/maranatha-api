<template>
    <ion-page>
        <ion-header>
            <ion-toolbar>
                <ion-title>{{ report?.title }}</ion-title>
                <ion-progress-bar v-if="isLoading" type="indeterminate"></ion-progress-bar>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-button expand="block" @click="addInvoice">Añadir Boleta o Factura</ion-button>

            <article v-if="report != null">
                <ion-list>
                    <ion-item>
                        <ion-label>
                            <h1>{{ report.title }}</h1>
                            <h3>{{ report.status }}</h3>
                        </ion-label>
                    </ion-item>
                </ion-list>

                <ion-list-header>Boletas y Facturas</ion-list-header>
                <ion-list>
                    <ion-item v-for="invoice in invoices" :key="invoice.id">
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
                <ion-list>
                    <ion-button expand="block" @click="addInvoice">Añadir Boleta o Factura</ion-button>
                </ion-list>


                <section class="ion-padding">
                    <ion-button expand="block" @click="createExportPDF">Finalizar y Enviar Reporte</ion-button>
                    <a>Borrar reporte</a>
                </section>

                
            </article>
            
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonListHeader, IonButton, IonList, IonItem, IonLabel, IonProgressBar, modalController } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { computed, ref } from 'vue';
import { addOutline } from 'ionicons/icons';
import { IReport } from '../../interfaces/ReportInterfaces';
import NewInvoiceModal  from '@/dialogs/NewInvoice/NewInvoiceModal.vue';

import { useRoute } from 'vue-router';
import { IInvoice } from '@/interfaces/InvoiceInterfaces';
import { DateTime } from 'luxon';
import { JobsList } from '@/utils/JobsAndProjects/JobsAndProjects';
import { PDFCreator } from '@/utils/PDFCreator/PDFCreator';

const report = ref<IReport|null>(null);
const invoicesData = ref<Array<IInvoice>>([]);
const isLoading = ref<boolean>(true);
const reportId = ref<string|null>(null);


const invoices = computed(() => {
    return invoicesData.value.map((invoice) => {
        return {
            ...invoice,
            date: DateTime.fromISO(invoice.date).toLocaleString(DateTime.DATE_MED),
            jobName: JobsList.find((job) => job.code === invoice.job_code)?.name
        }
    })
});


const addInvoice = async () => {
    const modal = await modalController.create({
        component: NewInvoiceModal,
        componentProps: {
            reportId: reportId.value,
            type: 'Bill'
        }
    });

    await modal.present();
}

const loadReport = async () => {
    const routeParams = useRoute();
    reportId.value = routeParams.params.id as string;
    const reportFetched = await RequestAPI.get(`/reports/${reportId.value}`);

    isLoading.value = false;
    report.value = reportFetched;
};
const loadReportInvoices = async () => {
    const invoicesFetched = await RequestAPI.get(`/reports/${reportId.value}/invoices`);
    invoicesData.value = invoicesFetched;
}


const createExportPDF = async () => {
    const instance = new PDFCreator({
        report: report.value as IReport,
        invoices: invoices.value
    });
    await instance.create();
}

const initialize = async () => {
    await loadReport();
    await loadReportInvoices();
}
initialize();
</script>
