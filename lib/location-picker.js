// lib/location-picker.js
class LocationPicker {
    constructor(inputId, mapId) {
        this.input = document.getElementById(inputId);
        this.mapContainer = document.getElementById(mapId);
        this.map = null;
        this.marker = null;
        this.autocomplete = null;
    }
    
    init() {
        // Load Google Maps API
        if (typeof google === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&libraries=places';
            script.onload = () => this.initializeMap();
            document.head.appendChild(script);
        } else {
            this.initializeMap();
        }
    }
    
    initializeMap() {
        // Initialize map centered on Nigeria
        this.map = new google.maps.Map(this.mapContainer, {
            center: { lat: 9.0820, lng: 8.6753 },
            zoom: 6,
            mapTypeControl: false,
            streetViewControl: false
        });
        
        // Initialize autocomplete
        this.autocomplete = new google.maps.places.Autocomplete(this.input);
        this.autocomplete.bindTo('bounds', this.map);
        
        // Handle place selection
        this.autocomplete.addListener('place_changed', () => {
            const place = this.autocomplete.getPlace();
            if (!place.geometry) return;
            
            // Center map on selected location
            if (place.geometry.viewport) {
                this.map.fitBounds(place.geometry.viewport);
            } else {
                this.map.setCenter(place.geometry.location);
                this.map.setZoom(17);
            }
            
            // Add marker
            if (this.marker) this.marker.setMap(null);
            this.marker = new google.maps.Marker({
                map: this.map,
                position: place.geometry.location,
                title: place.name
            });
            
            // Store coordinates in hidden fields
            document.getElementById('latitude').value = place.geometry.location.lat();
            document.getElementById('longitude').value = place.geometry.location.lng();
        });
        
        // Handle map clicks
        this.map.addListener('click', (event) => {
            if (this.marker) this.marker.setMap(null);
            this.marker = new google.maps.Marker({
                map: this.map,
                position: event.latLng
            });
            
            document.getElementById('latitude').value = event.latLng.lat();
            document.getElementById('longitude').value = event.latLng.lng();
        });
    }
}