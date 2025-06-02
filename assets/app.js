import './styles/app.scss';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const map = new maplibregl.Map({
    container: 'map',
    style: '/style',
    center: [0, 0],
    zoom: 1,
    attributionControl: false
});
map.addControl(new maplibregl.AttributionControl(), 'top-left');

const rewind = document.getElementById('rewind');
rewind.value = 0;
const label = document.querySelector('#time label');
const oninput = () => {
    const time = new Date(rewind.dataset.time * 1000 +
        rewind.value * 3600000);
    label.innerText = time.toTimeString().split(':').slice(0, 2).join(':');
    ['cloud', 'cloud_edge'].forEach((layer) => {
        map.setFilter(layer, ['==', 'time', `${time.getUTCHours()}:00`]);
    })
};
oninput();
rewind.oninput = oninput;
const nextHour = () => {
    setTimeout(nextHour,3600000);
    rewind.dataset.time = (parseInt(rewind.dataset.time)+3600).toString();
    const clouds = map.getSource('clouds');
    clouds.setTiles(clouds.tiles.map((tile) => {
        const url = new URL(tile);
        url.searchParams.set('time', new Date().getTime().toString());
        return url.toString();
    }));
    oninput();
};
setTimeout(nextHour,(rewind.dataset.time*1000+3600000)-new Date().getTime());
