class GTFE_Ship {
  INFOHTML = {
    engine:
      '<p>' +
      _('Engine strength:') +
      " <span id='curr_sel'>${curr}</span></p>" +
      '<p>' +
      _('Max engine strength:') +
      " <span id='max_str'>${max}</span></p>",
    cannon:
      '<p>' +
      _('Cannon strength:') +
      " <span id='curr_sel'>${curr}</span></p>" +
      '<p>' +
      _('Max cannon strength:') +
      " <span id='max_str'>${max}</span></p>",
    shield:
      '<p>' +
      _('Shield powered:') +
      " <span id='curr_sel'>${curr}</span></p>" +
      '<p>' +
      _('Shield required:') +
      " <span id='max_str'>${max}</span></p>",
    crew:
      '<p>' +
      _('Crew selected:') +
      "<span id='curr_sel'>${curr}</span></p>" +
      '<p>' +
      _('Crew required:') +
      "<span id='needed_sel'>${max}</span></p>",
    cell:
      '<p>' +
      _('Cells selected:') +
      "<span id='curr_sel'>${curr}</span></p>" +
      '<p>' +
      _('Cells required:') +
      "<span id='needed_sel'>${max}</span></p>",
    goods:
      '<p>' +
      _('${subtype} goods selected:') +
      "<span id='curr_sel'>${curr}</span></p>" +
      '<p>' +
      _('${subtype} goods required:') +
      "<span id='needed_sel'>${max}</span></p>",
  };

  constructor(game) {
    this.game = game;
    this.connects = {};

    // var to keep track of selections
    this._maxAllowed = undefined;
    this._typeToSelect = undefined;
    this._nbSelected = undefined;
    this._maxRequired = undefined;
    this._hasAlien = undefined;
  }

  connect(node, func) {
    // assume all onclicks, for simplicity
    let handle = dojo.connect(node, 'onclick', func);
    this.connects[node.id] = handle;
    return handle;
  }

  disconnectAll() {
    for (let [nodeId, handle] of Object.entries(this.connects)) dojo.disconnect(handle);
  }

  cargoTiles() {
    return dojo.query('.tile', 'my_ship').filter(
      dojo.hitch(this, (node) => {
        let id = node.id.split('_')[1];
        let type = this.game.tiles[id]['type'];
        if (type != 'cargo' && type != 'hazard') return false;
        dojo.addClass(node, type);
        return true;
      }),
    );
  }

  loseComponents(plId, tiles, delay) {
    delay = delay ? delay : 0;

    var nbTilesInSq1 = dojo.query('.tile', 'square_' + plId + '_-1_discard').length;
    var nbTilesInSq2 = dojo.query('.tile', 'square_' + plId + '_-2_discard').length;
    var interval = 1000 / tiles.length;

    for (var tileId of tiles) {
      var tileDivId = 'tile_' + tileId;

      // See on which discard square these tiles must be placed
      if (nbTilesInSq1 <= nbTilesInSq2) {
        var square = 1;
        var z_index = ++nbTilesInSq1;
      } else {
        var square = 2;
        var z_index = ++nbTilesInSq2;
      }
      dojo.query('.overlay_tile', tileDivId).forEach((n) => dojo.destroy(n));
      var offset = z_index > 10 ? 20 : (z_index - 1) * 2;
      var stylesOnEnd = { left: offset + 'px', top: '-' + offset + 'px', zIndex: z_index };
      let anim = this.game.slideToDomNodeAnim(tileDivId, 'square_' + plId + '_-' + square + '_discard', 1000, delay);
      dojo.connect(anim, 'onEnd', (n) => {
        dojo.style(n, stylesOnEnd);
        this.game.rotate(n, 0);
      });
      anim.play();
      delay += interval;
    }
  }

  ////////////////////////////////////////////////////////////
  ////////////////// CONTENT CHOOSING ////////////////////////

  prepareContentChoice(type, subtype, maxSel, maxRequired, baseStr, maxStr, hasAlien) {
    // type: type of thing, see QA check below for options; for goods, subtype is appended with a '.'
    // maxSel: maximum allowed to be selected
    // maxRequired: the player must choose the max (e.g. when selected crew to lose)
    // baseStr: base strength with nothing selected
    // maxStr: max str (includes alien)
    // hasAlien: whether or not there's a relevant alien (for adding 2 to strength)

    subtype = subtype || '';

    if (type != 'engine' && type != 'shield' && type != 'cannon' && type != 'crew' && type != 'goods' && type != 'cell')
      this.game.throw_bug_report('Unexpected content type: ' + type);

    let typeClass = type == 'engine' || type == 'shield' || type == 'cannon' ? 'cell' : type;

    if (type == 'goods') {
      if (!subtype) this.game.throw_bug_report('Subtype needed for content type ' + type);
      typeClass = ['goods', subtype].join('.');
    }

    this._nbSelected = 0;
    this._maxAllowed = parseInt(maxSel);
    this._typeToSelect = type;
    this._typeClassToSelect = typeClass;
    this._maxRequired = maxRequired;
    this._baseStrength = parseFloat(baseStr) || 0;
    this._hasAlien = hasAlien;

    dojo.query('.' + typeClass, 'my_ship').forEach((node) => {
      this.connect(node, dojo.partial(this.onSelectContent, this));
      dojo.addClass(node, 'available');
    });

    dojo.place(
      this.game.format_string(this.INFOHTML[type], {
        subtype: subtype.charAt(0).toUpperCase() + subtype.slice(1),
        curr: this._baseStrength,
        max: maxStr || this._maxAllowed,
      }),
      'info_box',
      'only',
    );
    dojo.style('info_box', 'display', 'block');
  }

  // Partially-applied function to get this object into a callback
  onSelectContent(this_ship, evt) {
    dojo.stopEvent(evt);

    var contId = evt.currentTarget.id;
    // Deselect this content if it's already selected
    if (dojo.hasClass(contId, 'selected')) {
      dojo.removeClass(contId, 'selected');
      dojo.addClass(contId, 'available');
      this_ship._nbSelected--;
      if (this_ship._nbSelected === this_ship._maxAllowed - 1)
        // all content divs were previously marked as unselectable because max
        // were selected; we add available class back to them
        dojo.query('.' + this_ship._typeClassToSelect + ':not(.selected)').addClass('available');
    }
    // Else this element is available, select it
    else {
      if (this_ship._nbSelected === this_ship._maxAllowed) return;

      dojo.removeClass(contId, 'available');
      dojo.addClass(contId, 'selected');
      this_ship._nbSelected++;
      if (this_ship._nbSelected === this_ship._maxAllowed) dojo.query('.available.content').removeClass('available');
    }

    this_ship.updateInfoBox();
  }

  selectedStr() {
    // strength of selected items including aliens and base strength
    switch (this._typeToSelect) {
      case 'cannon':
      case 'engine':
        var currentStrength = this._baseStrength + this._nbSelected * 2;
        if (this._baseStrength == 0 && this._nbSelected > 0 && this._hasAlien) {
          // baseStrength == 0 means that the alien bonus (if present) is not counted
          // in baseStrength (because no simple engine/cannon), so we must add it if
          // at least one cell is selected
          currentStrength += 2;
        }
        $('curr_sel').innerHTML = currentStrength;
        return currentStrength;
      case 'shield':
        return this._nbSelected;
      case 'cell':
        return this._nbSelected;
      case 'crew':
        return this._nbSelected;
      case 'goods':
        return this._nbSelected;
    }
  }

  updateInfoBox() {
    $('curr_sel').innerHTML = this.selectedStr();
  }

  onValidateContentChoice() {
    if (this._maxRequired && this._nbSelected != this._maxAllowed) {
      this.game.showMessage(_('Wrong number of ' + this._typeToSelect + ' selected'), 'error');
      return;
    }

    let divids = dojo.query('.selected', 'my_ship');

    if (divids.length != this._nbSelected)
      this.game.throw_bug_report(
        'onValidateContentChoice _nbSelected not length of ids: ' + this._nbSelected + '; ' + divids.length,
      );

    return {
      ids: divids.map((i) => this.game.getPart(i.id, 1)),
      contentType: this._typeToSelect,
      strength: this.selectedStr(),
    };
  }

  onLeavingContentChoice() {
    this._nbSelected = this._maxAllowed = this._typeToSelect = this._maxRequired = this._hasAlien = undefined;

    dojo.style('info_box', 'display', 'none');
    dojo.empty('info_box');

    dojo.query('.content', 'my_ship').removeClass('available selected');
    this.disconnectAll();
  }
}
