<template>
    <ion-page>
        <ion-header>
            <ion-toolbar>
                <ion-title>Añadir Boleta o Factura</ion-title>
            </ion-toolbar>
        </ion-header>
        <ion-content>
            <article>
                <ion-list-header>
                    <ion-label>
                        <b>1. Adjuntar boleta/factura</b>
                    </ion-label>
                </ion-list-header>
                <ion-list>
                    <ion-item>
                        <ion-input label="Descripción del gasto" label-placement="stacked" placeholder="Ej.: material para construcción" v-model="invoice.description"></ion-input>
                    </ion-item>
                </ion-list>


                <ion-list v-if="dynamicData.uploadedImageBase64">
                    <ion-item>
                        <ion-thumbnail slot="start">
                            <ion-img :src="'data:image/jpeg;base64,' + dynamicData.uploadedImageBase64"></ion-img>
                        </ion-thumbnail>
                        <ion-button fill="outline" color="danger" @click="deleteImageFromCamera"> 
                            <ion-icon slot="start" :icon="trashBinOutline"></ion-icon>
                            Borrar Foto de la Boleta/Factura
                        </ion-button>
                    </ion-item>
                </ion-list>
                <section class="ion-padding" v-if="!dynamicData.uploadedImageBase64">
                    <ion-button expand="block" fill="outline" @click="openCamera"> 
                        <ion-icon slot="start" :icon="camera"></ion-icon>
                        Tomar Foto de la Boleta/Factura
                    </ion-button>
                </section>
                

                <ion-list-header>
                    <ion-label>
                        <b>2. Datos de la boleta/factura</b>
                    </ion-label>
                </ion-list-header>

                
                <ion-list>
                    <ion-item>
                        <ion-input label="Código QR" label-placement="stacked" placeholder="" v-model="invoice.qrcode_data"></ion-input>
                        <ion-button slot="end" fill="clear" @click="openQRCodeScanner"> 
                            Scanear QR
                            <ion-icon slot="start" :icon="qrCodeOutline"></ion-icon>
                        </ion-button>
                    </ion-item>
                    <ion-item>
                        <ion-label position="stacked">Fecha</ion-label>
                        <input class="native-input sc-ion-input-md" v-maska data-maska="##/##/####" v-model="invoice.date">
                    </ion-item>

                    <ion-item>
                        <ion-label position="stacked">Precio</ion-label>
                        <CurrencyInput class="native-input sc-ion-input-md" v-model="invoice.amount" :options="{ currency: 'PEN', autoDecimalDigits: true, currencyDisplay: 'hidden' }"></CurrencyInput>
                    </ion-item>
                    <ion-item>
                        <ion-input label="Código de Factura/Boleta" label-placement="stacked" placeholder="AAXX-XXXXXXXX" v-model="invoice.ticket_number"></ion-input>
                    </ion-item>
                    <ion-item>
                        <ion-input label="RUC" label-placement="stacked" placeholder="XXXXXXXXXXX" v-model="invoice.commerce_number"></ion-input>
                    </ion-item>
                </ion-list>
                
                

                <ion-list-header>
                    <ion-label>
                        <b>3. Datos del proyecto</b>
                    </ion-label>
                </ion-list-header>
                <ion-list>
                    <ion-item>
                        <ion-select label="Job" label-placement="stacked" interface="action-sheet" placeholder="Selecciona el Job"  v-model="invoice.job_code">
                            <ion-select-option v-for="job in jobsAndProjects.jobs" :value="job.code">{{ job.name }}</ion-select-option>
                        </ion-select>                    
                    </ion-item>
                    <ion-item>
                        <ion-select label="Proyecto" label-placement="stacked" placeholder="Selecciona el Proyecto"  v-model="invoice.expense_code">
                            <ion-select-option v-for="project in jobsAndProjects.projects" :value="project.code">{{ project.name }}</ion-select-option>
                        </ion-select>                  
                    </ion-item>
                </ion-list>


                <section class="ion-padding">
                    <ion-button expand="block" shape="round" size="default" style="height: 50px" @click="createNewInvoice">
                        <ion-icon :icon="arrowForwardCircleOutline" slot="end"></ion-icon>
                        Añadir Boleta/Factura
                    </ion-button>
                </section>
            </article>
        </ion-content>
    </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonImg, IonToolbar, IonTitle, IonThumbnail, IonContent, IonListHeader, IonIcon, IonInput, IonSelect, IonSelectOption, IonModal, IonDatetime, IonDatetimeButton, IonButton, IonList, IonItem, IonLabel, IonProgressBar, toastController, alertController } from '@ionic/vue';
import { defineComponent, nextTick, onMounted, reactive, ref } from 'vue';
import { EInvoiceType, IInvoice, INewInvoice } from '../../interfaces/InvoiceInterfaces';
import { IJob, IProject } from '../../interfaces/JobsAndProjectsInterfaces';
import { briefcaseOutline, trashBinOutline, camera, cameraOutline, qrCodeOutline, ticketOutline, checkmarkCircleOutline, arrowForwardCircleOutline, cash } from 'ionicons/icons';

import { JobsList, ProjectsList } from '../../utils/JobsAndProjects/JobsAndProjects';
import { QRCodeScanner } from '@/dialogs/QRCodeScanner/QRCodeScanner';
//import { Money3Component } from 'v-money3';
import { vMaska } from "maska";
import { DateTime } from "luxon";
import { QRCodeParser } from '@/utils/QRCodeParser/QRCodeParser';
import CurrencyInput from '@/components/CurrencyInput/CurrencyInput.vue';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { RequestAPI } from '@/utils/Requests/RequestAPI';

const isLoading = ref<boolean>(true);
const dynamicData = ref<{
    uploadedImageBase64: null | string,
    formErrors: Array<{field: string, message: string}>,
    status: "idle" | "uploading-image" | "creating-invoice" | "success" | "error"
}>({
    uploadedImageBase64: null,
    formErrors: [],
    status: "idle"
})
const props = defineProps({
    reportId: {
        type: Number,
        required: true
    },
    type: {
        type: String,
        required: true
    }
});
const jobsAndProjects = ref<{jobs: Array<IJob>, projects: Array<IProject>}>({
    jobs: JobsList,
    projects: ProjectsList
});

const invoice = ref<INewInvoice>({
    report_id: props.reportId,
    type: props.type as unknown as EInvoiceType,
    description: "Test",
    ticket_number: "test",
    commerce_number: "test",
    date: DateTime.now().toFormat("dd/MM/yyyy").toString(),
    job_code: "701",
    expense_code: "1001",
    amount: 1 as unknown as number,
    qrcode_data: "",
    image: null
});


const openQRCodeScanner = async () => {
    QRCodeScanner.open().onScan().then((content) => {
        const response = QRCodeParser.parseBuyCode(content);

        if (!response.isValid || !response.content){
            toastController.create({
                message: "El código QR no es válido",
                duration: 2000
            }).then((toast) => {
                toast.present();
            })
            return;
        }

        invoice.value.qrcode_data = response.qrCode;
        invoice.value.ticket_number = response.content.docCode;
        invoice.value.commerce_number = response.content.ruc;
        invoice.value.amount = parseFloat(response.content.price);

        if (response.content.date){
            const ticketDate = DateTime.fromFormat(response.content.date, "yyyy-MM-dd");
            invoice.value.date = ticketDate.toFormat("dd/MM/yyyy");
        }
        console.log(response)
    })
}
const openCamera = async () => {
    const image = await Camera.getPhoto({
        quality: 90,
        allowEditing: true,
        resultType: CameraResultType.Base64,
        source: CameraSource.Camera
    });

    const base64Image = image.base64String as unknown as string;
    dynamicData.value.uploadedImageBase64 = base64Image;
}
const deleteImageFromCamera = () => {
    dynamicData.value.uploadedImageBase64 = null;
}

const validateData = async () => {
    const formErrors: Array<{field: string, message: string}> = [];

    if (!dynamicData.value.uploadedImageBase64){
        formErrors.push({
            field: "image",
            message: "La foto de la boleta/factura es requerida"
        })
    }
    if (!invoice.value.date){
        formErrors.push({
            field: "date",
            message: "La fecha es requerida"
        })
    }else{
        const dt = DateTime.fromFormat(invoice.value.date, "dd/MM/yyyy");
        if (!dt.isValid){
            formErrors.push({
                field: "date",
                message: "La fecha no es válida " + dt.invalidExplanation
            })
        }
    }
    if (!invoice.value.amount){
        formErrors.push({
            field: "amount",
            message: "El monto es requerido"
        })
    }
    if (!invoice.value.ticket_number || invoice.value.ticket_number.trim().length == 0){
        formErrors.push({
            field: "ticket_number",
            message: "El número de boleta/factura es requerido"
        })
    }
    if (!invoice.value.commerce_number || invoice.value.commerce_number.trim().length == 0){
        formErrors.push({
            field: "commerce_number",
            message: "El RUC es requerido"
        })
    }
    if (!invoice.value.job_code){
        formErrors.push({
            field: "job_code",
            message: "El Job es requerido"
        })
    }
    if (!invoice.value.expense_code){
        formErrors.push({
            field: "expense_code",
            message: "El Proyecto es requerido"
        })
    }
    if (!invoice.value.description || invoice.value.description.trim().length == 0){
        formErrors.push({
            field: "description",
            message: "La descripción del gasto es requerida"
        })
    }

    if (formErrors.length > 0){
        return {
            isValid: false,
            errors: formErrors
        };
    }else{
        return {
            isValid: true,
            errors: []
        };
    }
}

const createNewInvoice = async () => {
    const validationResponse = validateData();
    if (!(await validationResponse).isValid){
        alertController.create({
            header: "Oops...",
            subHeader: "Hay errores en el formulario",
            message: (await validationResponse).errors[0].message,
            buttons: ["OK"]
        }).then((alert) => {
            alert.present();
        })
    }else{
        dynamicData.value.status = "creating-invoice";
        const invoiceResponse = await RequestAPI.post("/invoices", {
            ...invoice.value,
            date: DateTime.fromFormat(invoice.value.date, "dd/MM/yyyy").toISO()
        }) as unknown as {invoice: IInvoice, message: string};
        const newInvoiceId = invoiceResponse.invoice.id;


        dynamicData.value.status = "uploading-image";
        const imageResponse = await RequestAPI.post(`/invoices/${newInvoiceId}/image-upload`, {
            image: dynamicData.value.uploadedImageBase64
        }) as unknown as {message: string, image: {id: string, url: string}};

        const invoiceUpdateResponse = await RequestAPI.patch(`/invoices/${newInvoiceId}`, {
            image: imageResponse.image.id
        }) as unknown as {invoice: IInvoice, message: string};

        console.log(invoiceUpdateResponse)
    }
}
</script>

<style scoped lang="scss">
.image-holder{
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>