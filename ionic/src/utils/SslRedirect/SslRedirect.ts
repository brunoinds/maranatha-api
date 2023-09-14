class SslRedirect{
    public static listen(){
        const location = new URL(window.location.href);
        if (location.protocol != 'https:' && location.hostname != 'localhost') {
            console.log(location);
            //Redirect to same page but with https protocol, Change url and reload page:
            window.location.protocol = 'https';
        }
    }
}


export default SslRedirect;