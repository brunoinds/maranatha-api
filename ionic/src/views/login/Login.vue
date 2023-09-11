<template>
    <ion-page>
        <ion-header>
        </ion-header>
        <ion-content class="ion-padding" :scrollX="false" :scrollY="false">
            <section class="holder ion-padding">
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
                        <ion-button v-if="!isLoading" expand="block" fill="outline" @click="addUser">Crear una cuenta</ion-button>

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
import { RequestAPI } from '@/utils/Requests/RequestAPI';

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
        router.push('/my-reports');
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

const addUser = async (prefiled:any = null) => {
    const alert = await alertController.create({
        header: 'Nuevo usuário',
        inputs: [
        {
                type: 'text',
                placeholder: 'Nombres y apellidos',
                value: prefiled ? prefiled.name : null
            },
            {
                type: 'email',
                placeholder: 'Correo electrónico',
                value: prefiled ? prefiled.email : null
            },
            {
                type: 'text',
                placeholder: 'Nombre de usuario',
                value: prefiled ? prefiled.username : null
            },
            {
                type: 'password',
                placeholder: 'Contraseña',
                value: prefiled ? prefiled.password : null
            }
        ],
        buttons: [
            {
                text: 'Cancelar',
                role: 'cancel',
                handler: () => {
                    
                },
            },
            {
                text: 'Crear Usuário',
                role: 'confirm'
            }
        ]
    });

    await alert.present();
    const { role, data } = await alert.onDidDismiss();

    if (role == "confirm"){
        const dataParsed = {
            name: data.values[0],
            username: data.values[2],
            email: data.values[1],
            password: data.values[3]
        }

        RequestAPI.post('/users', dataParsed).then((response) => {
            alertController.create({
                header: '¡Éxito!',
                message: 'Usuário creado exitosamente',
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                loginData.value.username = dataParsed.username;
                loginData.value.password = dataParsed.password;
                doLogin();
            });
        }).catch((error) => {
            alertController.create({
                header: 'Oops...',
                message: error.response.message,
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                addUser(dataParsed);
            });
        });
    }
}

const preventLoginIfHasSession = async () => {
    if (await Session.isLogged()) {
        router.push('/my-reports');
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
        padding: 20px;
        display: flex;flex-direction: column;row-gap: 12px;
    }
}
</style>