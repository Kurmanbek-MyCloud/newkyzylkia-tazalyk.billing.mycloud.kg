class FloorsSchemeAdapter {
    static convertData(data) {
        return data.map(function (floor) {
            let spaces = "";
            if (floor['spaces']) {
                // console.log('test', floor['spaces']);
                spaces = floor['spaces'].map(function (space) {
                    if (space.office_info) {
                        let officeStatus;
                        switch (space['new_status']) {
                            case 'В аренде':
                                officeStatus = "busy";
                                break;
                            case 'Свободен':
                                officeStatus = "available";
                                break;
                            case 'Бронь':
                                officeStatus = "booking";
                                break;
                            case 'В ремонте':
                                officeStatus = "remont";
                                break;
                            default:
                                break;
                        }
                        return {
                            spaceCoords: {
                                x: space['x_coords'],
                                y: space['y_coords'],
                                width: space['width_coords'],
                                height: space['height_coords'],
                            },
                            logo: space['logo'],
                            officeId: space.office_info.id,
                            spaceType: space['space_status'] === 'Активно' ? 'available' : 'busy',
                            officeType: officeStatus,
                            area: space['office_info']['area'],
                            organizationInfo: {
                                name: space['office_info']['name'],
                                status: space['office_info']['status'],
                                contract_number: space['office_info']['contract_number'],
                                contract_date: space['office_info']['start_date'],
                                provider: space['office_info']['provider'],
                                renter: space['office_info']['renter'],
                                features: space['office_info']['features'],
                                price: space['office_info']['price'],
                                logo: space['organization_logo'],
                                autocenter: new Boolean(space['autocenter_logo']),
                            },
                            moduleId: space['spaceid']
                        };
                    }

                });
            }

            return {
                floorNumber: floor['floor_number'],
                floorImage: floor['floor_plan'],
                spacesInfo: spaces,
            };

        });
    }
}