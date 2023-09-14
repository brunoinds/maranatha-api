class SslRedirect{
    public static listen(){
        const location = new URL(window.location.href);
        if (location.protocol != 'https:' && location.hostname != 'localhost') {
            //Redirect to same page but with https protocol:
            location.protocol = 'https';
        }
    }
}


export default SslRedirect;