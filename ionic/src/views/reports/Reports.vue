<template>
    <ion-page ref="page">
        <ion-header>
            <ion-toolbar>
                <ion-title>Mis Reportes</ion-title>
                <ion-progress-bar v-if="isLoading" type="indeterminate"></ion-progress-bar>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-fab slot="fixed" vertical="bottom" horizontal="end" :edge="false">
                <ion-fab-button @click="createNewReport">
                    <ion-icon :icon="addOutline"></ion-icon>
                </ion-fab-button>
            </ion-fab>


            <article v-if="reports.drafts.length > 0">
                <ion-list-header>Pendientes</ion-list-header>
                <ion-list>
                    <ion-item v-for="report in reports.drafts" :key="report.id" button @click="openReport(report.id)" :detail="true">
                        <ion-label>
                                <h2><b>{{ report.title }}</b></h2>
                                <p>{{report.reportType}}</p>
                                <p>{{report.reportDates}}</p>
                                <p><b>S./ {{report.invoices.totalAmount}}</b></p>
                            </ion-label>
                            <ion-chip color="danger" v-if="report.status == 'Draft'">
                                <ion-icon :icon="alertCircleOutline"></ion-icon>
                                <ion-label>{{report.reportStatus}}</ion-label>
                            </ion-chip>
                            <ion-chip color="success" v-if="report.status == 'Submitted'">
                                <ion-icon :icon="checkmarkCircleOutline"></ion-icon>
                                <ion-label>{{report.reportStatus}}</ion-label>
                            </ion-chip>
                    </ion-item>
                </ion-list>
            </article>

            <article v-if="reports.submitted.length > 0">
                <ion-list-header>Enviados</ion-list-header>
                <ion-list>
                    <ion-item v-for="report in reports.submitted" :key="report.id" button @click="openReport(report.id)" :detail="true">
                        <ion-label>
                                <h2><b>{{ report.title }}</b></h2>
                                <p>{{report.reportType}}</p>
                                <p>{{report.reportDates}}</p>
                                <p><b>S./ {{report.invoices.totalAmount}}</b></p>
                            </ion-label>
                            <ion-chip color="danger" v-if="report.status == 'Draft'">
                                <ion-icon :icon="alertCircleOutline"></ion-icon>
                                <ion-label>{{report.reportStatus}}</ion-label>
                            </ion-chip>
                            <ion-chip color="success" v-if="report.status == 'Submitted'">
                                <ion-icon :icon="checkmarkCircleOutline"></ion-icon>
                                <ion-label>{{report.reportStatus}}</ion-label>
                            </ion-chip>
                    </ion-item>
                </ion-list>
            </article>


            <article v-if="!isLoading && reportsData.length == 0" class="ion-padding" style="display: flex;flex-direction: column; align-items: center; justify-content: center; height: 100%;">
                <ion-img :src="ReportIcon" style="width: 90%;margin: 0 auto;"></ion-img>
                <h2>Aún no tienes reportes</h2>
                <p class="ion-text-center">Crea tu primer reporte haciendo click en el botón "+" abajo</p>
            </article>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonProgressBar, IonImg, IonListHeader, IonFab, IonChip, IonFabButton, IonIcon, IonList, IonItem, IonLabel, alertController } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { computed, ref } from 'vue';
import { Dialog } from '../../utils/Dialog/Dialog';
import ReportIcon from '&/assets/icons/report.svg';

import { addOutline, albumsOutline, alertCircleOutline, checkmarkCircleOutline } from 'ionicons/icons';
import { IReport } from '../../interfaces/ReportInterfaces';
import { useRouter } from 'vue-router';
import NewReport from '../../dialogs/NewReport/NewReport.vue';
import { DateTime } from 'luxon';

const reportsData = ref<Array<IReport>>([]);
const isLoading = ref<boolean>(true);
const router = useRouter();
const page = ref<HTMLElement|null>(null);



const reports = computed(() => {
    const reportsItems = reportsData.value.map((report) => {
        return {
            ...report,
            reportType: report.type == 'Bill' ? 'Boletas' : 'Facturas',
            reportStatus: report.status == 'Draft' ? 'Pendiente' : 'Enviado',
            reportDates: `${DateTime.fromISO(report.from_date).toLocaleString(DateTime.DATE_MED)} - ${DateTime.fromISO(report.to_date).toLocaleString(DateTime.DATE_MED)}`,
            invoices: {
                total: (report as any).invoices.count,
                totalAmount: (report as any).invoices.total_amount
            }
        }
    })


    return {
        drafts: reportsItems.filter((report) => report.status == 'Draft'),
        submitted: reportsItems.filter((report) => report.status == 'Submitted')
    }
})
const loadUserReports = async () => {
    const reportsFetched = await RequestAPI.get('/me/reports');

    isLoading.value = false;
    reportsData.value = reportsFetched;
};

const openReport = (reportId: number) => {
    router.push(`/reports/${reportId}`);
}

const createNewReport = async () => {
    Dialog.show(NewReport, {
        onLoaded($this) {
            $this.on('created', (event:any) => {
                const newReportId = event.data.id;
                openReport(newReportId);
                loadUserReports();
            })
        },
        modalControllerOptions: {
            presentingElement: page,
            showBackdrop: true,
        }
    })
}


loadUserReports();
</script>
