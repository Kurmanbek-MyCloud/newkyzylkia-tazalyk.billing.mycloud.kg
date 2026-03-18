class FloorsSchemeAdapter {
    static convertData(data) {
        // console.log(data);
        return data.map(function (floor) {
            let spaces = floor['spaces'].map(function (space) {
                // console.log(space);
                return {
                    spaceCoords: JSON.parse('[' + space['space_coords'] + ']'),
                    spaceType: space['space_status'] === 'Активно' ? 'available' : 'busy',
                    area: space['area'],
                    organizationInfo: {
                        name: space['organization_name'],
                        logo: space['organization_logo'],
                        autocenter: new Boolean(space['autocenter_logo']),
                    },
                    moduleId: space['workspaceid']
                };
            });

            return {
                floorNumber: floor['floor_number'],
                floorImage: floor['floor_plan'],
                spacesInfo: spaces,
            };
        });
    }
}