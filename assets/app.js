import './styles/app.scss';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const map = new maplibregl.Map({
    container: 'map',
    style: '/style',
    center: [0, 0],
    zoom: 1
});
window.debugMap = map;

const rewind = document.getElementById('rewind');
rewind.value = 0;
const label = document.querySelector('#time label');
label.innerText = new Date(rewind.dataset.time*1000)
    .toLocaleTimeString().split(':').slice(0, 2).join(':');
rewind.oninput = () => {
    const time = new Date(rewind.dataset.time * 1000 +
        rewind.value * 3600000);
    label.innerText = time.toLocaleTimeString().split(':').slice(0, 2).join(':');
    const clouds = map.getSource('clouds');
    clouds.setTiles(clouds.tiles.map((tile) => {
        const url = new URL(tile);
        url.pathname = `clouds/${time.getUTCHours()}:00`;
        return url.toString();
    }));
};
