import './styles/app.scss';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const rewind = document.getElementById('rewind');
let lastVal= rewind.value = 0;
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
let t = 0;
const advance = (v, diff = 1) =>
    diff > 0 ? v > 1 ? 0 : v + 1 : v < 1 ? 2 : v - 1;
const b = 1.25;
let timeout = null;
const layers = ['cloud_shadow', 'cloud_sky'];
const setFilter = (buffer, value) => layers.forEach((layer) => {
    map.setFilter(`${layer}_${buffer}`, ['==', 'time', `${('0' + new Date(
        rewind.dataset.time * 1000 + value * 3600000).getUTCHours()).slice(-2)}:00`]);
});
const oninput = (trigger = true) => {
    const d = lastVal - rewind.value;
    const time = new Date(rewind.dataset.time * 1000 +
        rewind.value * 3600000);
    label.innerText = time.toTimeString().split(':').slice(0, 2).join(':');
    clearTimeout(timeout);
    if (timeout === null) {
        setFilter(advance(t, d), rewind.value);
    }
    timeout = setTimeout(() => {
        timeout = null;
        layers.forEach((layer) => {
            const duration = map.getPaintProperty(`${layer}_${advance(t, d)}`, `${properties[layer]}-transition`).duration / b;
            map.setPaintProperty(`${layer}_${advance(t, d)}`,
                `${properties[layer]}-transition`, {duration});
            map.setPaintProperty(`${layer}_${advance(t, d)}`, properties[layer],
                map.getPaintProperty(`${layer}_${t}`, properties[layer]));
            map.setPaintProperty(`${layer}_${t}`, `${properties[layer]}-transition`,
                {duration: duration * b});
            map.setPaintProperty(`${layer}_${t}`, properties[layer], 0);
        });
        t = advance(t, d);
        let val = parseInt(rewind.value);
        let minVal = parseInt(rewind.min);
        if (val > minVal) {
            setFilter(advance(t, 1), rewind.value - 1);
        }
        setFilter(advance(t, -1), val < 0 ? val + 1 : minVal);
    }, 100);
    lastVal = rewind.value;
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
        }, 710);
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
