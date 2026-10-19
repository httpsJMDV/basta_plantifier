import {Head,Link} from '@inertiajs/react';
import {useRef,useState} from 'react';
import {ArrowDown,ArrowRight,ArrowUpRight,Bookmark,Camera,Check,Compass,Leaf,LockKeyhole,MapPin,Menu,MessageCircle,NotebookPen,ScanLine,ShieldCheck,Sprout,Users,X} from 'lucide-react';
import {Brand} from '../components/UI';
import canopy from '../assets/landing/canopy.webp';
import garden from '../assets/landing/garden.webp';
import leaves from '../assets/landing/leaves.webp';
import '../styles/landing.css';

const steps=[
    {icon:Camera,title:'Start with a photograph.',body:'Capture a plant or upload up to three photographs of the same plant. A clear leaf, stem, or flower is a good beginning.'},
    {icon:ScanLine,title:'Find a possible match.',body:'Pl@ntNet suggests possible species, with identification scores and alternative candidates when available.'},
    {icon:MessageCircle,title:'Follow your curiosity.',body:'Ask questions, explore characteristics, and understand care guidance through Groq-powered conversations.'},
    {icon:Bookmark,title:'Keep the discovery.',body:'Save a plant, document an observation, or choose something worth sharing with the community.'},
];

export default function Landing(){
    const [menuOpen,setMenuOpen]=useState(false);
    const menuButton=useRef<HTMLButtonElement>(null);
    return <div className="landing-page">
        <Head title="Every plant has a story">
            <meta name="description" content="Identify possible plant species, explore botanical information, keep a personal plant collection, and connect with a community of curious explorers on PlantifierAI."/>
        </Head>
        <a className="landing-skip" href="#main-content">Skip to content</a>
        <header className="landing-header" onKeyDown={event=>{if(event.key==='Escape'&&menuOpen){setMenuOpen(false);menuButton.current?.focus()}}}>
            <div className="landing-nav-wrap">
                <Link href="/" aria-label="PlantifierAI home" className="landing-brand"><Brand/></Link>
                <button ref={menuButton} type="button" className="landing-menu-toggle" aria-expanded={menuOpen} aria-controls="landing-navigation" aria-label={menuOpen?'Close navigation menu':'Open navigation menu'} onClick={()=>setMenuOpen(!menuOpen)}>{menuOpen?<X size={23}/>:<Menu size={23}/>}</button>
                <nav id="landing-navigation" className={menuOpen?'is-open':''} aria-label="Main navigation">
                    <div className="landing-nav-sections"><a href="#features" onClick={()=>setMenuOpen(false)}>Features</a><a href="#how-it-works" onClick={()=>setMenuOpen(false)}>How It Works</a><a href="#about" onClick={()=>setMenuOpen(false)}>About</a></div>
                    <div className="landing-nav-account"><Link href="/login">Log In</Link><Link href="/register" className="landing-button landing-button-small">Get Started<ArrowUpRight size={16}/></Link></div>
                </nav>
            </div>
        </header>
        <main id="main-content" tabIndex={-1}>
            <section className="landing-hero landing-container" aria-labelledby="hero-title">
                <div className="landing-hero-copy">
                    <p className="landing-eyebrow"><span/>A FIELD GUIDE FOR YOUR EVERYDAY</p>
                    <h1 id="hero-title">Every plant<br/>has a story.<br/><em>Discover it.</em></h1>
                    <p className="landing-lead">Identify plants through photographs, explore their characteristics, document your discoveries, and connect with people who share your curiosity about nature.</p>
                    <div className="landing-actions"><Link href="/register" className="landing-button">Get Started<ArrowUpRight size={18}/></Link><a href="#features" className="landing-text-link">Explore Features<ArrowRight size={17}/></a></div>
                    <div className="landing-hero-footnote"><span>DISCOVER · IDENTIFY · DOCUMENT · CONNECT</span><a href="#introduction" aria-label="Discover more about PlantifierAI"><ArrowDown size={19}/></a></div>
                </div>
                <div className="landing-hero-art">
                    <span className="landing-photo-index">FIELD NOTES / 001</span>
                    <figure className="landing-hero-photo"><img src={canopy} alt="A close view of overlapping green leaves with deep natural splits" width={1000} height={1333} fetchPriority="high"/><figcaption><Leaf size={16}/><span>A little closer to nature.</span></figcaption></figure>
                    <div className="landing-photo-note"><span className="landing-eyebrow">A PRACTICE OF NOTICING</span><p>The leaf. The light.<br/>The questions that follow.</p></div>
                </div>
            </section>

            <section id="introduction" className="landing-intro landing-container" aria-labelledby="intro-title">
                <div className="landing-section-heading"><p className="landing-eyebrow">ROOTED IN CURIOSITY</p><h2 id="intro-title">A closer look at the<br/>world around you.</h2></div>
                <div className="landing-intro-content"><p className="landing-section-description">PlantifierAI is a botanical discovery platform that combines image-based plant identification, conversational AI, personal plant documentation, and community exploration in one connected experience.</p>
                    <div className="landing-pillars">
                        <div><span>01 /</span><h3>Identify</h3><p>Discover possible plant species using Pl@ntNet.</p></div>
                        <div><span>02 /</span><h3>Understand</h3><p>Explore botanical information through Groq-powered conversations.</p></div>
                        <div><span>03 /</span><h3>Preserve</h3><p>Save discoveries and maintain your personal botanical collection.</p></div>
                    </div>
                </div>
            </section>

            <section id="how-it-works" className="landing-process" aria-labelledby="process-title"><div className="landing-container">
                <div className="landing-process-heading"><div><p className="landing-eyebrow">THE DISCOVERY PROCESS</p><h2 id="process-title">From a photograph<br/>to a discovery.</h2></div><p>No botanical vocabulary needed.<br/>Just something green and a good question.</p></div>
                <ol className="landing-steps">{steps.map(({icon:Icon,title,body},index)=><li key={title}><div className="landing-step-top"><span>0{index+1}</span><Icon size={23} strokeWidth={1.4}/></div><h3>{title}</h3><p>{body}</p></li>)}</ol>
                <p className="landing-process-note"><ShieldCheck size={16}/>Identification is a starting point for learning, never a guarantee. Confirm uncertain results before relying on them.</p>
            </div></section>

            <div id="features" className="landing-container landing-features">
                <div className="landing-feature-kicker"><p className="landing-eyebrow">MADE FOR THE WAY YOU EXPLORE</p><span>From first questions to field notes.</span></div>
                <section className="landing-feature-row" aria-labelledby="archive-title">
                    <figure className="landing-archive-photo"><img src={garden} alt="A potting bench with soil, a hand trowel, scissors, and small pots" width={1000} height={667} loading="lazy" decoding="async"/><figcaption><span>OBSERVE. RETURN. NOTICE WHAT CHANGES.</span><NotebookPen size={18}/></figcaption></figure>
                    <div className="landing-feature-copy"><p className="landing-eyebrow">01 / COLLECT & DOCUMENT</p><h2 id="archive-title">Your personal<br/>botanical archive.</h2><p>A name is only the beginning. Keep a record of the plants you meet and the small changes you notice along the way.</p><ul className="landing-feature-list"><li><Bookmark size={18}/><div><h3>My Plant Collection</h3><p>Keep your discoveries and their botanical details together.</p></div></li><li><NotebookPen size={18}/><div><h3>Notes & Journal</h3><p>Save observations, questions, and thoughts from the field.</p></div></li><li><Sprout size={18}/><div><h3>Plant Growth Journal</h3><p>Return to a plant with new photographs, measurements, and dated observations.</p></div></li></ul></div>
                </section>
                <section className="landing-feature-row landing-feature-reverse" aria-labelledby="explore-title">
                    <div className="landing-feature-copy"><p className="landing-eyebrow">02 / WANDER & NOTICE</p><h2 id="explore-title">Discover plants<br/>beyond your backyard.</h2><p>Give your discoveries a sense of place. Explore shared plant observations on an interactive map, or keep a location just for yourself.</p><ul className="landing-inline-features"><li><Compass size={17}/>Gallery & Location</li><li><MapPin size={17}/>Public discovery maps</li><li><LockKeyhole size={17}/>Privacy-controlled observations</li></ul><a href="#privacy" className="landing-text-link">Explore on your own terms<ArrowRight size={17}/></a></div>
                    <figure className="landing-place-photo"><img src={leaves} alt="A slender green plant reaching upward against a pale background" width={1200} height={901} loading="lazy" decoding="async"/><figcaption><MapPin size={18}/><div><strong>A place worth remembering.</strong><span>Keep it private. Share a general area when you choose.</span></div></figcaption></figure>
                </section>
            </div>

            <section className="landing-community" aria-labelledby="community-title"><div className="landing-container">
                <div className="landing-community-heading"><div><p className="landing-eyebrow">03 / CONNECT & LEARN</p><h2 id="community-title">Curiosity grows<br/><em>better together.</em></h2></div><div><p>Trade observations with someone who notices the same things. Ask for a second opinion, follow a fellow explorer, or share a small discovery from your day.</p><Link href="/register" className="landing-text-link">Find your plant people<ArrowUpRight size={17}/></Link></div></div>
                <div className="landing-community-details"><div><Users size={23} strokeWidth={1.5}/><h3>A shared field of interest.</h3><p>Community posts, friends, and following. Opt in to discover people through shared interests and your general area.</p></div><div><Leaf size={23} strokeWidth={1.5}/><h3>Another pair of eyes.</h3><p>Post an identification request, discuss possible matches, and compare the evidence with other explorers.</p></div><div><MessageCircle size={23} strokeWidth={1.5}/><h3>Keep the conversation growing.</h3><p>Share moments through Stories or take a conversation to Messenger.</p></div></div>
            </div></section>

            <section className="landing-confidence landing-container" aria-labelledby="confidence-title">
                <div><p className="landing-eyebrow">04 / QUESTION & VERIFY</p><h2 id="confidence-title">Explore with curiosity,<br/>not false certainty.</h2></div>
                <div><p className="landing-section-description">A thoughtful answer leaves room for uncertainty. PlantifierAI keeps possible matches and their evidence in view, so you can keep asking better questions.</p><ul className="landing-check-list"><li><Check size={17}/>Alternative identification suggestions and provider scores</li><li><Check size={17}/>Correction reporting that preserves the original result</li><li><Check size={17}/>Community discussion to help examine an identification</li></ul><p className="landing-evidence-note">Community input and AI suggestions are not expert verification. Never use an identification alone to decide whether a plant is safe to eat.</p></div>
            </section>

            <section id="privacy" className="landing-privacy" aria-labelledby="privacy-title"><div className="landing-container landing-privacy-inner"><div className="landing-privacy-symbol"><LockKeyhole size={35} strokeWidth={1.2}/><span>PRIVATE BY DEFAULT</span></div><div><p className="landing-eyebrow">A LITTLE SPACE OF YOUR OWN</p><h2 id="privacy-title">Your discoveries.<br/>Your choice to share.</h2></div><div><p>Your personal plant records, notes, conversations, and locations start private. You decide which discoveries become part of the public conversation.</p><p>Public location sharing is optional. Use approximate locations to share a general area while keeping a sensitive spot to yourself.</p></div></div></section>

            <section id="about" className="landing-about landing-container" aria-labelledby="about-title"><div><p className="landing-eyebrow">THE IDEA BEHIND PLANTIFIERAI</p><h2 id="about-title">Built for curious minds<br/>and the natural world.</h2><p>PlantifierAI is a web-based botanical discovery and community platform designed to make plant exploration, documentation, and collaborative learning more accessible. From a houseplant on your windowsill to a leaf on your next walk, there is always something to notice.</p></div><div className="landing-about-tools"><p className="landing-eyebrow">TWO TOOLS. ONE CONNECTED EXPERIENCE.</p><a href="https://plantnet.org/en/" target="_blank" rel="noreferrer"><span><Leaf size={20}/><strong>Pl@ntNet</strong></span><ArrowUpRight size={17}/><p>Image-based identification provides possible species, scores, and alternatives.</p></a><a href="https://groq.com/" target="_blank" rel="noreferrer"><span><MessageCircle size={20}/><strong>Groq</strong></span><ArrowUpRight size={17}/><p>Conversational assistance turns the identification context into explanations and useful follow-up questions.</p></a><small>Plant identification powered by Pl@ntNet. Botanical explanations generated with Groq.</small></div></section>

            <section className="landing-final" aria-labelledby="final-title"><div className="landing-container"><div className="landing-final-top"><p className="landing-eyebrow">YOUR NEXT DISCOVERY IS WAITING</p><Leaf size={34} strokeWidth={1}/></div><h2 id="final-title">There’s more growing<br/>around you <em>than you think.</em></h2><div className="landing-final-bottom"><p>Start exploring, identify your first plant, and build a collection of discoveries worth keeping.</p><Link href="/register" className="landing-button">Start Exploring<ArrowUpRight size={19}/></Link></div></div></section>
        </main>
        <footer className="landing-footer landing-container"><div className="landing-footer-top"><div className="landing-footer-brand"><Link href="/" aria-label="PlantifierAI home"><Brand/></Link><p>A thoughtful space to identify, understand,<br/>and keep a record of the plants around you.</p></div><nav aria-label="Footer navigation"><a href="#features">Features</a><a href="#how-it-works">How It Works</a><a href="#about">About</a><a href="#privacy">Privacy</a><a href="#contact">Contact</a><Link href="/login">Log In</Link></nav><div id="contact" tabIndex={-1} className="landing-contact"><h2>Good questions grow here.</h2><p>Have an observation or a botanical question to share? Join the conversation in Community.</p><Link href="/register" className="landing-text-link">Join the community<ArrowUpRight size={15}/></Link></div></div><div className="landing-footer-bottom"><span>© {new Date().getFullYear()} PlantifierAI. Made for the naturally curious.</span><span>Identification: <a href="https://plantnet.org/en/" target="_blank" rel="noreferrer">Pl@ntNet</a><span aria-hidden="true"> · </span>Conversations: <a href="https://groq.com/" target="_blank" rel="noreferrer">Groq</a></span></div><details className="landing-photo-credits"><summary>Photography & credits</summary><p>Botanical photographs from Unsplash, used under the <a href="https://unsplash.com/license" target="_blank" rel="noreferrer">Unsplash License</a>: <a href="https://images.unsplash.com/photo-1525498128493-380d1990a112" target="_blank" rel="noreferrer">leaf study</a>, <a href="https://images.unsplash.com/photo-1416879595882-3373a0480b5b" target="_blank" rel="noreferrer">potting bench</a>, and <a href="https://images.unsplash.com/photo-1501004318641-b39e6451bec6" target="_blank" rel="noreferrer">plant study</a>. Photographs are editorial imagery, not identification results or user discoveries.</p></details></footer>
    </div>;
}
