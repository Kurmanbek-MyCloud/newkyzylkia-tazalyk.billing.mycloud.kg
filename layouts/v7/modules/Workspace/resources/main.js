const convertedData = FloorsSchemeAdapter.convertData(floors);

const floorScheme = new FloorsScheme('#scheme', convertedData, {
    editMode: true,
    onCaptureMouseUp: (floor, coords) => {
        let quickCreateNode = jQuery('#quickCreateModules').find('[data-name="Workspace"]');
        let coordsAsString = JSON.stringify(coords);
        coordsAsString = coordsAsString.substring(1, coordsAsString.length - 1);
        let currentFloorIndex = $('.space-floor-select select').val();
        let currentFloorId = floors[currentFloorIndex]['floorschemeid'];

        setTimeout(() => {
            // $('input#Workspace_editView_fieldName_space_coords').val(coordsAsString);
            window.location.replace(`index.php?module=Workspace&view=Edit&__vtrftk=sid%3A2d959c8e36173e27886db6f8d54c20aa779b9a63%2C1628681598&popupReferenceModule=FloorScheme&floor_number=${currentFloorId}&floor_number_display=&area=&space_status=&space_coords=${coordsAsString}&responsible=1`);
        }, 1000);

    },
});
floorScheme.initialize();