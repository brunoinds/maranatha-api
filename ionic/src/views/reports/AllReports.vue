<template>
    <ion-page ref="page">
        <ion-header>
            <ion-toolbar>
                <ion-title>Todos los Reportes</ion-title>
                <ion-progress-bar v-if="isLoading" type="indeterminate"></ion-progress-bar>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-accordion-group>
                <ion-accordion v-for="userReports in usersReports" :key="userReports.user.id">
                    <ion-item slot="header" color="light">
                        <ion-label>
                            <h2>{{ userReports.user.name }}</h2>
                            <p>@{{ userReports.user.username }}</p>
                        </ion-label>
                    </ion-item>
                    <section slot="content">
                        <ion-list>
                            <ion-item v-for="report in userReports.reports" :key="report.id" button @click="openReport(report.id)" :detail="true">
                                <ion-label>
                                        <h2><b>{{ report.title }}</b></h2>
                                        <p>{{report.reportType}}</p>
                                        <p>{{report.reportDates}}</p>
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
                    </section>
                </ion-accordion>
            </ion-accordion-group>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent,IonAccordion, IonAccordionGroup, IonProgressBar, IonImg, IonListHeader, IonFab, IonChip, IonFabButton, IonIcon, IonList, IonItem, IonLabel, alertController } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { computed, ref } from 'vue';
import { addOutline, albumsOutline, alertCircleOutline, checkmarkCircleOutline } from 'ionicons/icons';
import { IReport } from '../../interfaces/ReportInterfaces';
import { useRouter } from 'vue-router';
import { DateTime } from 'luxon';

const reportsData = ref<Array<IReport>>([]);
const isLoading = ref<boolean>(true);
const router = useRouter();
const page = ref<HTMLElement|null>(null);



const usersReports = computed(() => {
    //Group reports by user_id, creating for each user_id a new array, return an array of users, please:
    const reportsGroupedByUser = reportsData.value.reduce((acc: any, report: IReport) => {
        if (!acc[report.user_id]){
            acc[report.user_id] = [];
        }
        acc[report.user_id].push(report);
        return acc;
    }, {});
    //Convert this key-value object to an array of objects, please:

    const reportsGroupedByUserArray = Object.keys(reportsGroupedByUser).map((key) => {
        return {
            user: reportsGroupedByUser[key][0].user,
            reports: reportsGroupedByUser[key].map((report:IReport) => {
                return{
                    ...report,
                    reportType: report.type == 'Bill' ? 'Boletas' : 'Facturas',
                    reportStatus: report.status == 'Draft' ? 'Pendiente' : 'Enviado',
                    reportDates: `${DateTime.fromISO(report.from_date).toLocaleString(DateTime.DATE_MED)} - ${DateTime.fromISO(report.to_date).toLocaleString(DateTime.DATE_MED)}`
                }
            })
        }
    });

    return reportsGroupedByUserArray;
})
const loadAllReports = async () => {
    const reportsFetched = await RequestAPI.get('/reports');

    isLoading.value = false;
    reportsData.value = reportsFetched;
};

const openReport = (reportId: number) => {
    router.push(`/reports/${reportId}`);
}

loadAllReports();
</script>
