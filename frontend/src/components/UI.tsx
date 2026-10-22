import {useEffect,useRef,useId,useState,isValidElement,cloneElement,type ReactNode,type ReactElement} from 'react';
import {Link} from '@inertiajs/react';
import CustomSelect from './CustomSelect';
import {X,Leaf,ArrowLeft,ArrowRight,LoaderCircle,Flag} from 'lucide-react';
import type {Page} from '../types';
import {api,notify,useAction} from '../lib';
export function Brand(){return <span className="brand"><span className="brand-mark"><Leaf size={23}/></span><span>Plantifier<span className="brand-ai">AI</span><small>YOUR BOTANICAL COMPANION</small></span></span>}
export function Button({children,busy=false,variant='',...props}:React.ButtonHTMLAttributes<HTMLButtonElement>&{busy?:boolean;variant?:string}){return <button {...props} disabled={busy||props.disabled} className={'button '+variant+' '+(props.className??'')}>{busy?<LoaderCircle className="spin" size={17}/>:null}{children}</button>}
export function Field({label,children,hint}: {label:string;children:ReactNode;hint?:string}) {
    const generatedId=useId();
    const control=children as ReactElement<{id?:string;'aria-describedby'?:string}>;
    const id=isValidElement(control)&&control.props.id?control.props.id:generatedId;
    return <div className="field"><label htmlFor={id}>{label}</label>{isValidElement(control)?cloneElement(control,{id,'aria-describedby':hint?id+'-hint':undefined}):children}{hint&&<small id={id+'-hint'}>{hint}</small>}</div>;
}
export function ErrorBox({error}:{error?:string}){return error?<div className="error-box" role="alert">{error}</div>:null}
export function Empty({title,body,children,icon}: {title:string;body:string;children?:ReactNode;icon?:ReactNode}){return <div className="empty-state"><div className="empty-icon">{icon??<Leaf size={30}/>}</div><h2>{title}</h2><p>{body}</p>{children}</div>}
export function Modal({title,children,onClose,wide=false}: {title:string;children:ReactNode;onClose:()=>void;wide?:boolean}){
    const headingId=useId();
    const ref=useRef<HTMLDialogElement>(null);
    useEffect(()=>{const d=ref.current;d?.showModal();return()=>{d?.close()}},[]);
    return <dialog ref={ref} aria-labelledby={headingId} className={'modal '+(wide?'wide':'')} onCancel={e=>{e.preventDefault();onClose()}} onClick={e=>{if(e.target===ref.current)onClose()}}><div className="modal-heading"><h2 id={headingId}>{title}</h2><button type="button" className="icon-button" onClick={onClose} aria-label="Close dialog"><X size={20}/></button></div>{children}</dialog>
}
export function Pagination({page}:{page:Page<unknown>}){if(page.last_page<=1)return null;return <nav className="pagination" aria-label="Pagination">{page.prev_page_url?<Link href={page.prev_page_url} preserveScroll><ArrowLeft size={16}/>Previous</Link>:<span/>}<span>Page {page.current_page} of {page.last_page}</span>{page.next_page_url?<Link href={page.next_page_url} preserveScroll>Next<ArrowRight size={16}/></Link>:<span/>}</nav>}
export function ReportButton({type,id}:{type:string;id:number}){
    const a=useAction();
    const [open,setOpen]=useState(false),[reason,setReason]=useState('Safety concern'),[details,setDetails]=useState('');
    return <><button type="button" className="text-button muted" aria-label="Report content" onClick={()=>{a.setError('');setOpen(true)}}><Flag size={14}/></button>{open&&<Modal title="Report content" onClose={()=>setOpen(false)}><p>Reporting {type} #{id}. Only this item is submitted to the existing moderation workflow.</p><form className="stack-form" onSubmit={event=>{event.preventDefault();a.run(async()=>{await api('/reports',{subject_type:type,subject_id:id,reason:reason+': '+details.trim()});setOpen(false);setDetails('');notify('Report submitted for review.')})}}><Field label="Reason"><CustomSelect value={reason} onChange={setReason}>{['Safety concern','Harassment','Privacy concern','Spam','Other'].map(label=><option key={label} value={label}>{label}</option>)}</CustomSelect></Field><Field label="Details" hint="Describe this item only. At least 10 characters."><textarea required minLength={10} maxLength={2900} rows={4} value={details} onChange={event=>setDetails(event.target.value)}/></Field><ErrorBox error={a.error}/><div className="form-actions"><Button type="button" variant="secondary" onClick={()=>setOpen(false)}>Cancel</Button><Button type="submit" busy={a.busy}>Submit report</Button></div></form></Modal>}</>
}
export function Confirm({title,body,onClose,onConfirm,busy,error}:{title:string;body:string;onClose:()=>void;onConfirm:()=>void;busy:boolean;error?:string}){return <Modal title={title} onClose={onClose}><p className="muted">{body}</p><ErrorBox error={error}/><div className="form-actions"><Button variant="secondary" onClick={onClose}>Cancel</Button><Button variant="danger" busy={busy} onClick={onConfirm}>Delete</Button></div></Modal>}
