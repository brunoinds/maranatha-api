<template>
    <ion-page>
        <ion-header>
            <ion-toolbar>
                <ion-buttons slot="start">
                    <ion-back-button default-href="/"></ion-back-button>
                </ion-buttons>
                <ion-title>Usuários</ion-title>
                <IonProgressBar v-if="isLoading" type="indeterminate"></IonProgressBar>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-list>
                <ion-item v-for="user in usersData" :key="user.id" button @click="showUserOptions(user)" :detail="true">
                    <ion-label>
                        <h2><b>{{ user.name }}</b></h2>
                        <h3>{{ user.email }}</h3>
                        <p>Usuário: @{{ user.username }}</p>
                    </ion-label>
                </ion-item>
            </ion-list>
            <ion-button @click="addUser">
                <ion-icon :icon="addOutline"></ion-icon>
                Nuevo usuario
            </ion-button>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { RequestAPI } from '@/utils/Requests/RequestAPI';
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonBackButton, IonButton, IonIcon, IonList, IonItem, IonLabel, IonProgressBar, alertController, actionSheetController } from '@ionic/vue';
import { addOutline } from 'ionicons/icons';
import { ref } from 'vue';
import { IUser } from '@/interfaces/UserInterfaces';


const isLoading = ref(true);

const usersData = ref<Array<IUser>>([]);


const loadUsersList = async () => {
    RequestAPI.get('/users').then((response) => {
        usersData.value = response;
    }).catch((error) => {
        console.log(error);
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
                message: 'Usuário creado correctamente',
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                loadUsersList();
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

const showUserOptions = async (user: IUser) => {
    const actionSheet = await actionSheetController.create({
        header: 'Opciones para ' + user.name,
        buttons: [
            {
                text: 'Borrar usuario',
                role: 'destructive',
                data: {
                    action: 'deleteUser',
                },
            },
            {
                text: 'Cambiar clave',
                data: {
                    action: 'changePassword',
                },
            },
            {
                text: 'Cancelar',
                role: 'cancel',
                data: {
                    action: 'cancel',
                },
            }
        ]
    })
    await actionSheet.present();
    const { role, data } = await actionSheet.onDidDismiss();
    console.log(role, data);

    if (data.action == "changePassword"){
        changeUserPassword(user);
    }
}

const changeUserPassword = async (user: IUser) => {
    const alert = await alertController.create({
        header: 'Cambiar contraseña',
        inputs: [
            {
                type: 'password',
                placeholder: 'Nueva contraseña',
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
                text: 'Cambiar contraseña',
                role: 'confirm'
            }
        ]
    });

    await alert.present();
    const { role, data } = await alert.onDidDismiss();

    
}

loadUsersList();
</script>
