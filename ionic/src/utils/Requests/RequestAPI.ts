import axios from 'axios';

class RequestAPI{
    private static variables = {
        rootUrl: "http://localhost:8000/api",
        rootStorageUrl: "http://localhost:8000/storage"
    }
    public static get(url: string, parameters: any = {}): Promise<any>{
        return new Promise((resolve, reject) => {
            axios.get(this.variables.rootUrl + url, { params: parameters })
            .then((response) => {
                resolve(response.data);
            })
            .catch((error) => {
                reject({
                    code: error.response.status,
                    response: error.response.data
                });
            })
        })
    }
    public static post(url: string, body: any = {}, ): Promise<any>{
        return new Promise((resolve, reject) => {
            axios.post(this.variables.rootUrl + url, body)
            .then((response) => {
                resolve(response.data);
            })
            .catch((error) => {
                console.log(error)
                reject({
                    code: error.response.status,
                    response: error.response.data
                });
            })
        })
    }
    public static patch(url: string, body: any = {}): Promise<any>{
        return new Promise((resolve, reject) => {
            axios.patch(this.variables.rootUrl + url, body)
            .then((response) => {
                resolve(response.data);
            })
            .catch((error) => {
                reject({
                    code: error.response.status,
                    response: error.response.data
                });
            })
        })
    }
    public static put(url: string, parameters: any = {}): Promise<any>{
        return new Promise((resolve, reject) => {
            axios.put(this.variables.rootUrl + url, parameters)
            .then((response) => {
                resolve(response.data);
            })
            .catch((error) => {
                reject({
                    code: error.response.status,
                    response: error.response.data
                });
            })
        })
    }
    public static delete(url: string, parameters: any = {}): Promise<any>{
        return new Promise((resolve, reject) => {
            axios.delete(this.variables.rootUrl + url, { params: parameters })
            .then((response) => {
                resolve(response.data);
            })
            .catch((error) => {
                reject({
                    code: error.response.status,
                    response: error.response.data
                });
            })
        })
    }


    public static getStorageInBase64(url: string, parameters: any = {}): Promise<any>{
        return new Promise((resolve, reject) => {
            fetch(this.variables.rootStorageUrl +  url).then((response) => {
                response.blob().then((blob) => {
                    const reader = new FileReader();
                    reader.readAsDataURL(blob); 
                    reader.onloadend = function() {
                        const base64data = reader.result;     
                        resolve(base64data);
                    }
                })
            })
        })
    }
}


export { RequestAPI };