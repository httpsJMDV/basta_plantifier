import {useState} from 'react';
import {Copy,Share2,Check} from 'lucide-react';
import type {Post} from '../types';
import {Button,Modal,Field,ErrorBox} from './UI';

export default function PostShare({post}:{post:Pick<Post,'id'|'name'>}){
    const [open,setOpen]=useState(false),[copied,setCopied]=useState(false),[error,setError]=useState('');
    const url=new URL('/community?post='+post.id,window.location.origin).href;
    return <div className="post-share-action"><button type="button" className="text-button" onClick={()=>{setCopied(false);setError('');setOpen(true)}}><Share2 size={16}/>Share</button>{open&&<Modal title="Share this discovery" onClose={()=>setOpen(false)}><p className="muted">Share a link to {post.name}’s post. Recipients must sign in. The author’s current sharing permissions still apply.</p><Field label="Post link"><input readOnly value={url} onFocus={event=>event.target.select()}/></Field><div className="form-actions"><Button type="button" variant="secondary" onClick={async()=>{try{await navigator.clipboard.writeText(url);setCopied(true)}catch{setError('Select the link above and copy it to share.')}}}>{copied?<Check size={16}/>:<Copy size={16}/>} {copied?'Copied':'Copy link'}</Button>{typeof navigator.share==='function'&&<Button type="button" onClick={async()=>{try{await navigator.share({title:'PlantifierAI discovery by '+post.name,url})}catch(error){if(!(error instanceof DOMException&&error.name==='AbortError'))setError('Sharing is unavailable. Copy the link instead.')}}}><Share2 size={16}/>Share to…</Button>}</div><ErrorBox error={error}/></Modal>}</div>;
}
