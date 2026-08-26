// Livewire ships and initializes its own Alpine.js instance (see @livewireScripts
// in resources/views/components/layouts/app.blade.php) — don't import/start a
// separate Alpine here, it would double-initialize. Register data/plugins on
// the 'alpine:init' event instead, same as the layout's inline authModal store.
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet's default marker icon paths assume a non-bundled <script> tag next
// to its CSS file — under Vite they 404 unless re-pointed at the bundled URLs.
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

document.addEventListener('alpine:init', () => {
    Alpine.data('storeLocatorMap', (locations) => ({
        map: null,
        init() {
            if (!locations.length) {
                return;
            }

            this.map = L.map(this.$el).setView([locations[0].lat, locations[0].lng], 11);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);

            const markers = locations
                .filter((location) => location.lat && location.lng)
                .map((location) => L.marker([location.lat, location.lng]).bindPopup(
                    `<strong>${location.city}</strong><br>${location.address}`
                ).addTo(this.map));

            if (markers.length > 1) {
                this.map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2));
            }
        },
    }));
});
