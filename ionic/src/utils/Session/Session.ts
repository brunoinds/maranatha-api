import { RequestAPI } from '@/utils/Requests/RequestAPI';
import { TStorage } from '@/utils/Toolbox/TStorage';

interface SessionUserData{
    id: number;
    username: string;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
    token: string;
}
class Session{
    private static session: Session|null = null;
    private static isInitialized: boolean = false;

    private sessionUserData: SessionUserData;

    constructor(sessionUserData: SessionUserData){
        this.sessionUserData = sessionUserData;
    }

    public name(){
        return this.sessionUserData.name;
    }
    public username(){
        return this.sessionUserData.username;
    }
    public email(){
        return this.sessionUserData.email;
    }
    public roles(){
        return this.sessionUserData.roles;
    }
    public permissions(){
        return this.sessionUserData.permissions;
    }
    public token(){
        return this.sessionUserData.token;
    }
    public id(){
        return this.sessionUserData.id;
    }

    
    public static async login(username:string, password:string){
        if (await Session.isLogged()){
            throw new Error("Already logged");
        }

        try {
            const response = await RequestAPI.post('/login', {
                username: username,
                password: password
            });
            const sessionData: SessionUserData = {
                id: response.user.id,
                username: response.user.username,
                name: response.user.name,
                email: response.user.email,
                roles: response.user.roles,
                permissions: response.user.permissions,
                token: response.token
            }
            Session.session = new Session(sessionData);
            await TStorage.clearBucket("session");
            await TStorage.load("session", {
                token: response.token,
                userData: response.user
            });

        } catch (error:any) {
            if (error.code == 401){
                throw new Error("Invalid credentials");
            }else{
                throw new Error(error.response.message);
            }
        }
    }
    public async logout(){
        const response = await RequestAPI.post('/logout', {});
        await TStorage.clearBucket("session");
        Session.session = null;
    }
    public static async getCurrentSession(){
        await Session.init();
        return Session.session;
    }
    public static async init(){
        if (this.isInitialized){
            return;
        }
        const sessionStorage = await TStorage.load("session", {
            token: null,
            userData: null
        });

        if (sessionStorage.data.token != null){
            const sessionData: SessionUserData = {
                id: sessionStorage.data.userData.id,
                username: sessionStorage.data.userData.username,
                email: sessionStorage.data.userData.email,
                roles: sessionStorage.data.userData.roles,
                permissions: sessionStorage.data.userData.permissions,
                token: sessionStorage.data.token
            }
            Session.session = new Session(sessionData);
        }else{
            Session.session = null;
        }
        this.isInitialized = true;
    }
    public static async isLogged(){
        await Session.init();
        return Session.session != null;
    }
}

export {Session}
export type {SessionUserData}