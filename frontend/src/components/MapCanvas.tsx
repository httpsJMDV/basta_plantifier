import {useEffect,useRef} from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import type {Pin} from '../types';

export default function MapCanvas({pins,onPick,selected,onSelect}:{pins:Pin[];onPick?:(lat:number,lng:number)=>void;selected:number|null;onSelect:(id:number)=>void}){
    const container=useRef<HTMLDivElement>(null);
    const map=useRef<L.Map|null>(null);
    const layer=useRef<L.LayerGroup|null>(null);
    const callbacks=useRef({onPick,onSelect});
    callbacks.current={onPick,onSelect};

    useEffect(()=>{
        if(!container.current)return;
        const instance=L.map(container.current,{scrollWheelZoom:true}).setView([12.6,122.5],5);
        map.current=instance;
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
            maxZoom:19,
            attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(instance);
        layer.current=L.layerGroup().addTo(instance);
        instance.on('click',event=>callbacks.current.onPick?.(event.latlng.lat,event.latlng.lng));
        const resize=new ResizeObserver(()=>instance.invalidateSize());
        resize.observe(container.current);
        return()=>{resize.disconnect();instance.remove();map.current=null};
    },[]);

    useEffect(()=>{
        if(!layer.current||!map.current)return;
        layer.current.clearLayers();
        pins.forEach(pin=>{
            const label=document.createElement('span');
            label.textContent=pin.title;
            L.circleMarker([pin.latitude,pin.longitude],{
                radius:pin.id===selected?11:7,
                color:pin.approximate?'#805035':'#244d3a',
                fillColor:pin.id===selected?'#244d3a':'#a8bfa5',
                fillOpacity:0.9,
                weight:2,
            }).bindTooltip(label,{direction:'top'})
                .on('click',()=>callbacks.current.onSelect(pin.id))
                .addTo(layer.current!);
            if(pin.approximate){
                L.circle([pin.latitude,pin.longitude],{
                    radius:8000,color:'#805035',fillOpacity:0.06,weight:1,
                }).addTo(layer.current!);
            }
        });
        if(pins.length&&!selected){
            map.current.fitBounds(L.latLngBounds(pins.map(pin=>[pin.latitude,pin.longitude] as [number,number])),{
                padding:[40,40],maxZoom:12,
            });
        }
        if(selected){
            const pin=pins.find(item=>item.id===selected);
            if(pin)map.current.panTo([pin.latitude,pin.longitude]);
        }
    },[pins,selected]);

    return <div className="leaflet-map" ref={container} aria-label="Plant discovery map"/>;
}
