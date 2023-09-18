import b64toBlob from 'b64-to-blob';


class BarcodeDetect{
    private barcodeDetector: BarcodeDetector;
    constructor(){
        const barcodeDetector = new BarcodeDetector({
            formats: [
                'qr_code',
            ]
        });
        this.barcodeDetector = barcodeDetector;
    }

    public async detect(image: ImageBitmap): Promise<Barcode[]>{
        const barcodes = await this.barcodeDetector.detect(image);
        return barcodes;
    }
    public async detectFromBase64(base64: string): Promise<Barcode[]>{
        //Convert base64 to ImageBitmap
        const blob = b64toBlob(base64, 'image/png');
        console.log(blob)
        const image = await createImageBitmap(blob);
        const barcodes = await this.detect(image);
        return barcodes;
    }

    public static isAvailable(): boolean{
        return 'BarcodeDetector' in window;
    }
}


export { BarcodeDetect }