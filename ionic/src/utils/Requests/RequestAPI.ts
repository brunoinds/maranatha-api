import axios from 'axios';

import { TStorage } from '@/utils/Toolbox/TStorage';
import { Session } from '@/utils/Session/Session';
class RequestAPI{
    private static variables = {
        rootUrl: "http://localhost:8000/api",
        rootStorageUrl: "http://localhost:8000/storage"
    }
    public static get(url: string, parameters: any = {}): Promise<any>{
        return new Promise(async (resolve, reject) => {
            axios.get(this.variables.rootUrl + url, { params: parameters, 
                headers: {
                    'Authorization': await RequestAPI.getAuthHeader()
                }
            }).then((response) => {
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
        return new Promise(async (resolve, reject) => {
            axios.post(this.variables.rootUrl + url, body, {
                headers: {
                    'Authorization': await RequestAPI.getAuthHeader()
                }
            })
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
        return new Promise(async (resolve, reject) => {
            axios.patch(this.variables.rootUrl + url, body, {
                headers: {
                    'Authorization': await RequestAPI.getAuthHeader()
                }
            })
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
        return new Promise(async (resolve, reject) => {
            axios.put(this.variables.rootUrl + url, parameters, {
                headers: {
                    'Authorization': await RequestAPI.getAuthHeader()
                }
            })
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
        return new Promise(async (resolve, reject) => {
            axios.delete(this.variables.rootUrl + url, { params: parameters,
            headers: {
                'Authorization': await RequestAPI.getAuthHeader()
            }})
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

    private static async getAuthHeader(): Promise<any>{
        return new Promise(async (resolve, reject) => {
            if (await Session.isLogged()){
                resolve('Bearer ' + (await Session.getCurrentSession())?.token());
            }else{
                resolve(undefined);
            }
        })
    }
}


export { RequestAPI };