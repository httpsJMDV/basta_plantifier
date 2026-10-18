import {useState} from 'react';
import {Leaf,ArrowUpRight} from 'lucide-react';
import {Modal,ErrorBox,Button} from './UI';
import type {Share} from '../types';
import {api,useAction} from '../lib';
export default function ShareCard({share}:{share:Share}){
    const [open,setOpen]=useState<Share|null>(null);const a=useAction();
    return <><button className="share-card" onClick={()=>a.run(async()=>{const result=await api('/shares/'+share.share_id,{},'GET');setOpen(result as Share)})}>{share.media_url?<img src={share.media_url} alt={share.common_name}/>:<span className="share-placeholder"><Leaf size={25}/></span>}<span><small>SHARED PLANT RECORD</small><strong>{share.common_name}</strong><i>{share.scientific_name??'Personal botanical record'}</i></span><ArrowUpRight size={17}/></button><ErrorBox error={a.error}/>{open&&<Modal title={open.common_name} onClose={()=>setOpen(null)}>{open.media_url&&<img className="share-detail-image" src={open.media_url} alt={open.common_name}/>}<i>{open.scientific_name}</i><p className="muted">{open.source}</p><p className="preserve-lines">{open.description}</p><h3>Care</h3><p className="preserve-lines">{open.care}</p><h3>Safety</h3><p className="preserve-lines">{open.safety}</p></Modal>}</>;
}
