# Make Ionic Vue and Laravel work together!
## Where the Ionic source project goes? 
Your Ionic project lives inside the "ionic" folder in your Laravel project. It's a normal Ionic project, with some small tweaks to the build commands in `ionic/vite.config.ts` file.

## Compiling the Ionic project:
When you run `ionic build` inside the "ionic" folder, the compiled scripts and assets get output to the `resources/ionic` folder in your Laravel project. 

## Routing between Laravel and Ionic:
The Laravel routes in routes/web.php are set up to pass requests to `/app` over to the Ionic router. So if you have something like `/tabs/tab1` in Ionic, you'd request `localhost/app/tabs/tab1` in your browser and Laravel will route it correctly. The Ionic router runs normally for any routes under `/app`.