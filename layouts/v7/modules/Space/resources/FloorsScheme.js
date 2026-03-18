const WIDTH = 900;
const HEIGHT = 'auto';
const SPACE_BORDER_WIDTH = 5;
// const SPACE_BORDER_BUSY = '#bb2124';
// const SPACE_BORDER_AVAILABLE = '#22bb33';
const SPACE_BORDER_BUSY = '#22bb33';
const SPACE_BORDER_AVAILABLE = '#bb2124';
const SPACE_BORDER_REMONT = '#a7a4a4db';
const SPACE_BOOKING_BOOKING = '#FFA500';
// const SPACE_FILL_BUSY = 'rgba(187, 33, 36, 0.5)';
// const SPACE_FILL_AVAILABLE = 'rgba(34, 187, 51, 0.5)';
const SPACE_FILL_BUSY = 'rgba(34, 187, 51, 0.5)';
const SPACE_FILL_AVAILABLE = 'rgba(187, 33, 36, 0.5)';
const SPACE_FILL_REMONT = '#a7a4a4db';
const SPACE_FILL_BOOKING = '#FFA500';


const DEFAULT_FLOOR_INDEX = 0;
const ORGANIZATION_LOGO_WIDTH = 25;
const ORGANIZATION_LOGO_BACKGROUND_COLOR = 'rgba(0, 0, 0, 0.3)';
const ORGANIZATION_LOGO_BACKGROUND_RADIUS = 20;
const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
var xStart = 0,
    yStart = 0,
    xEnd = 0,
    yEnd = 0;
var coards = [];
var canDrawSelection = false;
var rect_l;
var svg;

class FloorsScheme {
    /**
     *
     * @param {Object[]} data Floors information
     * @param {Number} data.floorNumber
     * @param {String} data.floorImage
     * @param {Object[]} data.spacesInfo
     * @param {String} [data.spacesInfo.area]
     * @param {Array[]} data.spacesInfo.spaceCoords
     * @param {('busy'|'available')} data.spacesInfo.officeType
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
        this.canDrawSelection = false;
        // this.coards = null;
        // console.log(this.data);
    }


    initialize() {
        this.wrapper.empty();
        this._createComponents();
        this._setStyles()
        this._initFloorsPlans();
        this.changeActiveFloor(DEFAULT_FLOOR_INDEX);
        this._bindEventListeners();
        // this.animate();

    }
    _initFloorsPlans() {
        for (let i = 0; i < this.data.length; i++) {
            const floorImage = this.data[i].floorImage;
            const dataFloor = this.data[i].floorNumber;
            this.wrapper.append(
                `<img class="floor-image" src="${floorImage}" data-index="${i}" hidden/>`
            );
            // d3.select("svg")
            // .append("image")
            // .attr('xlink:href',floorImage)
            // .attr("class","floor-image")
            // .attr("data-index",i)
            // // .attr("width",$(".floor-scheme").width())
            // .attr("height","400")            
        }
        let zoom = d3.zoom()
            .on('zoom', handleZoom);

        function handleZoom(e) {
            d3.select('svg g')
                .attr('transform', e.transform);
        }
    }
    _setStyles() {
        this.wrapper.css('position', 'relative');
        // this.wrapper.css('min-width', WIDTH);
        // this.wrapper.css('max-width', WIDTH);
        this.wrapper.css('margin', '0 auto');
        this.wrapper.css('height', HEIGHT);
        // this.canvas.css('width', WIDTH);
        this.canvas.css('zIndex', '1');
        $(".scheme-wrapper").width(window.innerWidth - 100);
    }
    _createComponents() {
        this.canvas = $('<svg class="floor-scheme" id="floor-scheme"></svg>');
        this.wrapper.append(this.canvas[0]);
        this.svg = document.querySelector('#floor-scheme');
        this.real_coards = this.svg.createSVGPoint();


        this.rect = $("rect");
        if (this.data != 'undefined') {
            this.spaceCard = $(`
                    <div class="space-card hidden" id="card_info">
                        <h2 class="space-card-title"></h2>
                        <ul class="space-card-info"></ul>
                    </div>
                `);
            this.wrapper.append(this.spaceCard);

            this.floorSelect = $(`
                    <div class="space-floor-select">
                        <div class="space-floor-select-controls">
                            <button class="prev-floor">-</button>
                            <button class="next-floor">+</button>
                        </div>
                        <select id="floor-select">
                            ${this.data.map(
                (floor, index) =>
                    `<option value="${index}">Этаж - ${floor.floorNumber}</option>`
            )}
                        </select>
                    </div>
                `);
        }
        this.wrapper.append(this.floorSelect);

        this.captureBorder = $(`
            <div class="capture-border"></div>
        `);
        this.wrapper.append(this.captureBorder);
    }
    _bindEventListeners() {
        this.wrapper.find('.prev-floor').on('click', () => this.prevFloor());

        this.wrapper.find('.next-floor').on('click', () => this.nextFloor());
        this.wrapper
            .find('.space-floor-select select')
            .on('change', e => this.changeActiveFloor($(e.currentTarget).val()));
        svg = d3.select("svg")
            .on("mousedown", mousedown)
            .on("mouseup", mouseup);


        function mousedown() {
            var m = d3.mouse(this);
            rect_l = svg.append("rect")
                .attr("x", m[0])
                .attr("y", m[1])
                .attr("height", 0)
                .attr("width", 0)
                .attr('fill', 'none')
                .attr('stroke', '#000');

            svg.on("mousemove", mousemove);
            svg.on("mouseup", up);

        }
        function mousemove(d) {
            var m = d3.mouse(this);
            let x_coards = rect_l.attr("x"),
                y_coards = rect_l.attr("y"),
                w = Math.max(0, m[0] - +rect_l.attr("x")),
                h = Math.max(0, m[1] - +rect_l.attr("y"));
            rect_l.attr("width", w)
                .attr("height", h);
            coards = {
                x_coards,
                y_coards,
                w,
                h
            }

        }
        function up() {
            let currentFloorIndex = $('.space-floor-select select').val();
            let currentFloorId = floors[currentFloorIndex]['floorid'];
            setTimeout(() => {
                window.location.replace(`index.php?module=Space&view=Edit&__vtrftk=sid%3A2d959c8e36173e27886db6f8d54c20aa779b9a63%2C1628681598&popupReferenceModule=Trad&floor_number=${currentFloorId}&floor_number_display=&area=&space_status=&x_coords=${coards.x_coards}&y_coords=${coards.y_coards}&width_coords=${coards.w}&height_coords=${coards.h}&responsible=1`);
            }, 200);
        }

        function mouseup() {
            svg.on("mousemove", null);

        }

    }

    changeActiveFloor(floorIndex) {
        this.currentFloorIndex = floorIndex;
        this._floorChangeHandler();
    }

    nextFloor() {
        const floorsCount = this.data.length;
        if (this.currentFloorIndex + 1 <= floorsCount - 1) {
            this.currentFloorIndex++;
            $('.space-floor-select select').val(this.currentFloorIndex);
            this._floorChangeHandler();
        }
    }

    prevFloor() {
        if (this.currentFloorIndex - 1 >= 0) {
            this.currentFloorIndex--;
            $('.space-floor-select select').val(this.currentFloorIndex);
            this._floorChangeHandler();
        }
    }

    _floorChangeHandler() {
        const floorImage = this.wrapper.find(
            `.floor-image[data-index="${this.currentFloorIndex}"]`
        );
        this.wrapper.find('.floor-image').attr('hidden', true);
        floorImage.attr('hidden', false);
        this._showFloorSpaces(this.currentFloorIndex);
        floorImage.on(
            'load',
            function () {
                this._showFloorSpaces(this.currentFloorIndex);
            }.bind(this)
        );
    }
    _showFloorSpaces(floorIndex) {
        this._add_remove_space(floorIndex);
    }
    _setFillColor(setFill) {
        var background,
            border;
        switch (setFill) {
            case 'busy':
                background = SPACE_FILL_BUSY;
                border = SPACE_BORDER_BUSY;
                break;
            case 'available':
                background = SPACE_FILL_AVAILABLE;
                border = SPACE_BORDER_AVAILABLE;
                break;
            case 'booking':
                background = SPACE_FILL_BOOKING;
                border = SPACE_BOOKING_BOOKING;
                break;
            case 'remont':
                background = SPACE_FILL_REMONT;
                border = SPACE_BORDER_REMONT;
                break;
            default:
                break;
        }
        return {
            background,
            border
        }
    }
    _sum(data) {
        const y = (parseInt(data.spaceCoords.height) + parseInt(data.spaceCoords.y));
        const x = (parseInt(data.spaceCoords.width) + parseInt(data.spaceCoords.x));
        const sum_y = (y + parseInt(data.spaceCoords.y)) / 2;
        const sum_x = (x + parseInt(data.spaceCoords.x)) / 2;
        return {
            sum_y,
            sum_x
        };
    }
    _text(data, id, name) {
        let result = [];
        for (const key of data) {
            let link = "";
            if (key.crmid == id) {
                link = key.path + key.attachmentsid + '_' + key.name;
                result.push(link)
            }
        }
        const text = name.split(",");
        return text;
        // if(result.length==0){
        //     return text;
        // }else{
        //     return "";
        // } 
    }
    image(data, id, n) {
        let result = [];
        for (const key of data) {
            let link = "";
            if (key.crmid === id) {
                link = key.path + key.attachmentsid + '_' + key.name;
                result.push(link)
            }
        }
        return result;
    }
    _add_remove_space(floor_data) {
        var myData = this.data[floor_data].spacesInfo;

        d3.select(".floor-scheme").selectAll("g").remove();
        if (myData) {
            var path = d3.select("svg").selectAll('g')
                .data(this.data[floor_data].spacesInfo)
                .enter().append("g")
                .attr("id", index => index.officeId);
            path
                .append("rect")
                .attr("id", index => index.officeId)
                .attr("x", index => index.spaceCoords.x)
                .attr("y", index => index.spaceCoords.y)
                .attr("width", index => index.spaceCoords.width)
                .attr("height", index => index.spaceCoords.height)
                .attr("fill", index => this._setFillColor(index.officeType).background)
                .attr("stroke", index => this._setFillColor(index.officeType).border)


            path
                .append('text')
                .attr('y', index => this._sum(index).sum_y - 20)
                .attr('x', index => parseInt(index.spaceCoords.x) + 40)
                .attr('class', 'text')
                .selectAll('tspan').data(index => this._text(index.logo, index.officeId, index.organizationInfo['name']))
                .enter().append('tspan')
                .text(function (d) {
                    return d;
                })
                .attr('dy', '1.3em')
                .attr('dx', '-35px')
            path
                .append('image')
                .attr('xlink:href', index => this.image(index.logo, index.officeId)[0])
                .attr('width', '60')
                .attr('height', '20')
                .attr('y', index => this._sum(index).sum_y - 10)
                .attr('x', index => this._sum(index).sum_x - 15)
            path
                .on("mousemove", function (ev) {
                    ev = window.event || ev;
                    var m = d3.mouse(this);
                    var p = "px";
                    $("#card_info").css({
                        "left": `${m[0]}${p}`,
                        "top": `${m[1]}${p}`
                    })
                    // let test=1>=2;
                    $(this).css("opacity", 0.7);
                    let rect_id = $(this).attr("id");
                    $(".space-card").removeClass('hidden', false);
                    const spaceCardInfo = $(".space-card").find('.space-card-info');
                    spaceCardInfo.empty();
                    myData.forEach(async (element) => {
                        let info = element['organizationInfo'];
                        if (rect_id === element['officeId']) {
                            let replace = info['contract_date'];
                            let provider = info['provider'].split("|##|").join(", ");
                            $(".space-card").find('.space-card-title').text(`Объект: ${info['name']}`);
                            spaceCardInfo.append(
                                `<li><strong>Площадь:</strong><span>${element['area']}</span></li>
                                <li><strong>Статус:</strong><span>${info['status']}</span></li>
                                <li><strong>Арендатор:</strong><span><a href="">${info['renter']}</a></span></li>
                                <li><strong>Дата договора:</strong><span>${replace}</span></li>
                                <li><strong>Номер договора:</strong><span>${info['contract_number']}</span></li>
                                <li><strong>Сумма договора:</strong><span>${info['price']} сом</span></li>
                                <li><strong>Особенности:</strong><span>${info['features']}</span></li>
                                <li><strong>Провайдеры:</strong><span>${provider}</span></li>
                            `);
                        }
                    });
                })
                .on("mouseleave", function () {
                    $(".space-card").addClass('hidden', true);
                    $(this).css("opacity", 1);
                })
                .on("click", function () {
                    let detail = $(this).attr("id");
                    setTimeout(() => {
                        window.location.replace(`index.php?module=Estates&view=Detail&record=${detail}&app=MARKETING`)
                    }, 500);
                })
            const imgs = document.querySelectorAll("svg image");
            for (const key of imgs) {
                const href = $(key).context.href.baseVal;
                if (href == "") {
                    $(key).remove()
                }
            }
            const txts = document.querySelectorAll("svg text");
            const g_paths = document.querySelectorAll("svg g");
            for (const item of g_paths) {
                const id = $(item).context.id;
                // $(`#${id}`).mousemove(function(ev){
                //     ev =window.event|| ev;
                //     // var m = d3.mouse(this);
                //     var p = "px";
                //     $("#card_info").css({
                //         "left":`${ev.offsetX}${p}`,
                //         "top":`${ev.offsetY}${p}`
                //     })
                //     $(this).css("opacity",0.7);
                //     let rect_id = $(this).attr("id");
                //     $(".space-card").removeClass('hidden', false);
                //     const spaceCardInfo = $(".space-card").find('.space-card-info');
                //     spaceCardInfo.empty();
                //     myData.forEach(async(element) => {
                //         let info=element['organizationInfo'];
                //         if (rect_id === element['officeId']) {
                //             let replace=info['contract_date'];
                //             let provider=info['provider'].split("|##|").join(", ");
                //             $(".space-card").find('.space-card-title').text(info['name']);
                //             spaceCardInfo.append(
                //                 `<li><strong>Площадь:</strong><span>${element['area']}</span></li>
                //                 <li><strong>Статус:</strong><span>${info['status']}</span></li>
                //                 <li><strong>Арендатр:</strong><span><a href="">${info['renter']}</a></span></li>
                //                 <li><strong>Дата договора:</strong><span>${replace}</span></li>
                //                 <li><strong>Номер договора:</strong><span>${info['contract_number']}</span></li>
                //                 <li><strong>Сумма договора:</strong><span>${info['price']} сом</span></li>
                //                 <li><strong>Особенности:</strong><span>${info['features']}</span></li>
                //                 <li><strong>Провайдеры:</strong><span>${provider}</span></li>
                //             `);
                //         }
                //     });
                // })
                // .mouseleave(function(ev){
                //     $(this).css("opacity",1);
                //     $(".space-card").addClass('hidden', true);
                // })
                // .click(function(ev){
                //     let detail = $(this).attr("id");
                //     setTimeout(() => {
                //         window.location.replace(`index.php?module=Offices&view=Detail&record=${detail}&app=MARKETING`)
                //     }, 500);
                // })
            }
        }
    }
    _show_card_info(floorIndex, rect_id) {
        let data = this.data[floorIndex].spacesInfo;
        const spaceCardInfo = this.spaceCard.find('.space-card-info');
        spaceCardInfo.empty();
        data.forEach(element => {
            if (rect_id === element['moduleId']) {
                this.spaceCard.find('.space-card-title').text(element['organizationInfo'].name);
                spaceCardInfo.append(
                    `<li><strong>Площадь:</strong><span>${element["area"]}</span></li>
                    <li><strong>Площадь:</strong><span>${element["spaceType"]}</span></li>
                    `
                );
            }

        });

    }


}