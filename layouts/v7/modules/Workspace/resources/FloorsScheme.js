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

class FloorsScheme {
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
        console.log(data);
    }

    initialize() {
        this.wrapper.empty();
        this._createComponents();
        this._setStyles();
        this._initFloorsPlans();
        this.changeActiveFloor(DEFAULT_FLOOR_INDEX);
        this._bindEventListeners();
    }

    _initFloorsPlans() {
        for (let i = 0; i < this.data.length; i++) {
            const floorImage = this.data[i].floorImage;
            this.wrapper.append(
                `<img class="floor-image" src="${floorImage}" data-index="${i}" hidden/>`
            );
        }
    }

    _setStyles() {
        this.wrapper.css('position', 'relative');
        this.wrapper.css('min-width', WIDTH);
        this.wrapper.css('max-width', WIDTH);
        this.wrapper.css('margin', '0 auto');
        this.wrapper.css('height', HEIGHT);
        this.canvas.css('zIndex', '1');
    }

    _createComponents() {
            this.canvas = $('<canvas class="floor-scheme"></canvas>');
            this.wrapper.append(this.canvas[0]);
            this.ctx = this.canvas[0].getContext('2d');
            // console.log(this._getSpaceByCoords(pageX, pageY));
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
        <select>
        ${this.data.map(
          (floor, index) =>
            `<option value="${index}">Этаж - ${floor.floorNumber}</option>`
        )}
        </select>
      </div>
    `);
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
      // console.log(this._getSpaceByCoords(pageX, pageY));
    this.canvas.on('mousemove', ({ pageX, pageY }) => {
      const space = this._getSpaceByCoords(pageX, pageY);
      const organizationInfo = space.organizationInfo;
      // console.log(space);
      // console.log(space);
      // $(".space-card").attr("data-space",`${space.moduleId}`);

      if (space) {
        this._showSpaceCard({
          title: organizationInfo.name,
          fields: [{ key: 'Площадь', value: space.area }],
          id: space.moduleId,
          coard:space.spaceCoords
        });
      } else {
        this._hideSpaceCard();
      }
    });

    this.canvas.on('mousedown', ({ pageX, pageY }) => {
      if (!this.editMode) return;

      this.spaceCapture = true;
      this.captureTopLeftCoord = this._pageCoordToCanvasRelative([
        pageX,
        pageY,
      ]);
      [this.captureBorderLeft, this.captureBorderTop] =
        this._pageCoordToCanvasRelative([pageX, pageY], false);
    });
    this.canvas.on('mousemove', ({ pageX, pageY }) => {
      // console.log(this._getSpaceByCoords(pageX, pageY));
      if (!this.editMode || !this.spaceCapture) return;
      console.log(pageX,pageY);

      this.captureBottomRightCoord = this._pageCoordToCanvasRelative([
        pageX,
        pageY,
      ]);

      this.captureTopRightCoord = [
        this.captureBottomRightCoord[0],
        this.captureTopLeftCoord[1],
      ];
      this.captureBottomLeftCoord = [
        this.captureTopLeftCoord[0],
        this.captureBottomRightCoord[1],
      ];

      this.captureBorderWidth =
        pageX - this.captureBorderLeft - this.canvas.offset().left;
      this.captureBorderHeight =
        pageY - this.captureBorderTop - this.canvas.offset().top;

      let transformProperty = '';
      if (this.captureBorderWidth <= 0) {
        transformProperty += ' scaleX(-1)';
        this.captureBorderWidth *= -1;
      } else {
        transformProperty += ' scaleX(1)';
      }

      if (this.captureBorderHeight <= 0) {
        transformProperty += ' scaleY(-1)';
        this.captureBorderHeight *= -1;
      } else {
        transformProperty += ' scaleY(1)';
      }

      this.captureBorder.css('transform', transformProperty);
      this.captureBorder.css('left', this.captureBorderLeft + 'px');
      this.captureBorder.css('top', this.captureBorderTop + 'px');
      this.captureBorder.css('width', this.captureBorderWidth + 'px');
      this.captureBorder.css('height', this.captureBorderHeight + 'px');
    });
    this.canvas.on('mouseup', () => {
      if (!this.editMode || !this.spaceCapture) return;

      this.settings.onCaptureMouseUp(this.currentFloorIndex, [
        this.captureTopLeftCoord,
        this.captureTopRightCoord,
        this.captureBottomRightCoord,
        this.captureBottomLeftCoord,
        this.captureTopLeftCoord,
      ]);
      this.spaceCapture = false;
      this.captureTopLeftCoord = null;
    });
    this.canvas.on('mouseout', () => {
      if (!this.editMode || !this.spaceCapture) return;

      this.spaceCapture = false;
      this.captureTopLeftCoord = null;
      this.changeActiveFloor(this.currentFloorIndex);
    });
  }

  updateData(newData) {
    this.data = newData;
    this.initialize();
  }

  changeActiveFloor(floorIndex) {
    this.currentFloorIndex = floorIndex;
    this._floorChangeHandler();
  }

  nextFloor() {
    const floorsCount = this.data.length;

    if (this.currentFloorIndex + 1 <= floorsCount - 1) {
      this.currentFloorIndex++;
      this._floorChangeHandler();
    }
  }

  prevFloor() {
    if (this.currentFloorIndex - 1 >= 0) {
      this.currentFloorIndex--;
      this._floorChangeHandler();
    }
  }

  _floorChangeHandler() {
    const floorImage = this.wrapper.find(
      `.floor-image[data-index="${this.currentFloorIndex}"]`
    );
    this.wrapper.find('.floor-image').attr('hidden', true);
    floorImage.attr('hidden', false);

    this._updateCanvasSizes();
    this._showFloorSpaces(this.currentFloorIndex);
    floorImage.on(
      'load',
      function () {
        this._updateCanvasSizes();
        this._showFloorSpaces(this.currentFloorIndex);
      }.bind(this)
    );

    this.wrapper.find('.space-floor-select select').val(this.currentFloorIndex);
  }

  _updateCanvasSizes() {
    this.dpiWrapperWidth = this.wrapper.width() * 2;
    this.dpiWrapperHeight = this.wrapper.height() * 2;
    this.canvas.attr('width', this.dpiWrapperWidth + 'px');
    this.canvas.attr('height', this.dpiWrapperHeight + 'px');
  }

  _getSpaceByCoords(x, y) {
    const spacesInfo = this.data[this.currentFloorIndex].spacesInfo;

    for (const space of spacesInfo) {
      const pointInSpace = PointInPolygon.pointInPolygon(
        [x, y],
        this._canvasCoordsToPageRelative(space.spaceCoords)
      );

      if (pointInSpace) return space;
    }

    return false;
  }
  
  _addSpace(coords, type, organizationInfo) {
    this.ctx.beginPath();

    this.ctx.setLineDash([7, 7]);
    this.ctx.lineWidth = SPACE_BORDER_WIDTH;
    // console.log(type);
    switch (type) {
      case 'busy':
        this.ctx.strokeStyle = SPACE_BORDER_BUSY;
        this.ctx.fillStyle = SPACE_FILL_BUSY;
        break;
      case 'available':
        this.ctx.strokeStyle = SPACE_FILL_BUSY;
        this.ctx.fillStyle = SPACE_FILL_AVAILABLE;
        break;
      default:
        this.ctx.strokeStyle = SPACE_BORDER_BUSY;
        this.ctx.fillStyle = SPACE_FILL_BUSY;
        break;
    }

    try {
      // console.log(coords);

      for (const [x, y] of coords) {
        this.ctx.lineTo(x * 2, this.dpiWrapperHeight - y * 2);
        // console.log(x,y);
      }
      this.ctx.stroke();
      this.ctx.fill();

      if (type === 'busy') {
        if (organizationInfo.autocenter) {
          this._drawLogo(organizationInfo.logo, coords, true);
        } else {
          this._drawLogo(organizationInfo.logo, organizationInfo.logoCoords);
        }
      }
    } catch (e) {}
  }

  _drawLogo(logo, coords, autocenter = false) {
    const logoImage = new Image();
    logoImage.src = logo;
    const [x, y] = coords;

    logoImage.onload = () => {
      const scaleFactor = logoImage.width / ORGANIZATION_LOGO_WIDTH;
      let realX;
      let realY;

      if (autocenter) {
        let spaceMinX;
        let spaceMaxX;
        let spaceMinY;
        let spaceMaxY;

        coords.forEach(([x, y]) => {
          if (!spaceMinX) spaceMinX = x;
          if (!spaceMaxX) spaceMaxX = x;
          if (!spaceMinY) spaceMinY = y;
          if (!spaceMaxY) spaceMaxY = y;

          if (x < spaceMinX) spaceMinX = x;
          if (x > spaceMaxX) spaceMaxX = x;
          if (y < spaceMinY) spaceMinY = y;
          if (y > spaceMaxY) spaceMaxY = y;
        });

        realX =
          (spaceMinX + (spaceMaxX - spaceMinX) / 2) * 2 -
          ORGANIZATION_LOGO_WIDTH;
        realY =
          this.dpiWrapperHeight -
          (spaceMinY + (spaceMaxY - spaceMinY) / 2) * 2 -
          logoImage.height / scaleFactor;
      } else {
        realX = x * 2 - ORGANIZATION_LOGO_WIDTH;
        realY = this.dpiWrapperHeight - y * 2 - logoImage.height / scaleFactor;
      }

      const backgroundCenterX = realX + ORGANIZATION_LOGO_WIDTH;
      const backgroundCenterY = realY + logoImage.height / scaleFactor;

      this.ctx.beginPath();
      this.ctx.arc(
        backgroundCenterX,
        backgroundCenterY,
        ORGANIZATION_LOGO_BACKGROUND_RADIUS * 2,
        0,
        2 * Math.PI,
        false
      );
      this.ctx.fillStyle = ORGANIZATION_LOGO_BACKGROUND_COLOR;
      this.ctx.fill();

      this.ctx.drawImage(
        logoImage,
        0,
        0,
        logoImage.width,
        logoImage.height,
        realX,
        realY,
        ORGANIZATION_LOGO_WIDTH * 2,
        (logoImage.height / scaleFactor) * 2
      );
    };
  }

  _showFloorSpaces(floorIndex) {
    this.ctx.clearRect(0, 0, this.canvas.width(), this.canvas.height());

    for (const spaceInfo of this.data[floorIndex].spacesInfo) {
      this._addSpace(
        spaceInfo.spaceCoords,
        spaceInfo.spaceType,
        spaceInfo.organizationInfo
      );
    }
  }

  _canvasCoordsToPageRelative(coords, invertY = true) {
    const result = coords.map(([x, y]) => {
      return [
        this.canvas.offset().left + x,
        invertY
          ? this.canvas.offset().top + (this.wrapper.height() - y)
          : this.canvas.offset().top + y,
      ];
    });

    return result;
  }

  _canvasCoordToPageRelative(coord, invertY = true) {
    const [x, y] = coord;

    return [
      this.canvas.offset().left + x,
      invertY
        ? this.canvas.offset().top + (this.wrapper.height() - y)
        : this.canvas.offset().top + y,
    ];
  }
  _pageCoordsToCanvasRelative(coords, invertY = true) {
    const result = coords.map(([x, y]) => {
      return [
        x - this.canvas.offset().left,
        invertY
          ? this.wrapper.height() - (y - this.canvas.offset().top)
          : y - this.canvas.offset().top,
      ];
    });

    return result;
  }

  _pageCoordToCanvasRelative(coord, invertY = true) {
    const [x, y] = coord;

    return [
      x - this.canvas.offset().left,
      invertY
        ? this.wrapper.height() - (y - this.canvas.offset().top)
        : y - this.canvas.offset().top,
    ];
  }

  _showSpaceCard(data) {
    this.spaceCard.toggleClass('hidden', false);

    this.spaceCard.find('.space-card-title').text(data.title);
    // console.log(data);
    const spaceCardInfo = this.spaceCard.find('.space-card-info');
    
    spaceCardInfo.empty();
    for (const dataField of data.fields) {
      spaceCardInfo.append(
        `<li><strong>${dataField.key}:</strong><span>${dataField.value}</span></li>`
      );
    }
  }

  _hideSpaceCard() {
    this.spaceCard.toggleClass('hidden', true);
  }
}