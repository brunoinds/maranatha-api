class SslRedirect{
    public static listen(){
        console.log("Listening");
        const location = new URL(window.location.href);
        console.log(location);
        if (location.protocol != 'https:' && location.hostname != 'localhost') {
            //Redirect to same page but with https protocol, Change url and reload page:
            window.location.protocol = 'https';
        }
    }
}


export default SslRedirect;