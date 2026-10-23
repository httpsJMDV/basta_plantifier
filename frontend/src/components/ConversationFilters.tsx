import {useEffect,useId,useLayoutEffect,useRef,useState,type CSSProperties,type KeyboardEvent} from 'react';
import {createPortal} from 'react-dom';
import {router} from '@inertiajs/react';
import {Check,ChevronLeft,ChevronRight,ListFilter} from 'lucide-react';
import {Button} from './UI';
import type {HistoryFilters} from '../types';

export const historyDefaults={q:'',status:'active',activity:'all',group:'date',sort:'activity'} as const;
export function localTimezone(){return Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'}
type FilterKey='status'|'activity'|'group'|'sort';
const filterRows:{key:FilterKey;label:string;options:{value:string;label:string}[]}[]=[
    {key:'status',label:'Status',options:[{value:'active',label:'Active'},{value:'archived',label:'Archived'},{value:'all',label:'All'}]},
    {key:'activity',label:'Last activity',options:[{value:'all',label:'All time'},{value:'today',label:'Today'},{value:'7',label:'Last 7 days'},{value:'30',label:'Last 30 days'}]},
    {key:'group',label:'Group by',options:[{value:'date',label:'Date'},{value:'none',label:'None'}]},
    {key:'sort',label:'Sort by',options:[{value:'activity',label:'Last activity'},{value:'created',label:'Created date'},{value:'title',label:'Title'}]},
];
export default function ConversationFilters({filters,compact=false}:{filters:HistoryFilters;compact?:boolean}){
    const [values,setValues]=useState(filters),[busy,setBusy]=useState(false),[open,setOpen]=useState(false);
    const [position,setPosition]=useState<CSSProperties>({}),[submenuPosition,setSubmenuPosition]=useState<CSSProperties>({});
    const [active,setActive]=useState<FilterKey|null>(null),[overlap,setOverlap]=useState(false);
    const popover=useRef<HTMLDetailsElement>(null),panel=useRef<HTMLDivElement>(null),submenu=useRef<HTMLDivElement>(null);
    const rows=useRef<Partial<Record<FilterKey,HTMLButtonElement|null>>>({}),focusSubmenu=useRef(false);
    const id=useId(),current=filterRows.find(row=>row.key===active);
    useEffect(()=>setValues(filters),[filters]);
    function close(returnFocus=false){
        if(popover.current)popover.current.open=false;
        setActive(null);setOpen(false);
        if(returnFocus)popover.current?.querySelector('summary')?.focus();
    }
    function closeSubmenu(){const key=active;setActive(null);if(key)rows.current[key]?.focus()}
    function showSubmenu(key:FilterKey,focus=false){
        focusSubmenu.current=focus;
        if(active===key){if(focus)submenu.current?.querySelector<HTMLButtonElement>('[aria-checked="true"]')?.focus();return}
        setActive(key);
    }
    useLayoutEffect(()=>{
        if(!open)return;
        const update=()=>{
            const anchor=popover.current?.querySelector('summary')?.getBoundingClientRect();if(!anchor)return;
            const width=Math.min(264,window.innerWidth-24),height=Math.min(panel.current?.scrollHeight??0,window.innerHeight-24);
            const top=anchor.bottom+8+height<=window.innerHeight-12?anchor.bottom+8:Math.max(12,anchor.top-height-8);
            setPosition({left:Math.max(12,Math.min(anchor.right-width,window.innerWidth-width-12)),top});
        };
        panel.current?.showPopover();update();rows.current.status?.focus();
        const outside=(event:Event)=>{const target=event.target as Node;if(!popover.current?.contains(target)&&!panel.current?.contains(target)&&!submenu.current?.contains(target))close()};
        const scroll=(event:Event)=>{if(!panel.current?.contains(event.target as Node)&&!submenu.current?.contains(event.target as Node))update()};
        document.addEventListener('pointerdown',outside,true);document.addEventListener('focusin',outside);
        window.addEventListener('scroll',scroll,true);window.addEventListener('resize',update);
        return()=>{document.removeEventListener('pointerdown',outside,true);document.removeEventListener('focusin',outside);window.removeEventListener('scroll',scroll,true);window.removeEventListener('resize',update)};
    },[open]);
    useLayoutEffect(()=>{
        if(!active||!open)return;
        const update=()=>{
            const anchor=rows.current[active]?.getBoundingClientRect(),parent=panel.current?.getBoundingClientRect();if(!anchor||!parent)return;
            const width=Math.min(180,window.innerWidth-24);
            const right=parent.right+5,left=parent.left-width-5;
            const fitsRight=right+width<=window.innerWidth-12,fitsLeft=left>=12;
            setOverlap(!fitsRight&&!fitsLeft);
            const height=Math.min(submenu.current?.scrollHeight??0,window.innerHeight-24);
            setSubmenuPosition({width,left:fitsRight?right:fitsLeft?left:Math.max(12,window.innerWidth-width-12),top:Math.max(12,Math.min(anchor.top-5,window.innerHeight-height-12))});
        };
        submenu.current?.showPopover();update();
        if(focusSubmenu.current)submenu.current?.querySelector<HTMLButtonElement>('[aria-checked="true"]')?.focus();
        focusSubmenu.current=false;
        window.addEventListener('resize',update);window.addEventListener('scroll',update,true);
        return()=>{window.removeEventListener('resize',update);window.removeEventListener('scroll',update,true)};
    },[active,open,position,overlap]);
    function apply(reset=false){router.get('/workspace/history',{...(reset?historyDefaults:values),timezone:localTimezone()},{preserveState:true,onStart:()=>setBusy(true),onFinish:()=>{setBusy(false);close()}})}
    function escape(event:KeyboardEvent){if(event.key==='Escape'){event.preventDefault();event.stopPropagation();active?closeSubmenu():close(true)}}
    function rowKeys(event:KeyboardEvent<HTMLButtonElement>,key:FilterKey){
        if(['ArrowRight','Enter',' '].includes(event.key)){event.preventDefault();showSubmenu(key,true)}
        else if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){
            event.preventDefault();setActive(null);const index=filterRows.findIndex(row=>row.key===key);
            const next=event.key==='Home'?0:event.key==='End'?filterRows.length-1:(index+(event.key==='ArrowDown'?1:-1)+filterRows.length)%filterRows.length;
            rows.current[filterRows[next].key]?.focus();
        }
    }
    function submenuKeys(event:KeyboardEvent<HTMLDivElement>){
        if(event.key==='Escape'||event.key==='ArrowLeft'){event.preventDefault();event.stopPropagation();closeSubmenu();return}
        if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){
            event.preventDefault();const options=Array.from(submenu.current?.querySelectorAll<HTMLButtonElement>('[role="menuitemradio"]')??[]);
            const index=options.indexOf(document.activeElement as HTMLButtonElement);
            const next=event.key==='Home'?0:event.key==='End'?options.length-1:(index+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;
            options[next]?.focus();
        }
    }
    const filtered=filters.status!==historyDefaults.status||filters.activity!==historyDefaults.activity||filters.group!==historyDefaults.group||filters.sort!==historyDefaults.sort;
    return <details ref={popover} className={'conversation-filters '+(compact?'is-compact ':'')+(filtered?'has-filters':'')} onToggle={event=>setOpen(event.currentTarget.open)} onKeyDown={escape}>
        <summary aria-label="Filter conversations" title="Filter conversations" aria-expanded={open}><ListFilter size={17}/>{!compact&&<span>Filters</span>}{filtered&&<span className="filter-active-dot" aria-hidden="true"/>}</summary>
        {open&&createPortal(<div ref={panel} popover="manual" className="filter-popover" role="region" aria-label="Conversation filters" style={position} onKeyDown={escape}>
            <div role="menu" aria-label="Filter settings">{filterRows.map((row,index)=><button key={row.key} ref={element=>{rows.current[row.key]=element}} id={id+'-'+row.key} type="button" role="menuitem" className={'filter-menu-row '+(active===row.key?'is-open ':'')+(index===2?'starts-group':'')} aria-label={row.label} aria-describedby={id+'-'+row.key+'-value'} aria-haspopup="menu" aria-expanded={active===row.key} aria-controls={active===row.key?id+'-options':undefined} disabled={busy} onPointerEnter={event=>{if(event.pointerType==='mouse')showSubmenu(row.key)}} onClick={()=>showSubmenu(row.key,true)} onKeyDown={event=>rowKeys(event,row.key)}><span>{row.label}</span><span id={id+'-'+row.key+'-value'} className="filter-row-value">{row.options.find(option=>option.value===values[row.key])?.label}</span><ChevronRight size={14}/></button>)}</div>
            <div className="filter-popover-actions" onPointerEnter={()=>setActive(null)}><button type="button" className="filter-reset" disabled={busy} onClick={()=>apply(true)}>Reset to defaults</button><Button type="button" busy={busy} onClick={()=>apply()}>Apply filters</Button></div>
        </div>,document.body)}
        {open&&current&&createPortal(<div ref={submenu} id={id+'-options'} popover="manual" className="filter-submenu" role="menu" aria-labelledby={id+'-'+current.key} style={submenuPosition} onKeyDown={submenuKeys}>
            {overlap&&<button type="button" className="filter-submenu-back" role="menuitem" onClick={closeSubmenu}><ChevronLeft size={14}/>{current.label}</button>}
            {current.options.map(option=><button key={option.value} type="button" role="menuitemradio" aria-checked={values[current.key]===option.value} disabled={busy} onClick={()=>{setValues(previous=>({...previous,[current.key]:option.value}));closeSubmenu()}}><span>{option.label}</span>{values[current.key]===option.value&&<Check size={15}/>}</button>)}
        </div>,document.body)}
    </details>;
}
