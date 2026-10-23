import {useCallback,useSyncExternalStore} from 'react';

type Draft={body:string;images:File[]};
const empty:Draft={body:'',images:[]};
const drafts=new Map<string,Draft>();
const listeners=new Set<()=>void>();
const subscribe=(listener:()=>void)=>{listeners.add(listener);return()=>{listeners.delete(listener)}};
function emit(){listeners.forEach(listener=>listener())}
export function clearWorkspaceDrafts(){drafts.clear();emit()}

// Files stay in this tab's memory, never in localStorage or on the server.
export function useWorkspaceDraft(userId:number,conversationId?:number){
    const key=userId+':'+(conversationId??'new');
    const snapshot=useCallback(()=>drafts.get(key)??empty,[key]);
    const draft=useSyncExternalStore(subscribe,snapshot);
    const setBody=(body:string)=>{drafts.set(key,{...snapshot(),body});emit()};
    const setImages=(value:File[]|((images:File[])=>File[]))=>{const current=snapshot();drafts.set(key,{...current,images:typeof value==='function'?value(current.images):value});emit()};
    return {...draft,setBody,setImages};
}
