<template>
    <ion-page ref="page">
        <ion-header>
            <ion-toolbar>
                <ion-buttons slot="start">
                    <ion-back-button default-href="/"></ion-back-button>
                </ion-buttons>
                <ion-title>Projects</ion-title>
                <ion-progress-bar v-if="isLoading" type="indeterminate"></ion-progress-bar>
                <ion-buttons slot="end">
                    <ion-button @click="addProject">
                        <ion-icon :icon="addOutline"></ion-icon>
                    </ion-button>
                </ion-buttons>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-list v-if="!isLoading">
                <ion-item v-for="project in projectsData" :key="project.id" @click="clickProject(project)" button>
                    <ion-label>
                        <h2><b>{{ project.name }}</b></h2>
                        <p>{{project.code}}</p>
                        <p>{{project.union}}</p>
                    </ion-label>
                </ion-item>
            </ion-list>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonImg, IonAvatar, IonButtons, IonBackButton, IonProgressBar, IonListHeader, IonFab, IonChip, IonFabButton, IonIcon, IonList, IonItem, IonLabel, alertController, actionSheetController, toastController } from '@ionic/vue';
import { RequestAPI } from '../../utils/Requests/RequestAPI';
import { computed, ref } from 'vue';

import { addOutline, albumsOutline, alertCircleOutline, checkmarkCircleOutline, close, logIn } from 'ionicons/icons';
import { useRouter } from 'vue-router';

const projectsData = ref<Array<{
    id: number;
    name: string;
    code: string,
    union: string
}>>([]);
const isLoading = ref<boolean>(true);
const router = useRouter();
const page = ref<HTMLElement|null>(null);

const loadProjects = async () => {
    isLoading.value = true;
    projectsData.value = await RequestAPI.get('/projects');
    isLoading.value = false;
}

const addProject = async (prefiled:any = null) => {
    const alert = await alertController.create({
        header: 'Nuevo Proyecto',
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
            {
                type: 'text',
                placeholder: 'Unión',
                value: prefiled ? prefiled.union : null
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
                text: 'Crear Proyecto',
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
            union: data.values[2]
        }

        RequestAPI.post('/projects', dataParsed).then((response) => {
            alertController.create({
                header: '¡Éxito!',
                message: 'Proyecto creado exitosamente',
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                loadProjects();
            });
        }).catch((error) => {
            alertController.create({
                header: 'Oops...',
                message: error.response.message,
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                addProject(dataParsed);
            });
        });
    }
}
const deleteProject = async (job:any) => {
    await RequestAPI.delete(`/projects/${job.id}`);
    loadProjects();
    toastController.create({
        message: 'Proyecto eliminado con exito!',
        duration: 2000
    }).then((toast) => {
        toast.present();
    })
}


const clickProject = async(job:any) => {
    await actionSheetController.create({
        header: 'Opciones',
        buttons: [
            {
                text: 'Editar',
                handler: () => {
                    editProject(job)
                }
            },
            {
                text: 'Eliminar',
                role: 'destructive',
                handler: () => {
                    deleteProject(job)
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


const editProject = async (project:any) => {
    const alert = await alertController.create({
        header: 'Editar Proyecto',
        inputs: [
        {
                type: 'text',
                placeholder: 'Name',
                value: project.name
            },
            {
                type: 'text',
                placeholder: 'Code',
                value: project.code
            },
            {
                type: 'text',
                placeholder: 'Unión',
                value: project.union
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
                text: 'Guardar proyecto',
                role: 'confirm'
            }
        ]
    });

    await alert.present();
    const { role, data } = await alert.onDidDismiss();

    if (role == "confirm"){
        const dataParsed = {
            id: project.id,
            name: data.values[0],
            code: data.values[1],
            union: data.values[2]
        }


        RequestAPI.patch('/projects/' + project.id, dataParsed).then((response) => {
            alertController.create({
                header: '¡Éxito!',
                message: 'Proyecto guardado exitosamente',
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                loadProjects();
            });
        }).catch((error) => {
            alertController.create({
                header: 'Oops...',
                message: error.response.message,
                buttons: ['OK']
            }).then(async (alert) => {
                await alert.present();
                await alert.onDidDismiss();
                editProject(project);
            });
        });
    }
}

loadProjects();
</script>
