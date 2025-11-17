import './styles/app.scss';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const rewind = document.getElementById('rewind');
let lastVal = rewind.value = 0;
const label = document.querySelector('#time label');
label.innerText = new Date(rewind.dataset.time*1000)
    .toTimeString().split(':').slice(0, 2).join(':');
const [lng, lat, zoom, bearing, pitch, roll] =  window.location.hash.length > 1 ?
window.location.hash.slice(1).split(';') : [];
const map = new maplibregl.Map({
    container: 'map',
    style: '/style',
    center: [isNaN(parseFloat(lng)) ? 37.618423 : parseFloat(lng), isNaN(parseFloat(lat)) ? 55.751244 : parseFloat(lat)],
    zoom: isNaN(parseFloat(zoom)) ? 4 : parseFloat(zoom),
    bearing: isNaN(parseFloat(bearing)) ? -60 : parseFloat(bearing),
    pitch: isNaN(parseFloat(pitch)) ? 60 : parseFloat(pitch),
    roll: isNaN(parseFloat(roll)) ? 0 : parseFloat(roll),
    attributionControl: false,
    minZoom: 1
});
map.addControl(new maplibregl.AttributionControl(), 'top-left');
const updateAnchor = (e = null) => {
    if (e && e.popstate) {
        return;
    }
    const {lng, lat} = map.getCenter();
    const position = '#'+[lng, lat, map.getZoom(), map.getBearing(), map.getPitch(), map.getRoll()].join(';');
    if (position === window.location.hash) {
        return;
    }
    history.pushState({}, '', position);
};
updateAnchor();
['moveend', 'dragend', 'zoomend', 'rotateend', 'pitchend'].forEach((event) => map.on(event, updateAnchor));
window.addEventListener('popstate', () => {
    const eventData = {popstate: true};
    const [lng, lat, zoom, bearing, pitch, roll] = window.location.hash.slice(1).split(';') 
    if (!isNaN(parseFloat(bearing))) {
        map.setBearing(parseFloat(bearing), eventData);
    }
    if (!isNaN(parseFloat(pitch))) {
        map.setPitch(parseFloat(pitch), eventData);
    }
    if (!isNaN(parseFloat(roll))) {
        map.setRoll(parseFloat(roll), eventData);
    }
    if (!isNaN(parseFloat(lng)) && !isNaN(parseFloat(lat) && !isNaN(parseFloat(zoom)))) {
        map.flyTo({center: [parseFloat(lng), parseFloat(lat)], zoom: parseFloat(zoom)}, eventData);
    }
});
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
    window.location.reload();
};
setTimeout(nextHour,(rewind.dataset.time*1000+3600000)-new Date().getTime());
