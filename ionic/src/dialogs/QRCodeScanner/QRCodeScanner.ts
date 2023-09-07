import { modalController } from "@ionic/vue";
import QRCodeScannerModal from "@/dialogs/QRCodeScanner/QRCodeScannerModal.vue";
import { EventEmitter } from "@billjs/event-emitter";
class QRCodeScanner{
    private modal:any = null;
    private emitter:any = null;
    constructor(){
        const emitter = new EventEmitter();
        this.emitter = emitter;
        modalController.create({
            component: QRCodeScannerModal,
            componentProps: {
                emitter: emitter
            }
        }).then((modal) => {
            this.modal = modal;
            this.show();
            emitter.on("close", () => {
                this.modal.dismiss();
            })
        })
    }

    public show(){
        this.modal.present();
    }
    public close(){
        this.modal.dismiss();
    }
    public onScan():Promise<string>{
        const emitter = this.emitter;
        return new Promise((resolve, reject) => {
            emitter.on("scan", (result:any) => {
                resolve(result.data)
            })
        })
    }

    public static open(): QRCodeScanner{
        return new QRCodeScanner();
    }
}


export { QRCodeScanner };