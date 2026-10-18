import {useId} from 'react';
import {Users,ShieldCheck} from 'lucide-react';

export default function DiscoveryPreference({checked,onChange}:{checked:boolean;onChange:(checked:boolean)=>void}){
    const id=useId();
    return <label className={'discovery-preference '+(checked?'is-enabled':'')}>
        <span className="discovery-preference-icon" aria-hidden="true"><Users size={21}/></span>
        <span className="discovery-preference-copy"><span className="discovery-preference-heading"><strong id={id+'-title'}>Profile discovery</strong><span className="discovery-preference-status" aria-hidden="true">{checked?'On':'Off'}</span></span><span id={id+'-description'} className="discovery-preference-description">Help other plant enthusiasts find you through shared interests and your general area.</span></span>
        <input className="discovery-switch" type="checkbox" role="switch" checked={checked} onChange={event=>onChange(event.target.checked)} aria-labelledby={id+'-title'} aria-describedby={id+'-description '+id+'-privacy'}/>
        <span id={id+'-privacy'} className="discovery-preference-privacy"><ShieldCheck size={15} aria-hidden="true"/>Your exact location stays private. Change this anytime in Settings.</span>
    </label>;
}
