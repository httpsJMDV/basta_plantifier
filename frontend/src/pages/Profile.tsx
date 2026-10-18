import Avatar from '../components/Avatar';
import {router,usePage,Link} from '@inertiajs/react';
import {MessagesSquare,UserPlus,Leaf} from 'lucide-react';
import Shell from '../components/Shell';
import {Button,ReportButton,Empty,Pagination,ErrorBox} from '../components/UI';
import type {Person,Page,Post,Shared} from '../types';
import {api,useAction,notify,date} from '../lib';
export default function Profile({profile:p,posts}:{profile:Person;posts:Page<Post>}){
    const {props}=usePage<Shared>();const a=useAction();return <Shell title={p.name} subtitle={p.username?'@'+p.username:'Community explorer'}><div className="profile-header"><Avatar name={p.name} src={p.avatar_url} className="large-avatar"/><div><h2>{p.name}</h2><p>{p.bio??'A little curiosity, a growing collection.'}</p>{p.municipality&&<span className="muted">{p.municipality}</span>}<div className="person-interests">{p.interests.map(i=><span className="badge" key={i}>{i}</span>)}</div></div>{p.id!==props.auth.user!.id&&<div className="profile-actions"><Button onClick={()=>a.run(async()=>{const r=await api('/messages/start/'+p.id,{});router.visit(r.url)})}><MessagesSquare size={16}/>Message</Button><ReportButton type="user" id={p.id}/></div>}</div><ErrorBox error={a.error}/><div className="section-heading"><h2>Public field discoveries</h2><span className="muted">Personal collections stay private.</span></div>{!posts.data.length?<Empty title="A quiet corner of the field." body="Public discoveries will appear here when this explorer chooses to share."/>:<div className="profile-posts">{posts.data.map(p=><article key={p.id} className="feed-post"><small>{date(p.created_at)}</small><p className="post-body">{p.body}</p>{p.media_id&&<img className="post-image" src={'/media/'+p.media_id} alt="Shared botanical discovery"/>}<Link className="text-link" href="/community">Visit the community discussion ?</Link></article>)}</div>}<Pagination page={posts}/></Shell>
}
