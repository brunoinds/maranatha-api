<template>
    <ion-page>
        <ion-header>
            <ion-toolbar>
                <ion-title>Mis Reportes</ion-title>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-fab slot="fixed" vertical="bottom" horizontal="end" :edge="false">
                <ion-fab-button>
                    <ion-icon :icon="addOutline"></ion-icon>
                </ion-fab-button>
            </ion-fab>


            <ion-list>
                <ion-item v-for="report in reports" :key="report.id" button @click="openReport(report.id)" :detail="true">
                    <ion-label>
                        <h2>{{ report.title }}</h2>
                        <h3>{{ report.status }}</h3>
                    </ion-label>
                </ion-item>
            </ion-list>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonFab, IonFabButton, IonIcon, IonList, IonItem, IonLabel, alertController } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { ref } from 'vue';

import { addOutline } from 'ionicons/icons';
import { IReport } from '../../interfaces/ReportInterfaces';
import { useRouter } from 'vue-router';

const reports = ref<Array<IReport>>([]);
const isLoading = ref<boolean>(true);
const router = useRouter();

const loadUserReports = async () => {
    const reportsFetched = await RequestAPI.get('/reports');

    isLoading.value = false;
    reports.value = reportsFetched;
};

const openReport = (reportId: number) => {
    router.push(`/reports/${reportId}`);
}

const createNewReport = async () => {
    alertController.create({
        
    })
}


loadUserReports();
</script>
