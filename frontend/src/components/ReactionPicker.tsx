import {useEffect,useState} from 'react';
import type {Reactions} from '../types';
import {api,useAction} from '../lib';
import {ErrorBox} from './UI';
const reactions=[{key:'fascinating',label:'Fascinating',emoji:'🌿'},{key:'crazy',label:'Crazy',emoji:'🤯'},{key:'loved',label:'Loved It',emoji:'💚'}];
export default function ReactionPicker({type,id,value}:{type:'post'|'comment';id:number;value:Reactions}){
    const [state,setState]=useState(value??{counts:{},selected:null});const action=useAction();useEffect(()=>setState(value??{counts:{},selected:null}),[value]);
    return <div className="reaction-picker" role="group" aria-label="Social reactions">{reactions.map(reaction=><button type="button" key={reaction.key} aria-pressed={state.selected===reaction.key} disabled={action.busy} onClick={()=>action.run(async()=>{const result=await api('/reactions/'+type+'/'+id,{reaction:reaction.key});setState(result.reactions)})}><span aria-hidden="true">{reaction.emoji}</span>{reaction.label}<span>{state.counts[reaction.key]??0}</span></button>)}<ErrorBox error={action.error}/></div>;
}
