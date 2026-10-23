import {useState,type FormEvent} from 'react';
import {Check,ExternalLink,KeyRound,LoaderCircle,RefreshCw,Sparkles} from 'lucide-react';
import type {AdminProps,AiHealth,AiKey} from '../adminTypes';
import {relativeTime} from '../adminTypes';
import {AdminPanel,AdminStatus,useStepUp} from './AdminUI';
import {Button,ErrorBox} from './UI';
import {api,notify,refresh,useAction} from '../lib';
type Provider='groq'|'claude'|'plantnet';
const info:Record<Provider,{name:string;tagline:string;keyUrl:string;unit:string}>={groq:{name:'Groq',tagline:'Fast open models',keyUrl:'https://console.groq.com/keys',unit:'requests'},claude:{name:'Claude',tagline:'Anthropic models',keyUrl:'https://console.anthropic.com/settings/keys',unit:'requests'},plantnet:{name:'Pl@ntNet',tagline:'Plant photo identification',keyUrl:'https://my.plantnet.org/',unit:'identifications'}};
function providerState(health:AiHealth['providers'][string]){return health.keys===0?'no_keys':health.ready===0?(health.invalid===health.keys?'invalid':'resting'):health.ready<health.keys?'limited':'ready'}
export function AiHealthCards({health}:{health:AiHealth}){
 const rows=[{key:health.primary,role:'Main text AI'},...(health.backup?[{key:health.backup,role:'Backup text AI'}]:[]),{key:'plantnet',role:'Photo identification'}];
 return <div className="admin-ai-health">{rows.map(({key,role})=>{const item=health.providers[key],state=providerState(item);return <article key={key} className={'admin-ai-health-card is-'+state}><div><span>{role}</span><AdminStatus value={state}/></div><strong>{item.name}</strong><p>{item.keys===0?'No API key yet':item.ready+' of '+item.keys+(item.keys===1?' key':' keys')+' ready'}</p><small>{state==='resting'&&item.next_ready_at?'Next key back '+relativeTime(item.next_ready_at):state==='invalid'?'Every key was rejected. Add a new one.':item.keys===0?'Add a key to turn this on.':item.remaining!=null?item.remaining.toLocaleString()+' '+info[key as Provider].unit+' left today':'Limits reset automatically.'}</small></article>})}</div>;
}
function keyDetail(key:AiKey,provider:Provider){
 if(key.state==='invalid')return (key.last_error??'The provider rejected this key.')+(key.source==='env'?' Replace it in backend/.env.':' Remove it and add a new one.');
 if(key.state==='resting')return (key.last_error??'Reached its usage limit')+(key.resting_until?' · back '+relativeTime(key.resting_until):'');
 const parts=[key.uses?'Used '+key.uses.toLocaleString()+'×':'Not used yet'];
 if(key.limit_hits)parts.push('hit its limit '+key.limit_hits+'×');
 if(key.last_used_at)parts.push('last used '+relativeTime(key.last_used_at));
 if(key.quota_remaining!=null)parts.push(key.quota_remaining.toLocaleString()+' '+info[provider].unit+' left today');
 if(key.last_error)parts.push(key.last_error);
 return parts.join(' · ');
}
export function AdminProviders({ai,aiHealth}:AdminProps){
 const connection=useStepUp(),keyStep=useStepUp(),check=useAction();
 const [provider,setProvider]=useState(ai?.primary??'groq'),[model,setModel]=useState(ai?.models[ai.primary]??''),[fallback,setFallback]=useState(ai?.fallback??true);
 const [models,setModels]=useState<Record<string,string[]>>({}),[loadingModels,setLoadingModels]=useState(false),[modelError,setModelError]=useState('');
 const [tab,setTab]=useState<Provider>((ai?.primary??'groq') as Provider),[form,setForm]=useState({label:'',key:''}),[checking,setChecking]=useState(0),[confirming,setConfirming]=useState(0);
 if(!ai)return null;
 const other=provider==='groq'?'claude':'groq',current=ai.primary as Provider,effective=(key:string)=>ai.models[key]||ai.defaults[key];
 const dirty=provider!==ai.primary||model.trim()!==(ai.models[provider]??'')||fallback!==ai.fallback;
 const total=Object.values(ai.keys).reduce((sum,list)=>sum+list.length,0);
 const choose=(next:string)=>{setProvider(next);setModel(ai.models[next]??'');setModelError('')};
 const save=()=>connection.run('Save AI connection','Save connection',async password=>{const result=await api('/admin/ai/connection',{provider,model:model.trim()||null,fallback,password});notify(result.message);refresh()});
 async function loadModels(){setLoadingModels(true);setModelError('');try{const result=await api('/admin/ai/'+provider+'/models',{});setModels({...models,[provider]:result.models});notify(result.models.length+' '+info[provider as Provider].name+' models available.')}catch(error){setModelError(error instanceof Error?error.message:'The model list could not be loaded.')}finally{setLoadingModels(false)}}
 const tryKey=(key:AiKey)=>{setChecking(key.id);check.run(async()=>{try{const result=await api('/admin/ai/keys/'+key.id+'/check',{});notify(result.message,result.status==='success'?'success':'error');refresh()}finally{setChecking(0)}})};
 const remove=(key:AiKey)=>{if(confirming!==key.id){setConfirming(key.id);setTimeout(()=>setConfirming(value=>value===key.id?0:value),4000);return}setConfirming(0);keyStep.run('Remove '+key.label,'Remove key',async password=>{const result=await api('/admin/ai/keys/'+key.id,{password},'DELETE');notify(result.message);refresh()})};
 const add=(event:FormEvent)=>{event.preventDefault();keyStep.run('Add '+info[tab].name+' key','Add key',async password=>{const result=await api('/admin/ai/keys',{provider:tab,label:form.label.trim()||null,key:form.key.trim(),password});setForm({label:'',key:''});notify(result.message);refresh()})};
 return <><AiHealthCards health={aiHealth}/>
 <AdminPanel title="Text AI connection" description="Pick which provider writes chat replies and note suggestions, and which model it uses." action={<AdminStatus value={aiHealth.providers[ai.primary].ready?'connected':'needs_keys'}/>}><div className="admin-ai-body">
  <div className="admin-ai-label">Provider</div>
  <div className="admin-ai-choice" role="radiogroup" aria-label="Main text provider">{(['groq','claude'] as const).map(key=>{const health=aiHealth.providers[key];return <button type="button" role="radio" aria-checked={provider===key} key={key} className={'admin-ai-option'+(provider===key?' is-selected':'')} onClick={()=>choose(key)}><span className="admin-ai-option-check">{provider===key&&<Check size={13}/>}</span><strong>{info[key].name}</strong><small>{info[key].tagline} · {effective(key)}</small><em>{health.keys?health.ready+' of '+health.keys+(health.keys===1?' key':' keys')+' ready':'No keys yet'}</em></button>})}</div>
  <div className="admin-ai-model"><div className="admin-ai-model-head"><label htmlFor="ai-model">Model</label><button type="button" className="admin-ai-link" onClick={loadModels} disabled={loadingModels}>{loadingModels?<LoaderCircle className="spin" size={14}/>:<RefreshCw size={14}/>}Refresh list</button></div><input id="ai-model" list="ai-model-options" value={model} placeholder={ai.defaults[provider]} maxLength={160} spellCheck={false} autoComplete="off" onChange={event=>setModel(event.target.value)}/><datalist id="ai-model-options">{(models[provider]??[]).map(id=><option value={id} key={id}/>)}</datalist><small>{models[provider]?models[provider].length+' models are available on your key. Start typing to pick one.':'Leave blank to use the default ('+ai.defaults[provider]+'). Refresh the list to see the models your key can use.'}</small><ErrorBox error={modelError}/></div>
  <div className="admin-toggle-row"><div><strong>Use {info[other].name} as backup</strong><small>When every {info[provider as Provider].name} key is at its limit, {info[other].name} answers instead.{aiHealth.providers[other].keys?'':' Add a '+info[other].name+' key below to make this work.'}</small></div><button type="button" role="switch" aria-checked={fallback} aria-label={'Use '+info[other].name+' as backup'} className={'admin-toggle'+(fallback?' is-on':'')} onClick={()=>setFallback(!fallback)}><span/></button></div>
  <div className="admin-ai-current"><Sparkles size={16}/><span><strong>Currently using {info[current].name} · {effective(current)}</strong>{ai.fallback&&<> with {info[current==='groq'?'claude':'groq'].name} as backup</>}{ai.updated_at&&<small> · updated {relativeTime(ai.updated_at)}</small>}</span></div>
  <ErrorBox error={connection.error}/>
  <div className="admin-ai-actions">{dirty&&<Button type="button" variant="secondary" onClick={()=>{setProvider(ai.primary);setModel(ai.models[ai.primary]??'');setFallback(ai.fallback)}}>Discard changes</Button>}<Button type="button" disabled={!dirty||connection.busy} busy={connection.busy} onClick={save}>Save connection</Button></div>
 </div></AdminPanel>
 <AdminPanel title="API keys" description="Add as many keys as you like. Requests rotate through them, and a key that runs out rests until its limit resets." action={<span className="admin-ai-pooled">{total} {total===1?'key':'keys'} pooled</span>}><div className="admin-ai-body">
  <div className="admin-ai-tabs" role="tablist" aria-label="Provider keys">{(['groq','claude','plantnet'] as Provider[]).map(key=><button type="button" role="tab" key={key} aria-selected={tab===key} className={tab===key?'is-active':''} onClick={()=>{setTab(key);setForm({label:'',key:''});setConfirming(0)}}>{info[key].name}<span>{ai.keys[key].length}</span></button>)}</div>
  <p className="admin-ai-explain">{tab==='plantnet'?'Pl@ntNet gives each account a daily number of identifications. When a key uses them up, it rests until tomorrow and the next key takes over. Keys from different accounts add more identifications per day.':'Each request uses the key that has rested longest. When a key reaches its limit, it rests and the next key takes over. If every key is out, the backup provider answers. Keys from the same account share one limit, so keys from different accounts add more capacity.'}</p>
  {ai.keys[tab].length?<ul className="admin-ai-keys">{ai.keys[tab].map(key=><li key={key.id} className={'admin-ai-key is-'+key.state}><span className="admin-ai-key-icon"><KeyRound size={16}/></span><div className="admin-ai-key-main"><div><strong>{key.label}</strong><code>••••{key.last_four}</code>{key.source==='env'&&<span className="admin-ai-tag">From .env</span>}</div><p>{keyDetail(key,tab)}</p></div><AdminStatus value={key.state}/><div className="admin-ai-key-actions"><button type="button" className="admin-ai-link" disabled={check.busy} onClick={()=>tryKey(key)}>{checking===key.id?<LoaderCircle className="spin" size={14}/>:<RefreshCw size={14}/>}Try now</button>{key.source==='env'?<span className="admin-ai-managed">Managed in .env</span>:<button type="button" className={'admin-ai-link is-danger'+(confirming===key.id?' is-confirming':'')} disabled={keyStep.busy} onClick={()=>remove(key)}>{confirming===key.id?'Confirm remove':'Remove'}</button>}</div></li>)}</ul>:<div className="admin-ai-empty"><KeyRound size={20}/><p>No {info[tab].name} keys yet. Add one below to turn it on.</p></div>}
  <ErrorBox error={check.error||keyStep.error}/>
  <form className="admin-ai-add" onSubmit={add}><input aria-label="Key label" placeholder="Label (optional)" maxLength={40} value={form.label} onChange={event=>setForm({...form,label:event.target.value})}/><input aria-label={info[tab].name+' API key'} type="password" required minLength={8} maxLength={400} autoComplete="off" spellCheck={false} placeholder={'Paste a '+info[tab].name+' API key'} value={form.key} onChange={event=>setForm({...form,key:event.target.value})}/><Button busy={keyStep.busy}>Add key</Button></form>
  <a className="admin-ai-get-key" href={info[tab].keyUrl} target="_blank" rel="noreferrer">Get a {info[tab].name} API key<ExternalLink size={12}/></a>
 </div></AdminPanel>{connection.dialog}{keyStep.dialog}</>;
}
