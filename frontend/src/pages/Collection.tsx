import CustomSelect from '../components/CustomSelect';
import {useState} from 'react';
import {Link,router} from '@inertiajs/react';
import {Plus,Download,Search,Leaf,Heart,ArrowUpRight,LayoutGrid,List,SlidersHorizontal} from 'lucide-react';
import Shell from '../components/Shell';
import {Button,Empty,Pagination} from '../components/UI';
import PlantEditor from '../components/PlantEditor';
import ExportDialog from '../components/ExportDialog';
import type {Plant,Page} from '../types';
import {date} from '../lib';
export default function Collection({plants,filters}:{plants:Page<Plant>;filters:{q?:string;filter?:string}}){
    const [edit,setEdit]=useState(false);const [exporting,setExporting]=useState(false);const [q,setQ]=useState(filters.q??'');const [list,setList]=useState(false);
    return <Shell title="My Plant Collection" subtitle="The plants you’ve met. The details you’ve kept." actions={<><Button variant="secondary" disabled={!plants.data.length} onClick={()=>setExporting(true)}><Download size={16}/>Export</Button><Button onClick={()=>setEdit(true)}><Plus size={17}/>Add a plant</Button></>}>
    <div className="collection-toolbar"><form className="search-input" onSubmit={e=>{e.preventDefault();router.get('/collection',{q,filter:filters.filter??'all'},{preserveState:true})}}><Search size={17}/><input aria-label="Search plant collection" value={q} onChange={e=>setQ(e.target.value)} placeholder="Search your plant records?"/></form><CustomSelect aria-label="Filter plant collection" value={filters.filter??'all'} onChange={value=>router.get('/collection',{q,filter:value},{preserveState:true})}><option value="all">All active records</option><option value="favorite">Favorites</option><option value="growing">Growing</option><option value="discovered">Discovered</option><option value="researching">Researching</option><option value="wishlist">Wishlist</option><option value="archived">Archived</option></CustomSelect><div className="view-toggle"><button className={!list?'selected':''} aria-label="Grid view" aria-pressed={!list} onClick={()=>setList(false)}><LayoutGrid size={17}/></button><button className={list?'selected':''} aria-label="List view" aria-pressed={list} onClick={()=>setList(true)}><List size={17}/></button></div></div>
    {plants.total>0&&<div className="results-caption">{plants.total} personal {plants.total===1?'record':'records'}<span>PRIVATE COLLECTION</span></div>}
    {!plants.data.length?<Empty title={filters.q||filters.filter&&filters.filter!=='all'?'No matching records.':'A collection starts with one leaf.'} body={filters.q?'Try another name or clear your filters.':'Save an AI discovery or add a plant you already know. Each record becomes a little field notebook of its own.'}><Link href="/workspace" className="button">Make a discovery<ArrowUpRight size={16}/></Link><Button variant="secondary" onClick={()=>setEdit(true)}>Add a record manually</Button></Empty>:
    <div className={'specimen-grid '+(list?'list-view':'')}>{plants.data.map(p=><Link href={'/collection/'+p.id} key={p.id} className="specimen-card"><div className="specimen-photo">{p.media?<img src={p.media.url} alt={p.common_name} loading="lazy"/>:<div className="specimen-no-photo"><Leaf size={46} strokeWidth={1}/><span>FIELD RECORD</span></div>}<span className="photo-label">{p.relationship}</span>{p.favorite&&<span className="favorite-mark" aria-label="Favorite"><Heart size={15} fill="currentColor"/></span>}</div><div className="specimen-copy"><div className="specimen-number">SPECIMEN {String(p.id).padStart(3,'0')}<ArrowUpRight size={16}/></div><h2>{p.common_name}</h2><i>{p.scientific_name??'Scientific name not recorded'}</i><div className="specimen-meta"><span>{p.family??'Personal botanical record'}</span><time>{date(p.created_at)}</time></div></div></Link>)}</div>}
    <Pagination page={plants}/>{edit&&<PlantEditor onClose={()=>setEdit(false)}/>} {exporting&&<ExportDialog plants={plants.data} onClose={()=>setExporting(false)}/>}
    </Shell>;
}
