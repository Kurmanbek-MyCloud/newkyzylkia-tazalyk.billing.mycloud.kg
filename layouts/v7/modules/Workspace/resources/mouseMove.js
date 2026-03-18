window.addEventListener("load", function() {
    // console.log('red');
    // var create = document.getElementById("canvas")
    // var c = create.getContext("2d");
    // console.log(c);

    var object = document.getElementById('card_info'),
        X = 0,
        Y = 0
    mouseX = 0,
        mouseY = 0;
    window.addEventListener("mousemove", function(ev) {
        ev = window.event || ev;
        X = ev.pageX;
        Y = ev.pageY;
    });

    function cardMove() {
        var p = "px";
        object.style.left = X + p;
        object.style.top = Y + p;
        setTimeout(cardMove, 100)
    }
    cardMove();

    // canvas.on('mousemove', ({ pageX, pageY }) => {
    //     const space = this._getSpaceByCoords(pageX, pageY);
    //     const organizationInfo = space.organizationInfo;
    //     // console.log(space.floorNumber);

    //     if (space) {
    //         this._showSpaceCard({
    //             title: organizationInfo.name,
    //             fields: [{ key: 'Площадь', value: space.area }],
    //             id: space.moduleId,
    //             coard: space.spaceCoords
    //         });
    //     } else {
    //         this._hideSpaceCard();
    //     }
    // });
    // canvas.mousemove(function(e) {
    //     e.preventDefault();

    //     // $(this).css("background", "green");

    // });
    const WIDTH = 900;
    const HEIGHT = 'auto';
    const SPACE_BORDER_WIDTH = 5;
    const SPACE_BORDER_BUSY = '#bb2124';
    const SPACE_BORDER_AVAILABLE = '#22bb33';
    const SPACE_FILL_BUSY = 'rgba(187, 33, 36, 0.5)';
    const SPACE_FILL_AVAILABLE = 'rgba(34, 187, 51, 0.5)';
    const DEFAULT_FLOOR_INDEX = 0;
    const ORGANIZATION_LOGO_WIDTH = 25;
    const ORGANIZATION_LOGO_BACKGROUND_COLOR = 'rgba(0, 0, 0, 0.3)';
    const ORGANIZATION_LOGO_BACKGROUND_RADIUS = 20;
    class Test {
        /**
         *
         * @param {Object[]} data Floors information
         * @param {Number} data.floorNumber
         * @param {String} data.floorImage
         * @param {Object[]} data.spacesInfo
         * @param {String} [data.spacesInfo.area]
         * @param {Array[]} data.spacesInfo.spaceCoords
         * @param {('busy'|'available')} data.spacesInfo.spaceType
         * @param {Object} [data.spacesInfo.organizationInfo]
         * @param {String} [data.spacesInfo.organizationInfo.name]
         * @param {String} [data.spacesInfo.organizationInfo.logo]
         * @param {Boolean} [data.spacesInfo.organizationInfo.autocenter]
         */
        constructor(wrapper, data, settings = {}) {
            this.wrapper = $(wrapper);
            this.data = data;
            this.dpiWrapperWidth = this.wrapper.width() * 2;
            this.dpiWrapperHeight = this.wrapper.height() * 2;
            this.currentFloorIndex = DEFAULT_FLOOR_INDEX;
            this.settings = settings;
            this.editMode = settings.editMode;
            this.spaceCapture = false;
            this.captureTopLeftCoord = null;
            this.captureBottomRightCoord = null;
            this.captureTopRightCoord = null;
            this.captureBottomLeftCoord = null;
        }
        name() {
            let create = this.xy;
            // create.fillStyle = "rgb(200,0,0)";
            // create.fillRect(10, 10, 55, 50);
            return this.data;

        }
    }
    // let test = 'test';
    t = new Test();
    // console.log(t.name());
});