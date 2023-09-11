<template>
    <ion-page ref="page">
        <ion-header>
            <ion-toolbar>
                <ion-title>Mi cuenta</ion-title>
                <ion-progress-bar v-if="isLoading" type="indeterminate"></ion-progress-bar>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-list v-if="!isLoading">
                <ion-item>
                    <ion-avatar slot="start">
                        <img alt="Silhouette of a person's head" src="https://ionicframework.com/docs/img/demos/avatar.svg" />
                    </ion-avatar>
                    <ion-label>
                        <h2>{{ accountData.name }}</h2>
                        <p>{{ accountData.email }}</p>
                    </ion-label>
                </ion-item>
                <ion-item @click="doLogout">, IonAvatar
                    <ion-icon color="danger" :icon="close" slot="start"></ion-icon>
                    <ion-label color="danger">Terminar sesión</ion-label>
                </ion-item>
            </ion-list>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonImg, IonAvatar, IonProgressBar, IonListHeader, IonFab, IonChip, IonFabButton, IonIcon, IonList, IonItem, IonLabel, alertController } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { computed, ref } from 'vue';
import { Dialog } from '../../utils/Dialog/Dialog';

import { addOutline, albumsOutline, alertCircleOutline, checkmarkCircleOutline, close, logIn } from 'ionicons/icons';
import { IReport } from '../../interfaces/ReportInterfaces';
import { useRouter } from 'vue-router';
import { Session } from '@/utils/Session/Session';

const accountData = ref<any>(null);
const isLoading = ref<boolean>(true);
const router = useRouter();
const page = ref<HTMLElement|null>(null);

const goToLogin = () => {
    router.replace('/login');
}

const loadAccount = async () => {
    accountData.value = await RequestAPI.get('/account/me');
    isLoading.value = false;
}

const doLogout = async () => {
    Session.getCurrentSession().then(async(session: Session) => {
        if (!session){
            goToLogin();
        }
        session.logout().then(() => {
            goToLogin();
        }).catch((error) => {
            console.error(error)
        })
    });
}

loadAccount();
</script>
