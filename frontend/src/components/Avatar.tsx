import {useState} from 'react';

export default function Avatar({name,src,className=''}:{name:string;src?:string|null;className?:string}){
    const [failed,setFailed]=useState<string|null>(null);
    const words=name.trim().split(/\s+/).filter(Boolean);
    const initials=(words.length>1?Array.from(words[0])[0]+Array.from(words.at(-1)!)[0]:Array.from(words[0]??'?').slice(0,2).join('')).toUpperCase();
    return <span className={'avatar '+className} aria-hidden="true">{src&&failed!==src?<img src={src} alt="" onError={()=>setFailed(src)}/>:initials}</span>;
}
