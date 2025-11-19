import './styles/app.scss';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
const parseHash = () => {
    return window.location.hash.length > 1 ?
            window.location.hash.slice(1).split(';') : []
}
let [lng, lat, zoom, bearing, pitch, roll] =  parseHash();
const debug = localStorage.getItem('debug') || false;
const style = localStorage.getItem('style') || '/style';
const initial = JSON.parse(localStorage.getItem('initial')) ||
    {lng: 37.618423, lat: 55.751244, zoom: 3, bearing: 180, pitch: 60, roll: 0};
const animationSpeed = localStorage.getItem('animationSpeed') || 110;
const animationStart = localStorage.getItem('animationStart') || 5000;
const rewind = document.getElementById('rewind');
let lastVal = rewind.value = 0;
const label = document.querySelector('#time label');
label.innerText = new Date(rewind.dataset.time*1000)
    .toTimeString().split(':').slice(0, 2).join(':');
const properties = {
    'cloud_shadow': ['fill-opacity'],
    'cloud_sky': ['fill-extrusion-opacity', 'fill-extrusion-base', 'fill-extrusion-height']
};
const layers = ['cloud_shadow', 'cloud_sky'];
const stages = ['a', 'b'];
let playInterval;
document.addEventListener('DOMContentLoaded', () => {
    const map = new maplibregl.Map({
        container: 'map', style, attributionControl: false,
        center: [isNaN(parseFloat(lng)) ? initial.lng : parseFloat(lng), isNaN(parseFloat(lat)) ? initial.lat : parseFloat(lat)],
        zoom: isNaN(parseFloat(zoom)) ? initial.zoom : parseFloat(zoom),
        bearing: isNaN(parseFloat(bearing)) ? initial.bearing : parseFloat(bearing),
        pitch: isNaN(parseFloat(pitch)) ? initial.pitch : parseFloat(pitch),
        roll: isNaN(parseFloat(roll)) ? initial.roll : parseFloat(roll)
    });
    map.addControl(new maplibregl.AttributionControl(), 'top-left');
    const updateAnchor = (e = null) => {
        if (e && e.popstate) return;
        const {lng, lat} = map.getCenter();
        const values = {
            lng, lat, zoom: map.getZoom(), bearing: map.getBearing(), pitch: map.getPitch(), roll: map.getRoll()
        }
        const position = Object.values(values).join(';');
        if (position === window.location.hash.slice(1)) return;
        if (debug) console.log(values);
        const params = [values, document.title, `#${position}`];
        window.location.hash.length > 1 ? history.pushState(...params) : history.replaceState(...params);
        localStorage.setItem('initial', JSON.stringify(values));
    };
    updateAnchor();
    ['moveend', 'dragend', 'zoomend', 'rotateend', 'pitchend'].forEach((event) => map.on(event, updateAnchor));
    const navigate = (state) => {
        if (debug) console.log(state);
        const eventData = {popstate: true};
        if (!isNaN(state.bearing)) map.setBearing(state.bearing, eventData);
        if (!isNaN(state.pitch)) map.setPitch(state.pitch, eventData);
        if (!isNaN(state.roll)) map.setRoll(state.roll, eventData);
        if (!isNaN(state.lng) && !isNaN(state.lat) && !isNaN(state.zoom)) {
            map.flyTo({center: [state.lng, state.lat], zoom: state.zoom}, eventData);
        }
    }
    window.addEventListener('popstate', (e) => {
        const state = e.state;
        if (!state) return;
        navigate(state);
    });
    window.addEventListener('hashchange', () => {
        let [lng, lat, zoom, bearing, pitch, roll] = parseHash();
        navigate({
            lng: parseFloat(lng),
            lat: parseFloat(lat),
            zoom: parseFloat(zoom),
            bearing: parseFloat(bearing),
            pitch: parseFloat(pitch),
            roll: parseFloat(roll)
        });
    });
    const getTime = offset => `${('0' + new Date(rewind.dataset.time * 1000 + offset * 3600000).getUTCHours()).slice(-2)}:00`;
    const setupAnimation = () => {
        clearInterval(playInterval);
        playInterval = setInterval(() => {
            let val = parseInt(rewind.value) + 1;
            rewind.value = val > 0 ? rewind.min : val;
            oninput(false);
        }, animationSpeed);
    }
    let playTimeout = setTimeout(setupAnimation, animationStart * 3);
    const oninput = (trigger = true) => {
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
        if (trigger === false) return;
        clearTimeout(playTimeout);
        clearInterval(playInterval);
        if (rewind.value !== rewind.min) return;
        playTimeout = setTimeout(setupAnimation, animationStart);
    };
    rewind.oninput = oninput;
    setTimeout(window.location.reload, (rewind.dataset.time*1000+3600000)-new Date().getTime());
});
