$(document).ready(function() {

// Fetch data from the pop-places endpoint.
async function fetchData(endpoint) {
    const response = await fetch(`https://data.chnm.org/pop-places/${endpoint}`);
    return await response.json();
}

// Set value options for a county select.
async function setCountyOptions(state, countySelect) {
    countySelect.empty();
    let countiesData = await fetchData(`state/${state}/county/`);
    countySelect.append($('<option>').val('').text(countySelect.data('empty-option')));
    for (let countyData of countiesData) {
        countySelect.append($('<option>').val(countyData.county_ahcb).text(countyData.name));
    }
}

// Set value options for a place select.
async function setPlaceOptions(county, placeSelect) {
    placeSelect.empty();
    let placesData = await fetchData(`county/${county}/place/`);
    placeSelect.append($('<option>').val('').text(placeSelect.data('empty-option')));
    for (let placeData of placesData) {
        placeSelect.append($('<option>').val(placeData.place_id).text(`${placeData.place} (${placeData.map_name})`));
    }
}

// Apply a fetch error to a field.
function applyFetchError(container) {
    container.children().hide();
    container.append(`<p>${container.data('fetch-error')}</p>`);
}

// Iterate all populated place fields and initialize them.
$('.mare-populated-place-select').each(async function(i) {
    let thisContainer = $(this);

    let placeHidden = thisContainer.children('.place-id');
    let stateSelect = thisContainer.children('.state');
    let countySelect = thisContainer.children('.county');
    let placeSelect = thisContainer.children('.place');

    // Initialize the field.
    let placeId = placeHidden.val();
    if ('' === placeId) {
        // A place ID is not set for this field. Hide all but state select.
        countySelect.hide();
        placeSelect.hide();
    } else {
        // A place ID is set for this field. Populate and set all selects.
        try {
            let placeData = await fetchData(`place/${placeId}/`);
            stateSelect.val(placeData.state);
            await setCountyOptions(placeData.state.toLowerCase(), countySelect);
            countySelect.val(placeData.county_ahcb);
            await setPlaceOptions(placeData.county_ahcb, placeSelect);
            placeSelect.val(placeId);
        } catch(e) {
            applyFetchError(thisContainer);
        }
    }

    // Display the county select when user selects a state.
    stateSelect.on('change', async function(e) {
        countySelect.hide();
        placeSelect.hide();
        placeHidden.val('');
        let state = $(this).val().toLowerCase();
        if ('' !== state) {
            try {
                await setCountyOptions(state, countySelect);
                countySelect.show();
            } catch(e) {
                applyFetchError(thisContainer);
            }
        }
    });

    // Display the place select when user selects a county.
    countySelect.on('change', async function(e) {
        placeSelect.hide();
        placeHidden.val('');
        let county = $(this).val();
        if ('' !== county) {
            try {
                await setPlaceOptions(county, placeSelect);
                placeSelect.show();
            } catch(e) {
                applyFetchError(thisContainer);
            }
        }
    });

    // Set the place ID when user selects a place.
    placeSelect.on('change', function(e) {
        let placeId = $(this).val();
        placeHidden.val(placeId);
    });
});

});
