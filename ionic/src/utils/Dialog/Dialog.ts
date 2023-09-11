import { modalController, ModalOptions } from "@ionic/vue";
import { ComponentRef } from "@ionic/core";
import { EventEmitter, EventHandler } from "@billjs/event-emitter";
import _ from "lodash";
import { Ref } from "vue";


interface DialogModalOptions{
    presentingElement?: Ref<HTMLElement | null>;
    showBackdrop?: boolean;
    backdropDismiss?: boolean;
    cssClass?: string | string[];
    animated?: boolean;
    canDismiss?: boolean | ((data?: any, role?: string) => Promise<boolean>);
    mode?: 'ios' | 'md';
    keyboardClose?: boolean;
    id?: string;
    htmlAttributes?: { [key: string]: any };
    breakpoints?: number[];
    initialBreakpoint?: number;
    backdropBreakpoint?: number;
    handle?: boolean;
}

interface DialogOptions{
    props?: any,
    modalControllerOptions?: DialogModalOptions,
    onLoaded?: ($this:Dialog) => void,
    onClosedResponse?: (response:any) => void
}

class Dialog{
    private component:ComponentRef;
    private modal:HTMLIonModalElement|null = null;
    private eventEmitter:EventEmitter;
    private options:DialogOptions;
    constructor(component:ComponentRef, options:DialogOptions){
        this.component = component;
        this.eventEmitter = new EventEmitter();

        this.options = _.assignIn({
            onLoaded: () => {},
            onClosedResponse: () => {},
            props: {},
            modalControllerOptions: {}
        }, options) as unknown as DialogOptions;
        this.createModal().then(() => {});
    }

    private async createModal(){
        const currentInstance = this;

        const modalControllerOptions = (() => {
            let modalControllerOptions = {
                component: this.component,
                componentProps: {
                    ...this.options.props,
                    emitter: this.eventEmitter
                },
                ...this.options.modalControllerOptions
            }

            if (modalControllerOptions.presentingElement){
                if ((modalControllerOptions.presentingElement as any).value){
                    modalControllerOptions.presentingElement = (modalControllerOptions.presentingElement as any).value.$el;
                }
            }
            return modalControllerOptions;
        })();
        
        this.modal = await modalController.create(modalControllerOptions);
        this.on('close', () => {
            this.close();
        })
        this.modal.onDidDismiss().then((response) => {
            if (this.options.onClosedResponse){
                this.options.onClosedResponse(response);
            }
        })
        await this.open();
        if (this.options.onLoaded){
            this.options.onLoaded(currentInstance);
        }
    }

    public on(event:string, callback:EventHandler){
        this.eventEmitter.on(event, (...args) => {
            callback(...args);
        });
    }
    public emit(event:string, ...args:any[]){
        this.eventEmitter.fire(event, ...args);
    }

    public async open(){
        return await this.modal?.present();
    }
    public async close(){
        return await this.modal?.dismiss();
    }

    public static show(component:ComponentRef, options:DialogOptions){
        const dialog = new Dialog(component, options);
        return dialog;
    }
}

const DialogEventEmitter = EventEmitter;

export { Dialog, DialogEventEmitter };
export type { DialogOptions };