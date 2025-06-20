import './styles/app.scss';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const rewind = document.getElementById('rewind');
let lastVal = rewind.value = 0;
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
const setupAnimation = () => {
    clearInterval(playInterval);
    playInterval = setInterval(() => {
        let val = parseInt(rewind.value) + 1;
        rewind.value = val > 0 ? rewind.min : val;
        oninput(false);
    }, 110);
}
let playTimeout = setTimeout(setupAnimation, 15000);
const properties = {
    'cloud_shadow': ['fill-opacity'],
    'cloud_sky': ['fill-extrusion-opacity', 'fill-extrusion-base', 'fill-extrusion-height']
};
const layers = ['cloud_shadow', 'cloud_sky'];
const stages = ['a', 'b'];
const getTime = offset => `${('0' + new Date(rewind.dataset.time * 1000 + offset * 3600000).getUTCHours()).slice(-2)}:00`;
const oninput = (trigger = true) => {
    const d = rewind.value === rewind.min && lastVal === '0' ? -1 :
        rewind.value === '0' && lastVal === rewind.min ? 1 : lastVal - rewind.value;
    const time = new Date(rewind.dataset.time * 1000 +
        rewind.value * 3600000);
    label.innerText = time.toTimeString().split(':').slice(0, 2).join(':');
    if (lastVal === rewind.value) return;
    layers.forEach((layer) => {
        stages.forEach((stage) => {
            properties[layer].forEach(property => {
                map.setPaintProperty(`${layer}_${stage}_${getTime(rewind.value)}`, property,
                    map.getPaintProperty(`${layer}_${stage}_${getTime(lastVal)}`, property));
                map.setPaintProperty(`${layer}_${stage}_${getTime(lastVal)}`, property, 0);
            });
        });
    });
    lastVal = rewind.value;
    if (trigger === false) {
        return;
    }
    clearTimeout(playTimeout);
    clearInterval(playInterval);
    if (rewind.value !== rewind.min) {
        return;
    }
    playTimeout = setTimeout(setupAnimation, 5000);
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
