<template>
    <ion-page>
        <ion-header>
        </ion-header>
        <ion-content class="ion-padding" :scrollX="false" :scrollY="false">
            <section class="holder">
                <article style="">
                    <header>
                        <ion-img :src="MaranathaLogo" style="width: 90%;margin: 0 auto;"></ion-img>
                    </header>
                    <main>
                        <ion-list>
                            <ion-item>
                                <ion-input label="Usuário" label-placement="stacked" v-model="loginData.username" placeholder="Nombre de usuário"></ion-input>
                            </ion-item>
                            <ion-item>
                                <ion-input label="Contraseña" label-placement="stacked" v-model="loginData.password" placeholder="Ingresa su clave"></ion-input>
                            </ion-item>
                        </ion-list>
                    </main>
                    <footer>
                        <ion-button v-if="!isLoading" :disabled="!enableLoginButton" expand="block" @click="doLogin">Iniciar sesión</ion-button>
                        <IonProgressBar v-if="isLoading" type="indeterminate"></IonProgressBar>
                    </footer>
                </article>
            </section>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonCard, IonCardHeader, IonImg, IonList, IonInput, IonItem, IonButton, IonProgressBar, alertController } from '@ionic/vue';
import { computed, ref } from 'vue';
import MaranathaLogo from '&/assets/images/maranatha-logo.svg';
import { Session } from '@/utils/Session/Session';
import { useRouter } from 'vue-router';

const router = useRouter();
const loginData = ref({
    username: '',
    password: ''
});

const enableLoginButton = computed(() => {
    return loginData.value.username.length > 0 && loginData.value.password.length >= 8;
})

const isLoading = ref(false);
const doLogin = () => {
    isLoading.value = true;
    Session.login(loginData.value.username, loginData.value.password).then((response) => {
        router.push('/reports');
    }).catch((error) => {
        if (error.message == "Invalid credentials"){
            alertController.create({
                header: 'Oops...',
                message: 'Usuario o contraseña incorrectos',
                buttons: ['OK']
            }).then((alert) => {
                alert.present();
            });
        }else{
            alertController.create({
                header: 'Oops...',
                message: error.message,
                buttons: ['OK']
            }).then((alert) => {
                alert.present();
            });
        }
    }).finally(() => {
        isLoading.value = false;
    });
}

const preventLoginIfHasSession = async () => {
    if (await Session.isLogged()) {
        router.push('/reports');
    }
}
preventLoginIfHasSession();
</script>


<style scoped lang="scss">
.holder{
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;

    > article{
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 100%;
        max-width: 400px;
        display: flex;flex-direction: column;row-gap: 12px;
    }
}
</style>