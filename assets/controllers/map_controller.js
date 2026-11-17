import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus Controller managing interactive Leaflet maps with OSRM GeoJSON polyline.
 * Usage:
 *  <div data-controller="map"
 *       data-map-waypoints-value="[...]"
 *       data-map-geometry-value="{...}">
 *       <div data-map-target="container" class="h-full w-full"></div>
 *  </div>
 */
export default class extends Controller {
    static targets = ['container'];
    static values = {
        waypoints: { type: Array, default: [] },
        geometry: { type: Object, default: null },
        interactive: { type: Boolean, default: true },
        zoom: { type: Number, default: 8 }
    };

    connect() {
        if (typeof L === 'undefined') {
            console.warn('[map_controller] Leaflet L is not loaded.');
            return;
        }

        const mapElement = this.hasContainerTarget ? this.containerTarget : this.element;
        if (!mapElement) return;

        // Ensure Leaflet container has a unique id or is clean
        this.initMap(mapElement);
    }

    disconnect() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    initMap(element) {
        // Default center on Morocco (between Agadir and Marrakech)
        const defaultCenter = [31.0, -8.8];
        const initialCoords = (this.waypointsValue.length > 0)
            ? [this.waypointsValue[0].lat, this.waypointsValue[0].lng]
            : defaultCenter;

        this.map = L.map(element, {
            center: initialCoords,
            zoom: this.zoomValue,
            zoomControl: this.interactiveValue,
            dragging: this.interactiveValue,
            scrollWheelZoom: false,
            touchZoom: this.interactiveValue
        });

        // Clean Modern OpenStreetMap CartoDB Voyager or OSM Standard tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap contributors • SafarCar OSRM'
        }).addTo(this.map);

        const bounds = L.latLngBounds([]);

        // 1. Plot Stops Waypoints with Moroccan Badges
        this.waypointsValue.forEach((wp, index) => {
            const point = [wp.lat, wp.lng || wp.lon];
            bounds.extend(point);

            const isInitial = wp.isInitial ?? (index === 0);
            const isFinal = wp.isFinal ?? (index === this.waypointsValue.length - 1);

            let bgClass = 'bg-amber-500 border-white text-white';
            if (isInitial) bgClass = 'bg-emerald-600 border-white text-white';
            if (isFinal) bgClass = 'bg-teal-900 border-white text-white';

            const customIcon = L.divIcon({
                className: 'custom-map-marker',
                html: `
                    <div class="flex items-center justify-center w-7 h-7 rounded-full ${bgClass} border-2 shadow-md text-xs font-bold font-sans">
                        ${index + 1}
                    </div>
                `,
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            });

            const marker = L.marker(point, { icon: customIcon }).addTo(this.map);
            const popupHtml = `
                <div class="font-sans text-xs">
                    <p class="font-bold text-slate-900 text-sm">${wp.city || 'Escale'}</p>
                    <p class="text-slate-500">${wp.meetingPoint || ''}</p>
                    ${wp.time ? `<p class="text-emerald-700 font-semibold mt-1">🕒 ${wp.time}</p>` : ''}
                </div>
            `;
            marker.bindPopup(popupHtml);
        });

        // 2. Draw Real OSRM GeoJSON Road Geometry if available, otherwise straight polyline
        if (this.geometryValue && this.geometryValue.coordinates && this.geometryValue.coordinates.length > 0) {
            const geoJsonLayer = L.geoJSON(this.geometryValue, {
                style: {
                    color: '#0d9488', // Emerald brand primary
                    weight: 5,
                    opacity: 0.9,
                    lineJoin: 'round',
                    lineCap: 'round'
                }
            }).addTo(this.map);

            bounds.extend(geoJsonLayer.getBounds());
        } else if (this.waypointsValue.length >= 2) {
            const straightPoints = this.waypointsValue.map(wp => [wp.lat, wp.lng || wp.lon]);
            const polyline = L.polyline(straightPoints, {
                color: '#0d9488',
                weight: 5,
                opacity: 0.85,
                dashArray: '8, 8',
                lineCap: 'round'
            }).addTo(this.map);

            bounds.extend(polyline.getBounds());
        }

        if (bounds.isValid()) {
            this.map.fitBounds(bounds, { padding: [35, 35] });
        }
    }
}
