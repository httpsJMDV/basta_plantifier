import MunicipalityField from '../components/MunicipalityField';
import DisplayNameField from '../components/DisplayNameField';
import ProfilePhotoEditor from '../components/ProfilePhotoEditor';
import DiscoveryPreference from '../components/DiscoveryPreference';
import CustomSelect from '../components/CustomSelect';
import {useState} from 'react';
import {usePage,Link} from '@inertiajs/react';
import {Save,ShieldCheck,Leaf,ExternalLink,Mail,ArrowUpRight,LockKeyhole,Users,Ban} from 'lucide-react';
import Shell from '../components/Shell';
import {Button,Field,ErrorBox,Modal} from '../components/UI';
import type {Shared} from '../types';
import {api,useAction,notify,refresh} from '../lib';

export default function Settings({googleEnabled,blocked=[]}:{googleEnabled:boolean;blocked?:{id:number;name:string}[]}){
    const {props}=usePage<Shared>();const user=props.auth.user!;
    const [data,setData]=useState({name:user.name,profile_visibility:user.profile_visibility,connections_visibility:user.connections_visibility,activity_visibility:user.activity_visibility,purpose:user.purpose??'discover',experience:user.experience??'beginner',username:user.username??'',bio:user.bio??'',municipality:user.municipality??'',interests:user.interests.join(', '),discovery_enabled:user.discovery_enabled,friend_request_privacy:user.friend_request_privacy});
    const action=useAction();
    const [accountModal,setAccountModal]=useState<'password'|'google'|null>(null);
    const [passwords,setPasswords]=useState({current_password:'',password:'',password_confirmation:''});
    const passwordAction=useAction();
    return <Shell title="Profile & Settings" subtitle="Make this space your own. Manage your profile and how you connect.">
        <form className="settings-form" onSubmit={event=>{event.preventDefault();action.run(async()=>{await api('/settings',{...data,interests:data.interests.split(',').map(value=>value.trim()).filter(Boolean)},'PATCH');notify('Settings saved.');refresh()})}}>
        <div className="settings-layout">
            <div className="settings-profile-column">
                <div className="settings-card settings-photo-card"><ProfilePhotoEditor name={data.name} src={user.avatar_url}/></div>
                <section className="settings-card settings-section" aria-labelledby="personal-profile-title">
                    <header className="settings-card-heading"><span className="settings-card-icon"><Leaf size={19}/></span><div><h2 id="personal-profile-title">Personal profile</h2><p>A little about the person behind the discoveries.</p></div></header>
                    <div className="two-fields">
                        <DisplayNameField value={data.name} onChange={value=>setData({...data,name:value})}/>
                        <Field label="Username · optional"><input maxLength={30} minLength={3} autoComplete="username" pattern="[A-Za-z0-9_-]+" placeholder="Your unique handle" value={data.username} onChange={event=>setData({...data,username:event.target.value})}/></Field>
                    </div>
                    <Field label="A short introduction"><textarea rows={3} maxLength={600} placeholder="What brings you into the green world?" value={data.bio} onChange={event=>setData({...data,bio:event.target.value})}/></Field>
                    <MunicipalityField value={data.municipality} onChange={value=>setData({...data,municipality:value})}/>
                    <div className="two-fields"><Field label="Purpose"><CustomSelect value={data.purpose} onChange={value=>setData({...data,purpose:value})}><option value="discover">Discover</option><option value="grow">Grow</option><option value="research">Research</option><option value="connect">Connect</option></CustomSelect></Field><Field label="Experience"><CustomSelect value={data.experience} onChange={value=>setData({...data,experience:value})}><option value="beginner">Beginner</option><option value="enthusiast">Enthusiast</option><option value="experienced">Experienced</option></CustomSelect></Field></div><Field label="Plant interests" hint="Separate interests with commas. Up to 12 interests."><input placeholder="e.g. Houseplants, Flowers, Herbs" value={data.interests} onChange={event=>setData({...data,interests:event.target.value})}/></Field>
                </section>
                <section className="settings-card settings-section" aria-labelledby="connections-title">
                    <header className="settings-card-heading"><span className="settings-card-icon"><Users size={19}/></span><div><h2 id="connections-title">Discovery & connections</h2><p>Choose how other explorers can find and connect with you.</p></div></header>
                    <div className="stack-form">{([['profile_visibility','Profile details'],['connections_visibility','Follower and following lists'],['activity_visibility','Activity on your profile']] as const).map(([key,label])=><Field key={key} label={label}><CustomSelect value={data[key]} onChange={value=>setData({...data,[key]:value})}><option value="everyone">Signed-in community</option><option value="friends">Friends</option><option value="private">Only me</option></CustomSelect></Field>)}</div><DiscoveryPreference checked={data.discovery_enabled} onChange={checked=>setData({...data,discovery_enabled:checked})}/>
                    <Field label="Who can send me friend requests?"><CustomSelect value={data.friend_request_privacy} onChange={value=>setData({...data,friend_request_privacy:value})}><option value="everyone">Everyone</option><option value="friends-of-friends">Friends of friends</option><option value="nobody">Nobody</option></CustomSelect></Field>
                </section>
        <ErrorBox error={action.error}/>
        <div className="settings-save-bar"><span><ShieldCheck size={16}/>Your profile, your preferences.</span><Button type="submit" busy={action.busy}><Save size={16}/>Save changes</Button></div>
            </div>
            <aside className="settings-aside" aria-label="Account and privacy">

                <section className="settings-card">
                    <header className="settings-card-heading"><span className="settings-card-icon"><Mail size={19}/></span><div><h2>Account</h2><p>Your sign-in details.</p></div></header>
                    <div className="settings-account-identity"><span>Email address</span><p>{user.email}</p><span className={'settings-verification '+(user.verified?'is-verified':'')}><ShieldCheck size={13}/>{user.verified?'Email verified':'Verification pending'}</span></div>
                    <div className="settings-account-links">{!user.verified&&<Link href="/verify-email">Verify your email<ArrowUpRight size={15}/></Link>}{(googleEnabled||user.google_linked)&&(user.google_linked?<button type="button" className="text-button" onClick={()=>setAccountModal('google')}>Google account linked<ShieldCheck size={14}/></button>:<a href="/auth/google">Link Google account<ExternalLink size={14}/></a>)}<button type="button" className="text-button" onClick={()=>{passwordAction.setError('');setAccountModal('password')}}>{user.has_local_password?'Change password':'Set password'}<ArrowUpRight size={15}/></button></div>
                </section>
                <section className="settings-card settings-privacy-card"><span className="settings-privacy-icon"><LockKeyhole size={21}/></span><h2>Private by default.</h2><p>Your conversations, plant collection, notes, and locations start private.</p><div className="settings-privacy-rule"/><p>You choose which discoveries, stories, plant cards, and location pins to share.</p></section>
                {blocked.length>0&&<section className="settings-card"><header className="settings-card-heading"><span className="settings-card-icon"><Ban size={18}/></span><div><h2>Blocked accounts</h2><p>Manage who can interact with you.</p></div></header>{blocked.map(person=><div className="blocked-row" key={person.id}><span>{person.name}</span><button type="button" className="text-button" disabled={action.busy} onClick={()=>action.run(async()=>{await api('/people/'+person.id+'/connections',{action:'unblock'});notify('Account unblocked.');refresh()})}>Unblock</button></div>)}</section>}
                {user.is_admin&&<section className="settings-card"><header className="settings-card-heading"><span className="settings-card-icon"><ShieldCheck size={19}/></span><div><h2>Administration</h2><p>A separate MFA check is required.</p></div></header><Link href="/admin" className="settings-admin-link">Open Admin System<ExternalLink size={14}/></Link></section>}
            </aside>
        </div>

        </form>
        {accountModal==='google'&&<Modal title="Google account" onClose={()=>setAccountModal(null)}><p>Your Google account is already linked.</p><Button onClick={()=>setAccountModal(null)}>Done</Button></Modal>}
        {accountModal==='password'&&<Modal title={user.has_local_password?'Change password':'Set a local password'} onClose={()=>{setAccountModal(null);setPasswords({current_password:'',password:'',password_confirmation:''})}}>{user.has_local_password?<form className="stack-form" onSubmit={event=>{event.preventDefault();passwordAction.run(async()=>{await api('/settings/password',passwords);setPasswords({current_password:'',password:'',password_confirmation:''});setAccountModal(null);notify('Password changed. Other sessions have been signed out.');refresh()})}}><Field label="Current password"><input type="password" required autoComplete="current-password" value={passwords.current_password} onChange={event=>setPasswords({...passwords,current_password:event.target.value})}/></Field><Field label="New password" hint="At least 10 characters, including letters and numbers."><input type="password" required minLength={10} autoComplete="new-password" value={passwords.password} onChange={event=>setPasswords({...passwords,password:event.target.value})}/></Field><Field label="Confirm new password"><input type="password" required autoComplete="new-password" value={passwords.password_confirmation} onChange={event=>setPasswords({...passwords,password_confirmation:event.target.value})}/></Field><ErrorBox error={passwordAction.error}/><Button type="submit" busy={passwordAction.busy}>Change password</Button></form>:<><p>We will send a secure, expiring password link to your account email. Your Google sign-in will stay linked.</p><ErrorBox error={passwordAction.error}/><Button busy={passwordAction.busy} onClick={()=>passwordAction.run(async()=>{const result=await api('/settings/password-link');notify(result.message);setAccountModal(null)})}>Email a password link</Button></>}</Modal>}
    </Shell>;
}
