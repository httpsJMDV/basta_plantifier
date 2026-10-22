import {useEffect,useId,useRef} from 'react';

export function normalizeName(value:string){return value.normalize('NFC').replace(/\s+/gu,' ').trim()}
export function validName(value:string){const normalized=normalizeName(value);return [...normalized].length<=20&&/^\p{L}[\p{L}\p{M}]*(?: \p{L}[\p{L}\p{M}]*)*$/u.test(normalized)}
export default function DisplayNameField({label='Display name',value,onChange}:{label?:string;value:string;onChange:(value:string)=>void}){
    const id=useId(),input=useRef<HTMLInputElement>(null);const count=[...normalizeName(value)].length;const invalid=value.length>0&&!validName(value);
    useEffect(()=>{input.current?.setCustomValidity(invalid?'Use up to 20 characters, with letters and spaces only.':'')},[invalid,value]);
    return <div className="field"><label htmlFor={id}>{label}</label><input ref={input} id={id} required autoComplete="name" value={value} onChange={event=>onChange(event.target.value)} onBlur={()=>onChange(normalizeName(value))} aria-describedby={id+'-hint'} aria-invalid={invalid} pattern="[\p{L}\p{M} ]+"/><small id={id+'-hint'}><span aria-live="polite">{count} / 20</span> · Letters and spaces only; accented letters are welcome.{invalid?' Please correct your display name before saving.':''}</small></div>;
}
