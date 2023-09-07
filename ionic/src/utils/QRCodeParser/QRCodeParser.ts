interface QRCodeParserResponse{
    isValid: boolean;
    content: any,
    qrCode: string  
}

interface QRCodeParserResponseBuy{
    isValid: boolean;
    content: {
        ruc: string;
        docCode: string;
        igv: string;
        price: string;
        date: string | null;
    } | null,
    qrCode: string
}
class QRCodeParser{
    public static parseBuyCode(qrCode: string): QRCodeParserResponseBuy{
        const containsTheSplitter = qrCode.split("|").length > 3;
        if (!containsTheSplitter) {
            return {
                content: null,
                isValid: false,
                qrCode: qrCode
            };
        }

        const parts = qrCode.split("|");
        let predifinedParts = {
            ruc: [0],
            docCode: [2, 3],
            igv: [4],
            price: [5],
            date: [6]
        }
        const data = {
            ruc: parts[0].trim(),
            docCode: (() => {
                const code = parts[predifinedParts.docCode[0]].trim();
                const serie = parts[predifinedParts.docCode[1]].trim();
                if (code.includes("-")){
                    predifinedParts.igv = [predifinedParts.igv[0] - 1];
                    predifinedParts.price = [predifinedParts.price[0] - 1];
                    predifinedParts.date = [predifinedParts.date[0] - 1];
                    return code;
                }
                return `${code}-${serie}`;
            
            })(),
            igv: (parts[predifinedParts.igv[0]].trim()),
            price: (parts[predifinedParts.price[0]].trim()),
            date: (() => {
                const data = parts[predifinedParts.date[0]].trim().split(" ")[0]
                //Should check for dates in those formats, is is not available, return null.: dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy, yyyy-mm-dd, yyyy/mm/dd, yyyy.mm.dd
                const dateRegex = /(\d{2}[-/.]\d{2}[-/.]\d{4})|(\d{4}[-/.]\d{2}[-/.]\d{2})/;
                const dateMatch = data.match(dateRegex);
                let datePreSanitizedValue = null
                if (dateMatch) {
                    //Define witch type of format is: dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy, yyyy-mm-dd, yyyy/mm/dd, yyyy.mm.dd:
                    const isDotStyle = data.includes(".");
                    const isSlashStyle = data.includes("/");
                    const isDashStyle = data.includes("-");

                    if (isSlashStyle){
                        const whereIsTheYear = data.split("/").findIndex((value) => value.length === 4);
                        const whereIsTheDay = data.split("/").findIndex((value, i) => {
                            return value.length === 2 && value !== "00" && parseInt(value) <= 31 && i !== 1
                        });
                        const whereIsTheMonth = 1

                        datePreSanitizedValue = `${data.split("/")[whereIsTheYear]}-${data.split("/")[whereIsTheMonth]}-${data.split("/")[whereIsTheDay]}`;
                    }else if (isDotStyle){
                        const whereIsTheYear = data.split(".").findIndex((value) => value.length === 4);
                        const whereIsTheDay = data.split(".").findIndex((value, i) => {
                            return value.length === 2 && value !== "00" && parseInt(value) <= 31 && i !== 1
                        });
                        const whereIsTheMonth = 1

                        datePreSanitizedValue = `${data.split(".")[whereIsTheYear]}-${data.split(".")[whereIsTheMonth]}-${data.split(".")[whereIsTheDay]}`;
                    }else if (isDashStyle){
                        const whereIsTheYear = data.split("-").findIndex((value) => value.length === 4);
                        const whereIsTheDay = data.split("-").findIndex((value, i) => {
                            return value.length === 2 && value !== "00" && parseInt(value) <= 31  && i !== 1
                        });
                        const whereIsTheMonth = 1

                        datePreSanitizedValue = `${data.split("-")[whereIsTheYear]}-${data.split("-")[whereIsTheMonth]}-${data.split("-")[whereIsTheDay]}`;
                    }
                }else{
                    return null;
                }

                if (datePreSanitizedValue){
                    //Check if has valid format: yyyy-mm-dd:
                    const dateRegex = /(\d{4}[-]\d{2}[-]\d{2})/;
                    const dateMatch = datePreSanitizedValue.match(dateRegex);
                    if (dateMatch){
                        return datePreSanitizedValue;
                    }
                    return null;
                }
                return null;
            })()
        }
        return {
            content: data,
            isValid: true,
            qrCode: qrCode
        };
    }
}


export {QRCodeParser}
export type {QRCodeParserResponse, QRCodeParserResponseBuy}