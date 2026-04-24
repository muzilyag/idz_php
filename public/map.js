document.addEventListener("DOMContentLoaded", function() {
    var map = L.map('map').setView([52.59314, 39.58710], 10);

    map.attributionControl.setPrefix(false);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19//,
 //       attribution: '© OpenStreetMap'
    }).addTo(map);

    var marker;

    map.on('click', function(e) {
        var lat = e.latlng.lat.toFixed(5);
        var lng = e.latlng.lng.toFixed(5);
        var coordsString = lat + ", " + lng;

        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng).addTo(map);
        }

        document.getElementById('place_input').value = coordsString;
    });
});
