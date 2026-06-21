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

(function () {
    function apiBase() {
        var script = document.currentScript && document.currentScript.getAttribute('src') || '';
        var marker = '/lib/location-picker.js';
        if (script.indexOf(marker) !== -1) {
            return script.slice(0, script.indexOf(marker)) + '/api/';
        }
        return '../api/';
    }

    function optionText(option) {
        return option ? String(option.textContent || '').trim() : '';
    }

    function clearLga(lgaSelect, placeholder) {
        if (!lgaSelect) return;
        var label = placeholder || lgaSelect.dataset.placeholder || 'Select LGA';
        lgaSelect.innerHTML = '';
        var option = document.createElement('option');
        option.value = '';
        option.textContent = label;
        lgaSelect.appendChild(option);
    }

    function normalizeItems(payload) {
        if (Array.isArray(payload)) return payload;
        if (payload && Array.isArray(payload.items)) return payload.items;
        return [];
    }

    async function loadLgas(stateSelect, lgaSelect) {
        if (!stateSelect || !lgaSelect) return;
        var selected = lgaSelect.dataset.selected || lgaSelect.getAttribute('value') || '';
        clearLga(lgaSelect);
        var stateValue = stateSelect.value || '';
        var stateName = optionText(stateSelect.selectedOptions && stateSelect.selectedOptions[0]);
        if (!stateValue && !stateName) return;
        lgaSelect.disabled = true;
        try {
            var endpoint = stateSelect.dataset.stateMode === 'name' || !/^\d+$/.test(String(stateValue))
                ? apiBase() + 'get-lgas-by-state.php?state=' + encodeURIComponent(stateValue || stateName)
                : apiBase() + 'get-lgas.php?state_id=' + encodeURIComponent(stateValue);
            var response = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
            var items = normalizeItems(await response.json());
            items.forEach(function (item) {
                var option = document.createElement('option');
                option.value = item.id && /^\d+$/.test(String(stateValue)) ? item.id : (item.lga_name || item.name || item);
                option.textContent = item.lga_name || item.name || String(item);
                if (String(option.value) === String(selected) || option.textContent === selected) {
                    option.selected = true;
                }
                lgaSelect.appendChild(option);
            });
        } catch (error) {
            // Keep the field usable even if the network/API is unavailable.
        } finally {
            lgaSelect.disabled = false;
        }
    }

    function findLgaForState(stateSelect) {
        var explicit = stateSelect.dataset.lgaTarget || stateSelect.getAttribute('aria-controls') || '';
        if (explicit) {
            return document.getElementById(explicit) || document.querySelector('[name="' + explicit + '"]');
        }
        var form = stateSelect.closest('form') || document;
        var name = stateSelect.name || '';
        var candidates = [];
        if (name.indexOf('state') !== -1) {
            candidates.push(name.replace('state_id', 'lga_id'), name.replace('state', 'lga'));
        }
        candidates.push('lga_id', 'lga', 'farm_lga_id', 'primary_lga_id');
        for (var i = 0; i < candidates.length; i++) {
            var found = form.querySelector('[name="' + candidates[i] + '"]') || document.getElementById(candidates[i]);
            if (found && found.tagName === 'SELECT') return found;
        }
        return null;
    }

    function attachStateLgaPickers(root) {
        (root || document).querySelectorAll('select[data-lga-target], select[name="state_id"], select[name="farm_state_id"], select[name="state"]').forEach(function (stateSelect) {
            if (stateSelect.dataset.lgaBound === '1') return;
            var lgaSelect = findLgaForState(stateSelect);
            if (!lgaSelect) return;
            stateSelect.dataset.lgaBound = '1';
            if (!stateSelect.dataset.stateMode && stateSelect.name === 'state') {
                stateSelect.dataset.stateMode = 'name';
            }
            stateSelect.addEventListener('change', function () {
                lgaSelect.dataset.selected = '';
                loadLgas(stateSelect, lgaSelect);
            });
            loadLgas(stateSelect, lgaSelect);
        });
    }

    window.NATCODEVLocation = window.NATCODEVLocation || {};
    window.NATCODEVLocation.attach = attachStateLgaPickers;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { attachStateLgaPickers(document); });
    } else {
        attachStateLgaPickers(document);
    }
})();
