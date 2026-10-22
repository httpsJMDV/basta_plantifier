import {useRef,useState} from 'react';
import {router} from '@inertiajs/react';
export function notify(message:string,kind='success') { window.dispatchEvent(new CustomEvent('plantifier:notice',{detail:{message,kind}})); }
export async function api(url:string,data:Record<string,unknown>|FormData={},method='POST'):Promise<Record<string,any>> {
    const token=decodeURIComponent(document.cookie.split('; ').find(c=>c.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=') ?? '');
    const response=await fetch(url,{method,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-XSRF-TOKEN':token,...(data instanceof FormData ? {} : {'Content-Type':'application/json'})},body:method==='GET' ? undefined : data instanceof FormData ? data : JSON.stringify(data)});
    let result:Record<string,any>={}; try{result=await response.json()}catch{throw new Error('The server could not complete this request. Please try again.')}
    if(!response.ok) {
        if(response.status===419) throw new Error('Your session expired. Refresh this page and try again.');
        if(response.status===429) throw new Error('Too many requests. Please wait before trying again.');
        throw new Error(result.errors ? Object.values(result.errors).flat().join(' ') : result.message || 'This request could not be completed.');
    }
    return result;
}
export function multipart(values:Record<string,unknown>):FormData {const f=new FormData();for(const [k,v] of Object.entries(values)){if(v==null || v==='')continue;if(v instanceof File)f.append(k,v);else if(Array.isArray(v))v.forEach(x=>f.append(k+'[]',String(x)));else f.append(k,typeof v==='boolean'?(v?'1':'0'):String(v));}return f;}
export function useAction() {
    const pending=useRef(false);
    const [busy,setBusy]=useState(false);const [error,setError]=useState('');
    async function run(task:()=>Promise<unknown>,done?:()=>void) {if(pending.current)return;pending.current=true;setBusy(true);setError('');try{await task();done?.()}catch(e){setError(e instanceof Error?e.message:'Something went wrong.')}finally{pending.current=false;setBusy(false)}}
    return {busy,error,run,setError};
}
export function refresh(){router.reload();}
export function selectedCard(value:string){const [type,id]=value.includes(':')?value.split(':'):['plant',value];return {plant_record_id:type==='plant'?id||null:null,observation_id:type==='observation'?id:null,map_pin_id:type==='pin'?id:null}}
export function date(value:string){return new Date(value.length===10 ? value+'T00:00:00' : value.includes('T') ? value : value.replace(' ','T')+'Z').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});}
export function today(){const d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
