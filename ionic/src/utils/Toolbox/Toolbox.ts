class Toolbox{
    public static generateId(): string{
        return Math.random().toString(36).substr(2, 9);
    }
}


export {Toolbox}