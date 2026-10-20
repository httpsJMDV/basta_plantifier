import {useState} from 'react';
import {Button,Field,ErrorBox} from './UI';
import {api} from '../lib';

export default function MunicipalityField({value,onChange}:{value:string;onChange:(value:string)=>void}){
    const [busy,setBusy]=useState(false),[error,setError]=useState(''),[proposed,setProposed]=useState('');
    async function locate(){
        setError('');setProposed('');
        if(!window.isSecureContext||!navigator.geolocation){setError('Automatic location needs a supported browser on HTTPS or localhost. Enter your city manually.');return}
        setBusy(true);
        try{
            const position=await new Promise<GeolocationPosition>((resolve,reject)=>navigator.geolocation.getCurrentPosition(resolve,reject,{enableHighAccuracy:false,timeout:10000,maximumAge:60000}));
            const result=await api('/location/municipality',{latitude:position.coords.latitude,longitude:position.coords.longitude});
            setProposed(result.municipality);
        }catch(caught){const code=(caught as GeolocationPositionError).code;setError(code===1?'Location permission was denied. Manual entry and Skip are still available.':code===3?'Location timed out. Try again or enter your city manually.':caught instanceof Error?caught.message:'Your location is unavailable. Enter your city manually.')}
        finally{setBusy(false)}
    }
    return <div className="municipality-field"><Field label="Municipality or city · optional" hint="Enter a broad area manually, or choose automatic lookup. Exact coordinates are not saved to your profile."><input maxLength={120} value={value} onChange={event=>onChange(event.target.value)}/></Field><p className="muted">Automatic lookup asks for browser permission and sends an approximate position to OpenStreetMap Nominatim. It does not enable discovery or public sharing. <a href="https://osmfoundation.org/wiki/Privacy_Policy" target="_blank" rel="noreferrer">Provider privacy</a></p><Button type="button" variant="secondary" busy={busy} onClick={locate}>Use my current location</Button><ErrorBox error={error}/>{proposed&&<div className="success-box"><p>Suggested city: {proposed}. Your existing entry stays until you choose to replace it.</p><Button type="button" onClick={()=>{onChange(proposed);setProposed('')}}>Use this city</Button><button type="button" className="text-button" onClick={()=>setProposed('')}>Keep my entry</button></div>}<small><a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">© OpenStreetMap contributors</a></small></div>;
}
