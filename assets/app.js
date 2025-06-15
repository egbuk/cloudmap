import './styles/app.scss';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const rewind = document.getElementById('rewind');
rewind.value = 0;
const label = document.querySelector('#time label');
label.innerText = new Date(rewind.dataset.time*1000)
    .toTimeString().split(':').slice(0, 2).join(':');
const map = new maplibregl.Map({
    container: 'map',
    style: '/style',
    center: [0, 0],
    zoom: 1,
    attributionControl: false
});
map.addControl(new maplibregl.AttributionControl(), 'top-left');
let playInterval;
let playTimeout;
const properties = {
    'cloud_shadow': 'fill-opacity',
    'cloud_sky': 'fill-extrusion-opacity'
};
let t = 'a';
const invert = (v) => v === 'a' ? 'b' : 'a';
const b = 1.25;
let timeout;
const oninput = (trigger = true) => {
    const time = new Date(rewind.dataset.time * 1000 +
        rewind.value * 3600000);
    label.innerText = time.toTimeString().split(':').slice(0, 2).join(':');
    ['cloud_shadow', 'cloud_sky'].forEach((layer) => {
        map.setFilter(`${layer}_${invert(t)}`, ['==', 'time', `${('0'+time.getUTCHours()).slice(-2)}:00`]);
    });
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        ['cloud_shadow', 'cloud_sky'].forEach((layer) => {
            const duration = map.getPaintProperty(`${layer}_${invert(t)}`, `${properties[layer]}-transition`).duration / b;
            map.setPaintProperty(`${layer}_${invert(t)}`, `${properties[layer]}-transition`, {duration});
            map.setPaintProperty(`${layer}_${invert(t)}`, properties[layer],
                map.getPaintProperty(`${layer}_${t}`, properties[layer]));
            map.setPaintProperty(`${layer}_${t}`, `${properties[layer]}-transition`, {duration: duration * b});
            map.setPaintProperty(`${layer}_${t}`, properties[layer], 0);
        });
        t = invert(t);
    }, 50);
    if (trigger === false) {
        return;
    }
    clearTimeout(playTimeout);
    clearInterval(playInterval);
    if (rewind.value !== rewind.min) {
        return;
    }
    playTimeout = setTimeout(() => {
        clearInterval(playInterval);
        playInterval = setInterval(() => {
            let val = parseInt(rewind.value) + 1;
            rewind.value = val > 0 ? rewind.min : val;
            oninput(false);
        }, 1000);
    }, 5000);
};
rewind.oninput = oninput;
const nextHour = () => {
    setTimeout(nextHour,3600000);
    rewind.dataset.time = (parseInt(rewind.dataset.time)+3600).toString();
    const clouds = map.getSource('clouds');
    clouds.setTiles(clouds.tiles.map((tile) => {
        const url = new URL(tile);
        url.searchParams.set('time', new Date().getTime().toString());
        return decodeURI(url.toString());
    }));
    oninput(false);
};
setTimeout(nextHour,(rewind.dataset.time*1000+3600000)-new Date().getTime());
