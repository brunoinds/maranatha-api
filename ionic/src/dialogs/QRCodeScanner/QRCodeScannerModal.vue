<template>
    <ion-page ref="page">
        <ion-header>
            <ion-toolbar>
				<ion-buttons slot="start">
					<ion-button @click="close">Cancelar</ion-button>
				</ion-buttons>
				<ion-title>Escanear Código QR</ion-title>
			</ion-toolbar>
        </ion-header>
        <ion-content :scrollX="true" :id="cameraAreaId">
            <canvas id="canvasElement" width="412" height="412"></canvas>

            <video id="preview-qr-scanner" playsInline webkit-playsInline></video>
        </ion-content>
        <ion-footer v-if="false">
            <ion-select v-model="selectedCamera">
                    <ion-select-option v-for="camera in camerasList" :value="camera">{{camera.name}}</ion-select-option>
                </ion-select>
        </ion-footer>
    </ion-page>
</template>

<script lang="ts">
import {
    alertController,
    IonAccordion,
    IonAccordionGroup, IonBackButton, IonButton, IonButtons, IonCheckbox, IonContent, IonDatetime, IonDatetimeButton, IonFooter, IonHeader, IonIcon, IonInput, IonItem, IonItemOption, IonItemOptions, IonItemSliding, IonLabel, IonList, IonListHeader, IonModal, IonNote, IonPage, IonRange, IonSelect,
    IonSelectOption, IonTextarea, IonTitle, IonToolbar
} from '@ionic/vue';
import { defineComponent, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import {Toolbox} from "@/utils/Toolbox/Toolbox";

export default defineComponent({
    name: 'QRCodeScannerDialog',
    components: { IonFooter, IonListHeader, IonRange, IonDatetimeButton, IonNote, IonModal, IonTextarea, IonCheckbox, IonHeader, IonToolbar, IonTitle, IonContent, IonPage,  IonButton, IonItem, IonList, IonLabel, IonInput, IonButtons, IonBackButton,  IonIcon, IonAccordion, IonAccordionGroup, IonDatetime, IonSelect, IonSelectOption, IonItemSliding, IonItemOptions, IonItemOption },
	props: ['emitter'],
    setup (props) {
        let camerasList = ref<any>([]);
        let selectedCamera = ref(null);
        let currentScanner:any = null;
        let cameraAreaId = ref<string>(Toolbox.generateId());
        let elementSizes = ref({width: 0, height: 0})
        onMounted(() => {
            startScanning();

            nextTick(() => {
                setTimeout(() => {
                    recalculate();
                }, 1000);
            })
            
        })

        const recalculate = () => {
            const element = document.getElementById(cameraAreaId.value);
            const preElementSizes = element?.getBoundingClientRect();
            elementSizes.value = {
                width: preElementSizes?.width || 0,
                height: preElementSizes?.height || 0
            }
            const canvas = document.getElementById('canvasElement') as unknown as HTMLCanvasElement;
            canvas.width = elementSizes.value.width;
            canvas.height = elementSizes.value.height;
        }
        const close = () => {
            props.emitter.fire("close");
        }
        const startScanning = async () => {
            let scanner = new Instascan.Scanner({ video: document.getElementById('preview-qr-scanner') });
            currentScanner = scanner;
            scanner.addListener('scan', function (content:any) {
                props.emitter.fire('scan', content);
                props.emitter.fire("close");
                currentScanner.stop();
            });
            Instascan.Camera.getCameras({
                video: {
                    facingMode: (() => {
                        //If is desktop device, return undefined, else, return environment
                        return window.innerWidth > 768 ? undefined : 'environment';
                    })()
                }
            }).then(function (cameras:any) {
                camerasList.value = cameras;
                if (cameras.length > 0) {
                    selectedCamera.value = cameras[0];
                }
            }).catch(function (e:any) {
                console.error(e);
            });

            const video = document.getElementById('preview-qr-scanner') as unknown as HTMLVideoElement;
            const canvas = document.getElementById('canvasElement') as unknown as HTMLCanvasElement;
            const ctx = canvas.getContext('2d') as unknown as CanvasRenderingContext2D;

            // When the video is playing, update the canvas with each frame
            video.addEventListener('play', function () {
                const drawFrame = () => {
                    if (video.paused || video.ended) {
                        return;
                    }

                    // Draw the current video frame on the canvas without stretching it:
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    

                    // Get the width and height to use 
                    // Get canvas width and height 
                    const width = canvas.width;
                    const height = canvas.height;

                    // Set square properties
                    const squareSize = 230; 
                    const borderWidth = 5;
                    const borderColor = '#ffc107';

                    // Calculate square coordinates to center 
                    const x = (width - squareSize) / 2;
                    const y = (height - squareSize) / 2;

                    // Draw rounded square
                    ctx.lineWidth = borderWidth;
                    ctx.strokeStyle = borderColor;
                    ctx.beginPath();
                    ctx.moveTo(x + borderWidth/2, y);
                    ctx.arcTo(x + squareSize, y, x + squareSize, y + squareSize, 10); 
                    ctx.arcTo(x + squareSize, y + squareSize, x, y + squareSize, 10);
                    ctx.arcTo(x, y + squareSize, x, y, 10);
                    ctx.arcTo(x, y, x + squareSize, y, 10);
                    ctx.closePath();
                    ctx.stroke();

                    // Request the next frame
                    requestAnimationFrame(drawFrame);
                };

                // Start the loop to draw frames on the canvas
                drawFrame();
            }, false);
        }
        watch(selectedCamera, (value) => {
            currentScanner.stop();
            currentScanner.start(value);
        })
        onUnmounted(() => {
            currentScanner.stop();
        })

        return {
            camerasList,
            selectedCamera,
            close,
            cameraAreaId
        }
    }
});
</script>


<style scoped lang="scss">
#preview-qr-scanner{
    display: none;
}
</style>