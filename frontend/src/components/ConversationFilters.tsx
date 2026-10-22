import {useEffect,useLayoutEffect,useRef,useState,type CSSProperties} from 'react';
import {createPortal} from 'react-dom';
import {router} from '@inertiajs/react';
import {SlidersHorizontal} from 'lucide-react';
import {Button,Field} from './UI';
import CustomSelect from './CustomSelect';
import type {HistoryFilters} from '../types';

export const historyDefaults={q:'',status:'active',activity:'all',group:'date',sort:'activity'} as const;
export function localTimezone(){return Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'}
export default function ConversationFilters({filters}:{filters:HistoryFilters}){
    const [values,setValues]=useState(filters),[busy,setBusy]=useState(false),[open,setOpen]=useState(false);
    const [position,setPosition]=useState<CSSProperties>({});
    const popover=useRef<HTMLDetailsElement>(null),panel=useRef<HTMLDivElement>(null);
    useEffect(()=>setValues(filters),[filters]);
    function close(returnFocus=false){
        if(popover.current)popover.current.open=false;
        setOpen(false);
        if(returnFocus)popover.current?.querySelector('summary')?.focus();
    }
    useLayoutEffect(()=>{
        if(!open)return;
        const update=()=>{
            const anchor=popover.current?.querySelector('summary')?.getBoundingClientRect();
            if(!anchor)return;
            const width=Math.min(290,window.innerWidth-24);
            const height=Math.min(panel.current?.scrollHeight??0,window.innerHeight-24);
            const top=anchor.bottom+8+height<=window.innerHeight-12?anchor.bottom+8:Math.max(12,anchor.top-height-8);
            setPosition({left:Math.max(12,Math.min(anchor.right-width,window.innerWidth-width-12)),top});
        };
        panel.current?.showPopover();
        update();
        panel.current?.querySelector<HTMLButtonElement>('[role="combobox"]')?.focus();
        const outside=(event:Event)=>{
            const target=event.target as Element;
            if(!popover.current?.contains(target)&&!panel.current?.contains(target)&&!target.closest?.('.custom-select-menu'))close();
        };
        const scroll=(event:Event)=>{if(!panel.current?.contains(event.target as Node))update()};
        document.addEventListener('pointerdown',outside,true);
        document.addEventListener('focusin',outside);
        window.addEventListener('scroll',scroll,true);
        window.addEventListener('resize',update);
        return()=>{
            document.removeEventListener('pointerdown',outside,true);
            document.removeEventListener('focusin',outside);
            window.removeEventListener('scroll',scroll,true);
            window.removeEventListener('resize',update);
        };
    },[open]);
    function apply(reset=false){router.get('/workspace/history',{...(reset?historyDefaults:values),timezone:localTimezone()},{preserveState:true,onStart:()=>setBusy(true),onFinish:()=>{setBusy(false);close()}})}
    const escape=(event:React.KeyboardEvent)=>{if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close(true)}};
    return <details ref={popover} className="conversation-filters" onToggle={event=>setOpen(event.currentTarget.open)} onKeyDown={escape}><summary aria-label="Filter conversations" aria-expanded={open}><SlidersHorizontal size={17}/><span>Filters</span></summary>{open&&createPortal(<div ref={panel} popover="manual" className="filter-popover" role="region" aria-label="Conversation filters" style={position} onKeyDown={escape}><Field label="Status"><CustomSelect value={values.status} onChange={value=>setValues({...values,status:value as HistoryFilters['status']})}><option value="active">Active</option><option value="archived">Archived</option><option value="all">All</option></CustomSelect></Field><Field label="Last activity"><CustomSelect value={values.activity} onChange={value=>setValues({...values,activity:value as HistoryFilters['activity']})}><option value="all">All time</option><option value="today">Today</option><option value="7">Last 7 days</option><option value="30">Last 30 days</option></CustomSelect></Field><Field label="Group by"><CustomSelect value={values.group} onChange={value=>setValues({...values,group:value as HistoryFilters['group']})}><option value="date">Date</option><option value="none">None</option></CustomSelect></Field><Field label="Sort by"><CustomSelect value={values.sort} onChange={value=>setValues({...values,sort:value as HistoryFilters['sort']})}><option value="activity">Last activity</option><option value="created">Created date</option><option value="title">Title</option></CustomSelect></Field><p>Dates use your local timezone. Filters also apply to the sidebar preview.</p><Button type="button" busy={busy} onClick={()=>apply()}>Apply filters</Button><button type="button" className="text-button" disabled={busy} onClick={()=>apply(true)}>Reset to defaults</button></div>,document.body)}</details>;
}
