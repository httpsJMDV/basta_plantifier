import {useEffect,useRef,useState} from 'react';
import {Camera,Upload,Trash2} from 'lucide-react';
import {router} from '@inertiajs/react';
import Avatar from './Avatar';
import {Button,ErrorBox} from './UI';
import {api,multipart,notify,useAction} from '../lib';

export default function ProfilePhotoEditor({name,src}:{name:string;src:string|null}){
    const fileInput=useRef<HTMLInputElement>(null);const [file,setFile]=useState<File|null>(null),[preview,setPreview]=useState('');const action=useAction();
    useEffect(()=>{if(!file){setPreview('');return}const url=URL.createObjectURL(file);setPreview(url);return()=>URL.revokeObjectURL(url)},[file]);
    const choose=(photo?:File)=>{if(!photo)return;action.setError('');if(!['image/png','image/jpeg'].includes(photo.type)||photo.size>5*1024*1024){action.setError('Choose a PNG or JPEG image up to 5 MB.');return}setFile(photo)};
    const update=()=>action.run(async()=>{await api('/settings/photo',multipart({image:file}));router.reload({onSuccess:()=>setFile(null)});notify('Profile photo updated.')});
    const remove=()=>action.run(async()=>{await api('/settings/photo',{},'DELETE');router.reload();setFile(null);notify('Profile photo removed.')});
    return <section className="profile-photo-editor" aria-labelledby="profile-photo-title"><div className="profile-photo-preview"><Avatar name={name} src={preview||src} className="profile-photo-avatar"/><span className="profile-photo-camera" aria-hidden="true"><Camera size={15}/></span></div><div className="profile-photo-content"><h3 id="profile-photo-title">Profile photo</h3><p>Make your profile feel like you.</p><span className="profile-photo-help" id="profile-photo-help">PNG or JPEG, up to 5 MB. Visible to the signed-in community.</span><input ref={fileInput} type="file" hidden accept="image/png,image/jpeg" aria-label="Choose profile photo" aria-describedby="profile-photo-help" onChange={event=>{choose(event.target.files?.[0]);event.target.value=''}}/><div className="profile-photo-actions"><Button type="button" variant="secondary small" disabled={action.busy} onClick={()=>fileInput.current?.click()}><Upload size={15}/>{src?'Change photo':'Upload photo'}</Button>{file&&<><Button type="button" variant="small" busy={action.busy} onClick={update}>Save photo</Button><button type="button" className="text-button" disabled={action.busy} onClick={()=>setFile(null)}>Cancel</button></>}{src&&!file&&<button type="button" className="text-button photo-remove" disabled={action.busy} onClick={remove}><Trash2 size={14}/>Remove</button>}</div>{file&&<p className="photo-pending" role="status">Preview ready. Save photo to apply it.</p>}<ErrorBox error={action.error}/></div></section>;
}
