import './styles/app.css';
import React from 'react';
import {createRoot} from 'react-dom/client';
import {createInertiaApp} from '@inertiajs/react';
const pages=import.meta.glob<{default:React.ComponentType<any>}>('./pages/**/*.tsx');
createInertiaApp({title:title=>title ? title+' · PlantifierAI' : 'PlantifierAI',resolve:async name=>(await pages['./pages/'+name+'.tsx']()).default,setup({el,App,props}){createRoot(el).render(<React.StrictMode><App {...props}/></React.StrictMode>)},progress:{color:'#244D3A'}});
