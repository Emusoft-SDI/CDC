(function () {
      const tabs = Array.from(document.querySelectorAll('[data-profile-tab]'));
      const panels = Array.from(document.querySelectorAll('[data-profile-panel]'));

      function activateProfileTab(key) {
        const targetKey = panels.some((panel) => panel.dataset.profilePanel === key) ? key : 'personal';
        tabs.forEach((tab) => { if(tab.hasAttribute('data-profile-tab')) tab.setAttribute('aria-selected', tab.dataset.profileTab === targetKey ? 'true' : 'false'); });
        panels.forEach((panel) => {
        if (!panel) return;
        if (!panel) return;
          panel.hidden = panel.dataset.profilePanel !== targetKey;
        });
        document.querySelectorAll('[data-profile-save-actions]').forEach((actions) => {
          actions.hidden = ['password', 'locations'].includes(targetKey);
        });
        try { localStorage.setItem('natcodev_profile_tab', targetKey); } catch (error) {}
      }

      tabs.forEach((tab) => {
        tab.addEventListener('click', (e) => {
          if(tab.hasAttribute('data-profile-tab')) { e.preventDefault(); activateProfileTab(tab.dataset.profileTab); }
        });
      });
      let initialTab = 'personal';
      try { initialTab = localStorage.getItem('natcodev_profile_tab') || initialTab; } catch (error) {}
      if (window.location.hash) {
        initialTab = window.location.hash.replace('#', '');
      }
      activateProfileTab(initialTab);

      async function loadLgas(stateSelect) {
        const target = document.getElementById(stateSelect.dataset.lgaTarget || '');
        if (!target) return;
        const selected = target.dataset.selected || '';
        target.innerHTML = '<option value="">Select LGA</option>';
        if (!stateSelect.value) return;
        try {
          const response = await fetch('../api/get-lgas.php?state_id=' + encodeURIComponent(stateSelect.value));
          const data = await response.json();
          (data.items || []).forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.lga_name;
            if (String(item.id) === String(selected)) option.selected = true;
            target.appendChild(option);
          });
        } catch (error) {}
      }

      document.querySelectorAll('select[data-lga-target]').forEach((select) => {
        select.addEventListener('change', () => {
          const target = document.getElementById(select.dataset.lgaTarget || '');
          if (target) target.dataset.selected = '';
          loadLgas(select);
        });
        loadLgas(select);
      });

      document.querySelectorAll('[data-location-fill]').forEach((button) => {
        button.addEventListener('click', () => {
          if (!navigator.geolocation) {
            alert('Location is not available on this device.');
            return;
          }
          button.disabled = true;
          navigator.geolocation.getCurrentPosition((position) => {
            const key = button.dataset.locationFill;
            const lat = document.getElementById(key + '_latitude');
            const lng = document.getElementById(key + '_longitude');
            if (lat) lat.value = position.coords.latitude.toFixed(7);
            if (lng) lng.value = position.coords.longitude.toFixed(7);
            button.disabled = false;
          }, () => {
            alert('Unable to get location. You can type latitude and longitude manually.');
            button.disabled = false;
          }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
        });
      });

      document.querySelectorAll('[data-map-search]').forEach((button) => {
        button.addEventListener('click', () => {
          const form = button.closest('form') || document;
          const address = form.querySelector('[name="farm_street_address"], [name="street_address"]')?.value || '';
          const state = form.querySelector('[name="farm_state_id"], [name="state_id"]')?.selectedOptions?.[0]?.textContent || '';
          const lga = form.querySelector('[name="farm_lga_id"], [name="lga_id"]')?.selectedOptions?.[0]?.textContent || '';
          const query = [address, lga, state, 'Nigeria'].filter((part) => part && !part.startsWith('Select')).join(', ');
          if (!query) {
            alert('Enter the farm address, state, or LGA first.');
            return;
          }
          window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query), '_blank', 'noopener');
        });
      });

      function coordinatesFromMapsUrl(url) {
        const text = String(url || '');
        const atMatch = text.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
        if (atMatch) return [atMatch[1], atMatch[2]];
        const destinationMatch = text.match(/[?&](?:q|query|destination|center)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
        if (destinationMatch) return [destinationMatch[1], destinationMatch[2]];
        const dataMatch = text.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
        if (dataMatch) return [dataMatch[1], dataMatch[2]];
        return null;
      }

      document.querySelectorAll('[data-map-extract]').forEach((button) => {
        button.addEventListener('click', () => {
          const key = button.dataset.mapExtract;
          const url = document.getElementById(key + '_maps_url')?.value || '';
          const coordinates = coordinatesFromMapsUrl(url);
          if (!coordinates) {
            alert('Could not find coordinates in that Google Maps link.');
            return;
          }
          const lat = document.getElementById(key + '_latitude');
          const lng = document.getElementById(key + '_longitude');
          if (lat) lat.value = Number(coordinates[0]).toFixed(7);
          if (lng) lng.value = Number(coordinates[1]).toFixed(7);
        });
      });
    })();
