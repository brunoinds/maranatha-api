<template>
    <ion-page ref="page">
        <ion-header>
            <ion-toolbar>
                <ion-buttons slot="start">
                    <ion-back-button default-href="/"></ion-back-button>
                </ion-buttons>
                <ion-title>Jobs</ion-title>
                <ion-progress-bar v-if="isLoading" type="indeterminate"></ion-progress-bar>
                <ion-buttons slot="end">
                    <ion-button @click="addJob">
                        <ion-icon :icon="addOutline"></ion-icon>
                    </ion-button>
                </ion-buttons>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-list v-if="!isLoading">
                <ion-item v-for="job in jobsData" :key="job.id" @click="clickJob(job)" button>
                    <ion-label>
                        <h2><b>{{ job.name }}</b></h2>
                        <p>{{job.code}}</p>
                    </ion-label>
                </ion-item>
            </ion-list>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonImg, IonAvatar, IonProgressBar,IonButtons, IonBackButton, IonListHeader, IonFab, IonChip, IonFabButton, IonIcon, IonList, IonItem, IonLabel, alertController, actionSheetController, toastController } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { computed, ref } from 'vue';

import { addOutline, albumsOutline, alertCircleOutline, checkmarkCircleOutline, close, logIn } from 'ionicons/icons';
import { useRouter } from 'vue-router';

const jobsData = ref<Array<{
    id: number;
    name: string;
    code: string
}>>([]);
const isLoading = ref<boolean>(true);
const router = useRouter();
const page = ref<HTMLElement|null>(null);

const loadJobs = async () => {
    isLoading.value = true;
    jobsData.value = await RequestAPI.get('/jobers');
    isLoading.value = false;
}

const addJob = async (prefiled:any = null) => {
    const alert = await alertController.create({
        header: 'Nuevo Job',
        inputs: [
        {
                type: 'text',
                placeholder: 'Name',
                value: prefiled ? prefiled.name : null
            },
            {
                type: 'text',
                placeholder: 'Code',
                value: prefiled ? prefiled.code : null
            },
        ],
        buttons: [
            {
                text: 'Cancelar',
                role: 'cancel',
                handler: () => {
                    
                },
            },
            {
                text: 'Crear Job',
                role: 'confirm'
            }
        ]
    });

    await alert.present();
    const { role, data } = await alert.onDidDismiss();

    if (role == "confirm"){
        const dataParsed = {
            name: data.values[0],
            code: data.values[1],
        }

        RequestAPI.post('/jobers', dataParsed).then((response) => {
            alertController.create({
                header: '¡Éxito!',
                message: 'Job creado exitosamente',
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                loadJobs();
            });
        }).catch((error) => {
            alertController.create({
                header: 'Oops...',
                message: error.response.message,
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                addJob(dataParsed);
            });
        });
    }
}
const deleteJob = async (job:any) => {
    await RequestAPI.delete(`/jobers/${job.id}`);
    loadJobs();
    toastController.create({
        message: 'Job eliminado con exito!',
        duration: 2000
    }).then((toast) => {
        toast.present();
    })
}
const clickJob = async(job:any) => {
    await actionSheetController.create({
        header: 'Opciones',
        buttons: [
            {
                text: 'Editar',
                handler: () => {
                    editJob(job)
                }
            },
            {
                text: 'Eliminar',
                role: 'destructive',
                handler: () => {
                    deleteJob(job)
                }
            },
            {
                text: 'Cancelar',
                role: 'cancel',
                handler: () => {
                    console.log('Cancel clicked');
                }
            }
        ]
    }).then((actionSheet) => {
        actionSheet.present();
    })
}
const editJob = async (job:any) => {
    const alert = await alertController.create({
        header: 'Editar Job',
        inputs: [
        {
                type: 'text',
                placeholder: 'Name',
                value: job.name
            },
            {
                type: 'text',
                placeholder: 'Code',
                value: job.code
            },
        ],
        buttons: [
            {
                text: 'Cancelar',
                role: 'cancel',
                handler: () => {
                    
                },
            },
            {
                text: 'Guardar Job',
                role: 'confirm'
            }
        ]
    });

    await alert.present();
    const { role, data } = await alert.onDidDismiss();

    if (role == "confirm"){
        const dataParsed = {
            id: job.id,
            name: data.values[0],
            code: data.values[1]
        }


        RequestAPI.patch('/jobers/' + job.id, dataParsed).then((response) => {
            alertController.create({
                header: '¡Éxito!',
                message: 'Job guardado exitosamente',
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                loadJobs();
            });
        }).catch((error) => {
            alertController.create({
                header: 'Oops...',
                message: error.response.message,
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                editJob(job);
            });
        });
    }
}

loadJobs();
</script>
