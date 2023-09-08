<template>
    <ion-page>
        <ion-header>
            <ion-toolbar>
                <ion-buttons slot="start">
                    <ion-back-button default-href="/"></ion-back-button>
                </ion-buttons>
                <ion-title>Usuários</ion-title>

            </ion-toolbar>
        </ion-header>
        <ion-content>
            {{ usersData }}
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { RequestAPI } from '@/utils/Requests/RequestAPI';
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonBackButton } from '@ionic/vue';
import { ref } from 'vue';


const isLoading = ref(false);

const usersData = ref([]);


const loadUsersList = async () => {
    RequestAPI.get('/users').then((response) => {
        usersData.value = response.data;
    }).catch((error) => {
        console.log(error);
    }).finally(() => {
        isLoading.value = false;
    });
}

loadUsersList();
</script>
