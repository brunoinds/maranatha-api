import * as IonicStorage from '@ionic/storage';

const isValidJSON = (str:string) => {
    try {
        JSON.parse(str);
        return true;
    } catch (e) {
        return false;
    }
}

export class TStorage{
    private bucketTemplate:object;
    private bucketName:string;
    private bucketData:object|null|any = null;
    private ionicStorage:IonicStorage.Storage|null = null;
    private constructor(bucket:string, template:object = {}){
        this.bucketTemplate = template;
        this.bucketName = bucket;
    }

    private initBucketData():Promise<void>{
        const $this = this;
        return new Promise((resolve) => {
            TStorage.exists($this.bucketName).then((exists) => {
                if (!exists){
                    $this.reset().then(() => {
                        resolve();
                    });
                }else{
                    $this.ionicStorage?.get($this.bucketName).then((dataCompressed) => {
                        const decompressedData = dataCompressed
                        if (decompressedData){
                            if (!isValidJSON(decompressedData)){
                                $this.reset().then(() => {
                                    resolve();
                                });
                                return;
                            }
                            $this.bucketData = JSON.parse(decompressedData);
                            resolve();
                        }else{
                            $this.reset().then(() => {
                                resolve();
                            });
                        }
                    })
                }
            })
        })
    }

    set data(data:{[key:string]:any}){
        this.bucketData = data;
    }
    get data():{[key:string]:any}{
        if (this.bucketData == null){
            return {};
        }
        return this.bucketData;
    }


    public async save():Promise<TStorage>{
        const $this = this;
        return new Promise((resolve) => {
            TStorage.exists($this.bucketName).then((exists) => {
                if (!exists){
                    $this.reset().then(() => {
                        save()
                    });
                }else{
                    save()
                }

                function save(){
                    const compressedData = JSON.stringify($this.data)
                    $this.ionicStorage?.set($this.bucketName, compressedData).then(() => {
                        resolve($this);
                    });
                }
            });
        })
    }
    public async reset():Promise<TStorage>{
        const $this = this;
        return new Promise((resolve) => {
            const compressedData = JSON.stringify($this.bucketTemplate);
            $this.ionicStorage?.set($this.bucketName, compressedData).then(() => {
                $this.bucketData = JSON.parse(JSON.stringify($this.bucketTemplate));
                resolve($this);
            });
        })
    }
    public async delete():Promise<void>{
        const $this = this;

        return new Promise((resolve) => {
            $this.ionicStorage?.remove($this.bucketName).then(() => {
                resolve();
            })
        })
    }
    public getSize():number{
        const compressed = JSON.stringify(this.data);
        return (new Blob([compressed])).size * 2
    }

    public static async exists(bucket:string):Promise<boolean>{
        return new Promise((resolve, reject) => {
            let response = false;
            const storage = new IonicStorage.Storage();
            storage.create().then((storage) => {
                storage.keys().then((keys) => {
                    response = keys.includes(bucket);
                    resolve(response);
                })
            })
        })
    }
    public static async getBucketsNames():Promise<string[]>{
        return new Promise((resolve, reject) => {
            const store = new IonicStorage.Storage();
            store.create().then((storage) => {
                storage.keys().then((keys) => {
                    resolve(keys);
                })
            })
        })
    }
    public static getOcupiedSpace():Promise<{
        buckets: Array<{name: string, size:number, sizeReadable: string}>, 
        size: number, 
        sizeReadable: string
    }>{
        const formatSize = sizeFormatter();
        return new Promise((resolve) => {
            let buckets:any = [];
            const store = new IonicStorage.Storage();
            store.create().then((storage) => {
                TStorage.getBucketsNames().then((bucketsNames) => {
                    bucketsNames.forEach((bucketName) => {
                        storage.get(bucketName).then((data) => {
                            buckets.push({
                                name: bucketName,
                                size: (new Blob([data])).size * 2,
                                sizeReadable: formatSize((new Blob([data])).size * 2)
                            })
                        })
                    })

                    const timer = setInterval(() => {
                        if (buckets.length == bucketsNames.length){
                            clearInterval(timer);
                            const size = buckets.reduce((a:any, b:any) => a + b.size, 0)
                            resolve({
                                buckets: buckets,
                                size: size,
                                sizeReadable: formatSize(size)
                            })
                        }
                    }, 100)
                })
            })
        })
    }
    public static async load(bucket:string, template:object = {}):Promise<TStorage>{
        return new Promise((resolve) => {
            const instance = new TStorage(bucket, template);
            const store = new IonicStorage.Storage();
            store.create().then((storage) => {
                instance.ionicStorage = storage;
                instance.initBucketData().then(() => {
                    resolve(instance);
                })
            })
        })
    }
    public static clearAllBuckets():Promise<void>{
        return new Promise((resolve) => {
            const store = new IonicStorage.Storage();
            store.create().then(() => {
                store.clear().then(() => {
                    resolve();
                })
            })
        })
    }
    public static async clearBucket(bucket:string):Promise<void>{
        return new Promise((resolve) => {
            const store = new IonicStorage.Storage();
            store.create().then(() => {
                store.remove(bucket).then(() => {
                    resolve();
                })
            })
        })
    }

    public static placeholder():TStorage{
        return new TStorage('', {});
    }
}