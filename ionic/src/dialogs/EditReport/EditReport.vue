<template>
    <ion-page>
        <ion-header>
            <ion-toolbar>
                <ion-buttons slot="start">
                    <ion-button @click="props.emitter.fire('close')">Cancelar</ion-button>
                </ion-buttons>
                <ion-title>Modificar Reporte</ion-title>
                <ion-buttons slot="end">    
                    <ion-button @click="saveReport">Guardar</ion-button>
                </ion-buttons>
                <ion-progress-bar v-if="isLoading" type="indeterminate"></ion-progress-bar>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <ion-list :inset="true" v-if="!isLoading">
                <ion-item>
                    <ion-input label="Nombre del reporte" label-placement="stacked" placeholder="Nombre del reporte"  v-model="dynamicData.title" :disabled="isLoading"></ion-input>
                </ion-item>

                <ion-item>
                    <ion-select label="Tipo de documento" label-placement="stacked" interface="action-sheet" v-model="dynamicData.type" :disabled="isLoading">
                        <ion-select-option value="Bill">Boletas</ion-select-option>
                        <ion-select-option value="Facture">Facturas</ion-select-option>
                    </ion-select>
                </ion-item>
                <ion-item>
                    <ion-label position="stacked">Fecha de Inicio</ion-label>
                    <input class="native-input sc-ion-input-ios" v-maska data-maska="##/##/####" v-model="dynamicData.startDate" :disabled="isLoading">
                </ion-item>
                <ion-item>
                    <ion-label position="stacked">Fecha de Término</ion-label>
                    <input class="native-input sc-ion-input-ios" v-maska data-maska="##/##/####" v-model="dynamicData.endDate" :disabled="isLoading">
                </ion-item>
            </ion-list>
            <article class="ion-padding" v-if="!isLoading">
                <ion-button color="danger" @click="deleteReport" expand="block">
                    <ion-icon :icon="trashBinOutline" slot="start"></ion-icon>
                    Borrar reporte
                </ion-button>
            </article>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonImg, IonToolbar, IonTitle, IonButtons, IonThumbnail, IonContent,  IonListHeader, IonIcon, IonInput, IonSelect, IonSelectOption, IonModal, IonDatetime, IonDatetimeButton, IonButton, IonList, IonItem, IonLabel, IonProgressBar, toastController, alertController } from '@ionic/vue';
import { defineComponent, nextTick, onMounted, reactive, ref } from 'vue';
import { briefcaseOutline, trashBinOutline, camera, cameraOutline, qrCodeOutline, ticketOutline, checkmarkCircleOutline, arrowForwardCircleOutline, cash } from 'ionicons/icons';
import { DialogEventEmitter } from "../../utils/Dialog/Dialog";
import { vMaska } from "maska";
import { EReportType } from '@/interfaces/ReportInterfaces';
import { DateTime } from 'luxon';
import { RequestAPI } from '@/utils/Requests/RequestAPI';
import { Session } from '@/utils/Session/Session';

const isLoading = ref<boolean>(false);
const props = defineProps({
    emitter: {
        type: DialogEventEmitter,
        required: true
    },
    reportId: {
        type: Number,
        required: true
    }
});
const dynamicData = ref<{
    title: string,
    type: EReportType,
    startDate: string,
    endDate: string
}>({
    title: '',
    type: EReportType.Bill,
    startDate: (DateTime.now().set({ day: 1}).toFormat("dd/MM/yyyy") as unknown as string).toString(),
    endDate: (DateTime.now().set({ day: 1}).plus({ month: 1}).minus({ day: 1}).toFormat("dd/MM/yyyy") as unknown as string).toString()
});

const deleteReport = async () => {
    alertController.create({
        header: '¿Estás seguro?',
        message: '¿Estás seguro que deseas borrar este reporte y todas las boletas/facturas relacionadas a él? Esta acción no se puede deshacer.',
        buttons: [
            {
                text: 'Cancelar',
                role: 'cancel'
            },
            {
                text: 'Borrar',
                role: 'destructive',
                handler: () => {
                    isLoading.value = true;
                    RequestAPI.delete(`/reports/${props.reportId}`).then((response) => {
                        props.emitter.fire('deleted', {
                            ...response.report
                        });
                        toastController.create({
                            message: 'Reporte borrado con exito!',
                            duration: 2000
                        }).then((toast) => {
                            toast.present();
                        })
                        props.emitter.fire('close');
                    }).catch((error) => {
                        alertController.create({
                            header: 'Oops...',
                            message: error.response.message,
                            buttons: ['OK']
                        }).then((alert) => {
                            alert.present();
                        });
                    }).finally(() => {
                        isLoading.value = false;
                    });
                }
            }
        ]
    }).then((alert) => {
        alert.present();
    })
}
const saveReport = async () => {
    const validationResponse = validateCamps();
    if (!validationResponse.isValid){
        alertController.create({
            header: 'Oops...',
            message: validationResponse.errors[0] as string,
            buttons: ['OK']
        }).then((alert) => {
            alert.present();
        });
        return;
    }


    isLoading.value = true;

    RequestAPI.patch(`/reports/${props.reportId}`, {
        user_id: (await Session.getCurrentSession())?.id(),
        title: dynamicData.value.title,
        type: dynamicData.value.type,
        from_date: DateTime.fromFormat(dynamicData.value.startDate, "dd/MM/yyyy").toISO(),
        to_date: DateTime.fromFormat(dynamicData.value.endDate, "dd/MM/yyyy").toISO(),
    }).then((response) => {
        props.emitter.fire('updated', {
            ...response.report
        });
        toastController.create({
            message: 'Reporte guardado con exito!',
            duration: 2000
        }).then((toast) => {
            toast.present();
        })
        props.emitter.fire('close');
    }).catch((error) => {
        alertController.create({
            header: 'Oops...',
            message: error.response.message,
            buttons: ['OK']
        }).then((alert) => {
            alert.present();
        });
    }).finally(() => {
        isLoading.value = false;
    });
}

const validateCamps = () => {
    let errors = [];

    if (dynamicData.value.title.trim().length == 0){
        errors.push("El nombre del reporte no puede estar vacío");
    }

    const isStartDateValid = DateTime.fromFormat(dynamicData.value.startDate, "dd/MM/yyyy").isValid;
    const isEndDateValid = DateTime.fromFormat(dynamicData.value.endDate, "dd/MM/yyyy").isValid;

    if (!isStartDateValid){
        errors.push(DateTime.fromFormat(dynamicData.value.startDate, "dd/MM/yyyy").invalidExplanation);
    }
    if (!isEndDateValid){
        errors.push(DateTime.fromFormat(dynamicData.value.endDate, "dd/MM/yyyy").invalidExplanation);
    }

    if (isStartDateValid && isEndDateValid){
        const startDate = DateTime.fromFormat(dynamicData.value.startDate, "dd/MM/yyyy");
        const endDate = DateTime.fromFormat(dynamicData.value.endDate, "dd/MM/yyyy");

        if (startDate > endDate){
            errors.push("La fecha de inicio no puede ser mayor a la fecha de término");
        }
    }

    return {
        isValid: errors.length == 0,
        errors: errors
    }
}

const loadReport = () => {
    isLoading.value = true;
    RequestAPI.get(`/reports/${props.reportId}`).then((response) => {
        dynamicData.value = {
            title: response.title,
            type: response.type,
            startDate: DateTime.fromISO(response.from_date).toFormat("dd/MM/yyyy"),
            endDate: DateTime.fromISO(response.to_date).toFormat("dd/MM/yyyy")
        }

    }).catch((error) => {
        alertController.create({
            header: 'Oops...',
            message: error.response.message,
            buttons: ['OK']
        }).then(async (alert) => {
            await alert.present();
            await alert.onDidDismiss();
            props.emitter.fire('close');
        });
    }).finally(() => {
        isLoading.value = false;
    });
}
loadReport();
</script>

<style scoped lang="scss">
</style>