import {useEffect,useId,useRef,useState} from 'react';
import {SmilePlus} from 'lucide-react';
import type {Reactions} from '../types';
import {api,useAction} from '../lib';
import {ErrorBox} from './UI';
const reactions=[{key:'fascinating',label:'Fascinating',emoji:'🌿'},{key:'crazy',label:'Crazy',emoji:'🤯'},{key:'loved',label:'Loved It',emoji:'💚'}];
export default function ReactionPicker({type,id,value}:{type:'post'|'comment';id:number;value:Reactions}){
    const [state,setState]=useState(value??{counts:{},selected:null});const [open,setOpen]=useState(false);const [pinned,setPinned]=useState(false);const [below,setBelow]=useState(false);const root=useRef<HTMLDivElement>(null);const paletteId=useId();const trigger=useRef<HTMLButtonElement>(null);const action=useAction();useEffect(()=>setState(value??{counts:{},selected:null}),[value]);
    useEffect(()=>{if(!open)return;const close=(event:PointerEvent)=>{if(!root.current?.contains(event.target as Node)){setOpen(false);setPinned(false)}};document.addEventListener('pointerdown',close);return()=>document.removeEventListener('pointerdown',close)},[open]);
    const reveal=()=>{setBelow((root.current?.getBoundingClientRect().top??200)<120);setOpen(true)};
    const selected=reactions.find(reaction=>reaction.key===state.selected);
    return <div ref={root} className="reaction-picker" role="group" aria-label="Social reactions" onMouseEnter={()=>{if(!open)reveal()}} onMouseLeave={()=>{if(!pinned&&!root.current?.contains(document.activeElement))setOpen(false)}} onBlur={event=>{if(!event.currentTarget.contains(event.relatedTarget)){setOpen(false);setPinned(false)}}} onKeyDown={event=>{if(event.key==='Escape'){event.stopPropagation();setOpen(false);setPinned(false);trigger.current?.focus()}}}>
        <button ref={trigger} type="button" className="reaction-trigger" aria-expanded={open} aria-controls={paletteId} onClick={()=>{setPinned(true);reveal()}}>{selected?<span aria-hidden="true">{selected.emoji}</span>:<SmilePlus size={17}/>} {selected?.label??'React'}</button>
        <div id={paletteId} className={'reaction-palette '+(below?'opens-below':'')} hidden={!open} aria-label="Choose a reaction">{reactions.map(reaction=><button type="button" key={reaction.key} data-reaction={reaction.key} aria-label={reaction.label} title={reaction.label} aria-pressed={state.selected===reaction.key} disabled={action.busy} onClick={()=>action.run(async()=>{const result=await api('/reactions/'+type+'/'+id,{reaction:reaction.key});setState(result.reactions);setOpen(false);setPinned(false);trigger.current?.focus()})}><span className="reaction-emoji" aria-hidden="true">{reaction.emoji}</span><span className="reaction-label">{reaction.label}</span></button>)}</div>
        <span className="reaction-totals" aria-live="polite">{reactions.filter(reaction=>state.counts[reaction.key]>0).map(reaction=><span key={reaction.key} title={reaction.label} aria-label={state.counts[reaction.key]+' '+reaction.label}>{reaction.emoji} {state.counts[reaction.key]}</span>)}</span><ErrorBox error={action.error}/></div>;
}
