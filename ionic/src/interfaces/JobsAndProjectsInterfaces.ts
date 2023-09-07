interface IJob{
    name: string;
    code: string;
}
interface IProject{
    name: string;
    code: string;
    union: string;
}

export type { IJob, IProject };