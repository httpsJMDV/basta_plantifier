import {Children,isValidElement,useEffect,useLayoutEffect,useRef,useState,useId,type CSSProperties,type ReactElement,type ReactNode} from 'react';
import {createPortal} from 'react-dom';
import {Check,ChevronDown} from 'lucide-react';

type Props={value:string|number;onChange:(value:string)=>void;children:ReactNode;id?:string;name?:string;required?:boolean;disabled?:boolean;className?:string;'aria-label'?:string;'aria-describedby'?:string};

// Adapted from Velure's CustomSelect, with native popovers for modal-safe positioning.
export default function CustomSelect({value,onChange,children,id,name,required,disabled,className='',...aria}:Props){
    const generatedId=useId(),controlId=id??generatedId,listId=controlId+'-options';
    const options=Children.toArray(children).filter(isValidElement).map(child=>{
        const option=child as ReactElement<{value:string|number;children:ReactNode;disabled?:boolean}>;
        return {value:String(option.props.value),label:option.props.children,disabled:option.props.disabled};
    });
    const selected=options.findIndex(option=>option.value===String(value));
    const [open,setOpen]=useState(false),[active,setActive]=useState(0),[position,setPosition]=useState<CSSProperties>({});
    const trigger=useRef<HTMLButtonElement>(null),list=useRef<HTMLDivElement>(null),validation=useRef<HTMLSelectElement>(null),search=useRef({text:'',time:0});
    const choose=(index:number)=>{const option=options[index];if(!option||option.disabled)return;onChange(option.value);setOpen(false);trigger.current?.focus()};
    const show=(index=selected)=>{if(disabled)return;setActive(index>=0?index:Math.max(0,options.findIndex(option=>!option.disabled)));setOpen(true)};

    useLayoutEffect(()=>{
        if(!open)return;
        const update=()=>{
            const rect=trigger.current?.getBoundingClientRect();if(!rect)return;
            const below=window.innerHeight-rect.bottom-12,above=rect.top-12,up=below<Math.min(240,options.length*44)&&above>below;
            setPosition({left:Math.max(8,Math.min(rect.left,window.innerWidth-rect.width-8)),width:Math.min(rect.width,window.innerWidth-16),maxHeight:Math.max(44,Math.min(240,up?above:below)),...(up?{bottom:window.innerHeight-rect.top+4}:{top:rect.bottom+4})});
        };
        update();list.current?.showPopover();
        const outside=(event:PointerEvent)=>{if(!trigger.current?.contains(event.target as Node)&&!list.current?.contains(event.target as Node))setOpen(false)};
        const scroll=(event:Event)=>{if(!list.current?.contains(event.target as Node))update()};
        document.addEventListener('pointerdown',outside,true);window.addEventListener('scroll',scroll,true);window.addEventListener('resize',update);
        return()=>{document.removeEventListener('pointerdown',outside,true);window.removeEventListener('scroll',scroll,true);window.removeEventListener('resize',update)};
    },[open,options.length]);
    useEffect(()=>{if(open)list.current?.querySelector('[data-active="true"]')?.scrollIntoView({block:'nearest'})},[active,open]);

    return <div className={'custom-select '+className}>
        <select ref={validation} className="custom-select-native" aria-hidden="true" tabIndex={-1} name={name} required={required} disabled={disabled} value={value} onChange={event=>onChange(event.target.value)} onInvalid={event=>{event.preventDefault();trigger.current?.focus();show()}}>{children}</select>
        <button {...aria} ref={trigger} id={controlId} type="button" className="custom-select-trigger" role="combobox" aria-haspopup="listbox" aria-expanded={open} aria-controls={open?listId:undefined} aria-activedescendant={open&&options[active]?listId+'-'+active:undefined} aria-required={required} disabled={disabled}
            onClick={()=>open?setOpen(false):show()} onBlur={()=>setOpen(false)} onKeyDown={event=>{
                if(event.key==='Escape'&&open){event.preventDefault();event.stopPropagation();setOpen(false);return}
                if(event.key==='Tab'){setOpen(false);return}
                if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){
                    event.preventDefault();let next=event.key==='Home'?0:event.key==='End'?options.length-1:open?active+(event.key==='ArrowDown'?1:-1):selected>=0?selected:0;
                    const direction=event.key==='ArrowUp'||event.key==='End'?-1:1;
                    next=Math.max(0,Math.min(options.length-1,next));while(options[next]?.disabled)next+=direction;
                    if(next>=0&&next<options.length){setActive(next);setOpen(true)}return;
                }
                if(event.key==='Enter'||event.key===' '){event.preventDefault();open?choose(active):show();return}
                if(event.key.length===1&&!event.ctrlKey&&!event.metaKey&&!event.altKey){
                    event.preventDefault();const now=Date.now();search.current={text:(now-search.current.time<700?search.current.text:'')+event.key.toLowerCase(),time:now};
                    const index=options.findIndex(option=>!option.disabled&&String(option.label).toLowerCase().startsWith(search.current.text));
                    if(index>=0){setActive(index);setOpen(true)}
                }
            }}><span>{options[selected]?.label??'Select an option'}</span><ChevronDown size={18} className={open?'rotated':''}/></button>
        {open&&createPortal(<div ref={list} id={listId} role="listbox" aria-label={aria['aria-label']??trigger.current?.labels?.[0]?.textContent??'Options'} popover="manual" className="custom-select-menu" style={position} onPointerDown={event=>event.preventDefault()}>
            {options.length?options.map((option,index)=><div key={option.value} id={listId+'-'+index} role="option" aria-selected={index===selected} aria-disabled={option.disabled} data-active={index===active} className="custom-select-option" onPointerMove={()=>!option.disabled&&setActive(index)} onClick={()=>choose(index)}><span>{option.label}</span>{index===selected&&<Check size={16}/>}</div>):<div className="custom-select-empty">No options available</div>}
        </div>,trigger.current?.closest('dialog')??document.body)}
    </div>;
}
