$(document).ready(function() {

async function fetchData(endpoint) {
    const response = await fetch(`https://data.chnm.org/pop-places/${endpoint}`);
    return await response.json();
}

function appendCounties(countySelect, countiesData) {
    countySelect.empty().append($('<option>').val('').text(countySelect.data('empty-option')));
    for (let countyData of countiesData) {
        countySelect.append($('<option>').val(countyData.county_ahcb).text(countyData.name));
    }
}

function appendPlaces(placeSelect, placesData) {
    placeSelect.empty().append($('<option>').val('').text(placeSelect.data('empty-option')));
    for (let placeData of placesData) {
        placeSelect.append($('<option>').val(placeData.place_id).text(`${placeData.place} (${placeData.map_name})`));
    }
}

$('.mare-populated-place-select').each(async function(i) {
    let thisContainer = $(this);

    let placeHidden = thisContainer.children('.place-id');
    let stateSelect = thisContainer.children('.state');
    let countySelect = thisContainer.children('.county');
    let placeSelect = thisContainer.children('.place');

    stateSelect.on('change', async function(e) {
        countySelect.hide();
        placeSelect.hide();
        placeHidden.val('');
        let state = $(this).val().toLowerCase();
        if ('' !== state) {
            let countiesData = await fetchData(`state/${state}/county/`);
            appendCounties(countySelect, countiesData);
            countySelect.show();
        }
    });

    countySelect.on('change', async function(e) {
        placeSelect.hide();
        placeHidden.val('');
        let county = $(this).val();
        if ('' !== county) {
            let placesData = await fetchData(`county/${county}/place/`);
            appendPlaces(placeSelect, placesData);
            placeSelect.show();
        }
    });

    placeSelect.on('change', async function(e) {
        let placeId = $(this).val();
        placeHidden.val(placeId);
    });

    let placeId = placeHidden.val();
    if ('' === placeId) {
        countySelect.hide();
        placeSelect.hide();
    } else {
        let placeData = await fetchData(`place/${placeId}/`);

        stateSelect.val(placeData.state);

        let countiesData = await fetchData(`state/${placeData.state.toLowerCase()}/county/`);
        appendCounties(countySelect, countiesData);
        countySelect.val(placeData.county_ahcb);

        let placesData = await fetchData(`county/${placeData.county_ahcb}/place/`);
        appendPlaces(placeSelect, placesData);
        placeSelect.val(placeId);
    }
});

});
