<template>
    <ion-page>
        <ion-header>
            <ion-toolbar>
                <ion-title>Login</ion-title>
            </ion-toolbar>
        </ion-header>
        <ion-content>


            <article>
                <header></header>
                <main>
                    
                    
                </main>
            </article>

            <ion-card>
                <ion-card-header>
                    <ion-img :src="MaranathaLogo"></ion-img>
                </ion-card-header>

                <main>
                    <ion-list>
                        <ion-item>
                            <ion-input label="Usuário" label-placement="stacked" placeholder="Nombre de usuário"></ion-input>
                        </ion-item>
                        <ion-item>
                            <ion-input label="Contraseña" label-placement="stacked" placeholder="Ingresa su clave"></ion-input>
                        </ion-item>
                    </ion-list>
                </main>
                <footer>
                    <ion-button fill="clear">Iniciar sesión</ion-button>
                    <ion-button color="dark" fill="clear">Olvidé mi contraseña</ion-button>
                </footer>
            </ion-card>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { ref } from 'vue';
import MaranathaLogo from '&/assets/images/maranatha-logo.svg';


console.log(MaranathaLogo)
interface UserReport{
    id: number;
    name: string;
}

const reports = ref<Array<UserReport>>([]);
const isLoading = ref<boolean>(true);

const loadUserReports = (async () => {
    const reportsFetched = await RequestAPI.get('/reports', {
        user_id: 1
    })

    isLoading.value = false;
    reports.value = reportsFetched.data;
});


loadUserReports();
</script>
