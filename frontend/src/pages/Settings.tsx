import ProfilePhotoEditor from '../components/ProfilePhotoEditor';
import DiscoveryPreference from '../components/DiscoveryPreference';
import CustomSelect from '../components/CustomSelect';
import {useState} from 'react';
import {usePage,Link} from '@inertiajs/react';
import {Save,ShieldCheck,Leaf,ExternalLink,Mail,ArrowUpRight,LockKeyhole,Users,Ban} from 'lucide-react';
import Shell from '../components/Shell';
import {Button,Field,ErrorBox} from '../components/UI';
import type {Shared} from '../types';
import {api,useAction,notify,refresh} from '../lib';

export default function Settings({googleEnabled,blocked=[]}:{googleEnabled:boolean;blocked?:{id:number;name:string}[]}){
    const {props}=usePage<Shared>();const user=props.auth.user!;
    const [data,setData]=useState({name:user.name,username:user.username??'',bio:user.bio??'',municipality:user.municipality??'',interests:user.interests.join(', '),discovery_enabled:user.discovery_enabled,friend_request_privacy:user.friend_request_privacy});
    const action=useAction();
    return <Shell title="Profile & Settings" subtitle="Make this space your own. Manage your profile and how you connect.">
        <form className="settings-form" onSubmit={event=>{event.preventDefault();action.run(async()=>{await api('/settings',{...data,interests:data.interests.split(',').map(value=>value.trim()).filter(Boolean)},'PATCH');notify('Settings saved.');refresh()})}}>
        <div className="settings-layout">
            <div className="settings-profile-column">
                <div className="settings-card settings-photo-card"><ProfilePhotoEditor name={data.name} src={user.avatar_url}/></div>
                <section className="settings-card settings-section" aria-labelledby="personal-profile-title">
                    <header className="settings-card-heading"><span className="settings-card-icon"><Leaf size={19}/></span><div><h2 id="personal-profile-title">Personal profile</h2><p>A little about the person behind the discoveries.</p></div></header>
                    <div className="two-fields">
                        <Field label="Display name"><input required maxLength={80} autoComplete="name" value={data.name} onChange={event=>setData({...data,name:event.target.value})}/></Field>
                        <Field label="Username · optional"><input maxLength={30} minLength={3} autoComplete="username" pattern="[A-Za-z0-9_-]+" placeholder="Your unique handle" value={data.username} onChange={event=>setData({...data,username:event.target.value})}/></Field>
                    </div>
                    <Field label="A short introduction"><textarea rows={3} maxLength={600} placeholder="What brings you into the green world?" value={data.bio} onChange={event=>setData({...data,bio:event.target.value})}/></Field>
                    <Field label="General municipality or city · optional" hint="A broad area is enough. Keep your exact address private."><input maxLength={120} placeholder="e.g. Santa Cruz, Laguna" value={data.municipality} onChange={event=>setData({...data,municipality:event.target.value})}/></Field>
                    <Field label="Plant interests" hint="Separate interests with commas. Up to 12 interests."><input placeholder="e.g. Houseplants, Flowers, Herbs" value={data.interests} onChange={event=>setData({...data,interests:event.target.value})}/></Field>
                </section>
                <section className="settings-card settings-section" aria-labelledby="connections-title">
                    <header className="settings-card-heading"><span className="settings-card-icon"><Users size={19}/></span><div><h2 id="connections-title">Discovery & connections</h2><p>Choose how other explorers can find and connect with you.</p></div></header>
                    <DiscoveryPreference checked={data.discovery_enabled} onChange={checked=>setData({...data,discovery_enabled:checked})}/>
                    <Field label="Who can send me friend requests?"><CustomSelect value={data.friend_request_privacy} onChange={value=>setData({...data,friend_request_privacy:value})}><option value="everyone">Everyone</option><option value="friends-of-friends">Friends of friends</option><option value="nobody">Nobody</option></CustomSelect></Field>
                </section>
        <ErrorBox error={action.error}/>
        <div className="settings-save-bar"><span><ShieldCheck size={16}/>Your profile, your preferences.</span><Button type="submit" busy={action.busy}><Save size={16}/>Save changes</Button></div>
            </div>
            <aside className="settings-aside" aria-label="Account and privacy">

                <section className="settings-card">
                    <header className="settings-card-heading"><span className="settings-card-icon"><Mail size={19}/></span><div><h2>Account</h2><p>Your sign-in details.</p></div></header>
                    <div className="settings-account-identity"><span>Email address</span><p>{user.email}</p><span className={'settings-verification '+(user.verified?'is-verified':'')}><ShieldCheck size={13}/>{user.verified?'Email verified':'Verification pending'}</span></div>
                    <div className="settings-account-links">{!user.verified&&<Link href="/verify-email">Verify your email<ArrowUpRight size={15}/></Link>}{googleEnabled&&<a href="/auth/google">Link Google account<ExternalLink size={14}/></a>}<Link href="/forgot-password">Reset password<ArrowUpRight size={15}/></Link></div>
                </section>
                <section className="settings-card settings-privacy-card"><span className="settings-privacy-icon"><LockKeyhole size={21}/></span><h2>Private by default.</h2><p>Your conversations, plant collection, notes, and locations start private.</p><div className="settings-privacy-rule"/><p>You choose which discoveries, stories, plant cards, and location pins to share.</p></section>
                {blocked.length>0&&<section className="settings-card"><header className="settings-card-heading"><span className="settings-card-icon"><Ban size={18}/></span><div><h2>Blocked accounts</h2><p>Manage who can interact with you.</p></div></header>{blocked.map(person=><div className="blocked-row" key={person.id}><span>{person.name}</span><button type="button" className="text-button" disabled={action.busy} onClick={()=>action.run(async()=>{await api('/people/'+person.id+'/connections',{action:'unblock'});notify('Account unblocked.');refresh()})}>Unblock</button></div>)}</section>}
                {user.is_admin&&<section className="settings-card"><header className="settings-card-heading"><span className="settings-card-icon"><ShieldCheck size={19}/></span><div><h2>Administration</h2><p>A separate MFA check is required.</p></div></header><Link href="/admin" className="settings-admin-link">Open Admin System<ExternalLink size={14}/></Link></section>}
            </aside>
        </div>

        </form>
    </Shell>;
}
