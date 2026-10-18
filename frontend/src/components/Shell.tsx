import Avatar from './Avatar';
import {useEffect,useRef,useState,type ReactNode} from 'react';
import {Head,Link,router,usePage} from '@inertiajs/react';
import {Plus,Leaf,MapPinned,NotebookPen,Users,MessagesSquare,PanelLeftClose,PanelLeftOpen,Menu,Search,Settings,LogOut,ArrowUpRight,MessageSquare,CheckCircle2,AlertCircle,X,Compass,ChevronDown} from 'lucide-react';
import type {Shared} from '../types';
import {Brand,Button} from './UI';
import {api} from '../lib';
const communityLinks=[{href:'/community',label:'Community feed'},{href:'/community?tab=following',label:'Following'},{href:'/community?tab=stories',label:'Stories'},{href:'/community?tab=requests',label:'Plant ID requests'},{href:'/people?tab=friends',label:'Friends'},{href:'/people?tab=requests',label:'Friend requests'},{href:'/people?tab=suggestions',label:'Discover people'}];
const links=[{href:'/workspace',label:'AI Workspace',icon:Compass},{href:'/collection',label:'My Plant Collection',icon:Leaf},{href:'/map',label:'Gallery & Location',icon:MapPinned},{href:'/notes',label:'Notes & Journal',icon:NotebookPen},{href:'/community',label:'Community',icon:Users},{href:'/messages',label:'Messages',icon:MessagesSquare}];
export default function Shell({title,subtitle,children,actions,full=false}: {title:string;subtitle?:string;children:ReactNode;actions?:ReactNode;full?:boolean}){
    const {props,url}=usePage<Shared>();const user=props.auth.user!;const [open,setOpen]=useState(false);const [search,setSearch]=useState('');const [notice,setNotice]=useState<{message:string;kind:string}|null>(null);
    const [mobile,setMobile]=useState(()=>window.matchMedia('(max-width: 800px)').matches);
    const [collapsed,setCollapsed]=useState(()=>{try{return localStorage.getItem('plantifier:sidebar-collapsed')==='true'}catch{return false}});
    const [panelMotion,setPanelMotion]=useState(false);
    const communityActive=url.startsWith('/community')||url.startsWith('/people');
    const [communityOpen,setCommunityOpen]=useState(communityActive);
    useEffect(()=>{if(communityActive)setCommunityOpen(true)},[url,communityActive]);
    const navigationToggle=useRef<HTMLButtonElement>(null);
    const navigationVisible=mobile?open:!collapsed;
    const closeNavigation=()=>{if(mobile)setOpen(false);else{setPanelMotion(true);setCollapsed(true)}navigationToggle.current?.focus()};
    useEffect(()=>{const query=window.matchMedia('(max-width: 800px)');const change=()=>{setMobile(query.matches);setOpen(false)};query.addEventListener('change',change);return()=>query.removeEventListener('change',change)},[]);
    useEffect(()=>{try{localStorage.setItem('plantifier:sidebar-collapsed',String(collapsed))}catch{}},[collapsed]);
    useEffect(()=>{if(!open||!mobile)return;const key=(event:KeyboardEvent)=>{if(event.key==='Escape'){setOpen(false);navigationToggle.current?.focus()}};document.addEventListener('keydown',key);return()=>document.removeEventListener('keydown',key)},[open,mobile]);
    useEffect(()=>{setOpen(false)},[url]);
    useEffect(()=>{const fn=(e:Event)=>setNotice((e as CustomEvent).detail);window.addEventListener('plantifier:notice',fn);return()=>window.removeEventListener('plantifier:notice',fn)},[]);
    useEffect(()=>{if(props.flash.success)setNotice({message:props.flash.success,kind:'success'});if(props.flash.error)setNotice({message:props.flash.error,kind:'error'})},[props.flash]);
    useEffect(()=>{if(!notice)return;const t=setTimeout(()=>setNotice(null),6500);return()=>clearTimeout(t)},[notice]);
    return <div className={'app-shell '+(collapsed?'nav-collapsed ':'')+(panelMotion?'panel-motion':'')}><Head title={title}/><button className={'sidebar-overlay '+(open?'is-visible':'')} aria-label="Close navigation" aria-hidden={!mobile||!open} tabIndex={mobile&&open?0:-1} onClick={closeNavigation}/>
    <button ref={navigationToggle} className="icon-button navigation-toggle" aria-label={mobile?(open?'Close navigation':'Open navigation'):(collapsed?'Expand sidebar':'Collapse sidebar')} aria-expanded={navigationVisible} aria-controls="main-navigation" onClick={()=>{if(mobile)setOpen(!open);else{setPanelMotion(true);setCollapsed(!collapsed)}}}>{mobile?<Menu size={21}/>:collapsed?<PanelLeftOpen size={20}/>:<PanelLeftClose size={20}/>}</button>
    <aside id="main-navigation" className={'sidebar '+(open?'open':'')} aria-label="Main navigation" inert={!navigationVisible} aria-hidden={!navigationVisible}>
        <div className="sidebar-brand-row"><Link href="/workspace" className="brand-link"><Brand/></Link></div><Button className="new-chat" onClick={()=>router.visit('/workspace')}><span className="new-chat-icon"><Plus size={18}/></span><span>New discovery</span><ArrowUpRight size={15}/></Button>
        <div className="sidebar-scroll"><div className="nav-caption">WORKSPACE</div><nav aria-label="Workspace">{links.map(({href,label,icon:Icon})=>href==='/community'?<div className={'community-nav-group '+(communityActive?'is-active':'')} key={href}><div className="community-nav-row"><Link href={href} className={'nav-link '+(communityActive?'active':'')}><Icon size={19}/>{label}</Link><button type="button" className="community-nav-toggle" aria-label={communityOpen?'Collapse Community menu':'Expand Community menu'} aria-expanded={communityOpen} aria-controls="community-submenu" onClick={()=>setCommunityOpen(!communityOpen)}><ChevronDown size={16}/></button></div><div id="community-submenu" className={'community-submenu '+(communityOpen?'is-open':'')} inert={!communityOpen} aria-hidden={!communityOpen}><div><nav aria-label="Community">{communityLinks.map(item=><Link key={item.href} href={item.href} className={'nav-sublink '+(url===item.href?'active':'')} aria-current={url===item.href?'page':undefined}>{item.label}</Link>)}</nav></div></div></div>:<Link href={href} key={href} className={'nav-link '+(url.startsWith(href)?'active':'')} aria-current={url.startsWith(href)?'page':undefined}><Icon size={19}/>{label}</Link>)}</nav>
        <div className="history-header"><span className="nav-caption">RECENT CONVERSATIONS</span><Search size={14}/></div>
        {props.recentChats.length>0&&<input className="history-search" aria-label="Search recent conversations" placeholder="Find a conversation?" value={search} onChange={e=>setSearch(e.target.value)}/>}
        <div className="recent-chats">{props.recentChats.filter(c=>c.title.toLowerCase().includes(search.toLowerCase())).map(c=><Link className={'recent-chat '+(url==='/workspace/'+c.id?'selected':'')} key={c.id} href={'/workspace/'+c.id}><MessageSquare size={15}/><span>{c.title}</span></Link>)}{props.recentChats.length===0&&<p className="sidebar-hint">Your discoveries will find a home here.</p>}</div>
        </div><div className="sidebar-bottom"><Link href="/settings" className="profile-link" aria-label="Edit your profile"><Avatar name={user.name} src={user.avatar_url}/><span className="sidebar-profile-copy"><strong>{user.name}</strong><small>Personal workspace</small></span><Settings size={17}/></Link><button className="sidebar-signout" type="button" onClick={()=>router.post('/logout')}><LogOut size={16}/><span>Sign out</span></button></div>
    </aside>
    <div className={'main-area '+(full?'full':'')}><header className="topbar"><div className="topbar-breadcrumb"><span className="desktop-only"><Leaf size={15}/>Personal workspace</span><span className="breadcrumb-divider desktop-only">/</span><span>{title}</span></div><div className="topbar-end"><span className="private-chip"><span className="status-dot"/>Private by default</span><Link className="icon-button" href="/settings" aria-label="Settings"><Settings size={18}/></Link></div></header>
    {props.announcement&&<div className="announcement">{props.announcement}</div>}
    {!full&&<div className="page-heading"><div><div className="eyebrow">PLANTIFIER FIELD NOTES</div><h1>{title}</h1>{subtitle&&<p>{subtitle}</p>}</div>{actions&&<div className="heading-actions">{actions}</div>}</div>}
    <main className={full?'full-content':'page-content'}>{children}</main>
    </div>{notice&&<div className={'toast '+notice.kind} role="status">{notice.kind==='error'?<AlertCircle size={19}/>:<CheckCircle2 size={19}/>}<span>{notice.message}</span><button aria-label="Dismiss notification" onClick={()=>setNotice(null)}><X size={16}/></button></div>}
    </div>;
}
