// const GTFE_Tile = require('./GTFE_Tile.js');

class GTFE_Card {
  MARKER_PREFIX = 'card_marker';

  constructor(game, id, cardData) {
    this.game = game;
    this.id = id;

    if (cardData) {
      // setup object based on GT_StatesCard::currentCardData()
      this.type = cardData['type'];
      this.curHazard = cardData['curHazard'];
      this.card_line_done = cardData['card_line_done']; // player => {data}
    }

    // onclick connects
    this.connects = {};
  }

  static cardBg(cardId) {
    var x = (cardId % 20) * -165;
    var y = Math.floor(cardId / 20) * -253;
    return { x: x, y: y };
  }

  setId(id) {
    this.id = id;
    // we don't have any of this data
    this.type = this.progress = this.die1 = this.die1 = this.card_line_done = undefined;
    return this;
  }

  setupImage() {
    if (this.id == null || this.id === '-1') return this;

    let game = this.game;

    dojo.style('current_card', 'display', 'block');
    let cardBg = GTFE_Card.cardBg(this.id);
    dojo.style('current_card', 'background-position', cardBg.x + 'px ' + cardBg.y + 'px');

    // notif will turn dice_box on when needed
    dojo.style('dice_box', 'display', 'none');

    // place planet elements
    dojo.query('.planet').forEach(dojo.destroy);
    for (let i = 1; i <= 4; i++) {
      dojo.place(game.format_block('jstpl_circle', { idx: i, classes: 'planet' }), 'current_card');
    }

    if (!this.type) return this;
    if (this.curHazard && this.curHazard.die1 != '0') {
      this._placeDice(this.curHazard.die1, this.curHazard.die2);
      if (this.card_line_done[game.player_id]['card_line_done'] != 2) this._placeHazard(this.curHazard);
    }

    return this;
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

  /// ################# MISC #########################
  processContentChoice(payload) {
    const CARD_METEORIC_SWARM = 7;
    payload['ids_str'] = payload['ids'].join();

    // Returns false if payload was not processed
    if (!payload['contentType'] in ['engine', 'cannon', 'shield', 'crew']) {
      this.game.throw_bug_report('Unknown content type in GTFE_Card.processContentChoice');
      return true;
    }
    if (payload['contentType'] == 'engine' && payload['strength'] == 0) {
      let msg = 'Are you sure you do not want to power any engines?';
      this.game.giveUpDialog(msg, 'contentChoice.html', payload);
      return true;
    }
    if (payload['contentType'] == 'cannon' && payload['strength'] == 0 && this.type == CARD_METEORIC_SWARM) {
      let msg = 'Are you sure you do not want to power cannons. Your ship will be damaged!';
      this.game.confirmDialog(msg, 'contentChoice.html', payload);
      return true;
    }
    if (payload['contentType'] == 'shield' && payload['strength'] == 0) {
      let msg = 'Are you sure you do not want to power shields. Your ship will be damaged!';
      this.game.confirmDialog(msg, 'contentChoice.html', payload);
      return true;
    }

    // if choosing crew, and all humans are selected, give confirmation dialog
    if (payload['contentType'] == 'crew') {
      let nbrTotHum = dojo.query('.human', 'my_ship');
      let nbrSelHum = payload['ids']
        .flatMap((i) => dojo.query('#content_' + i))
        .filter((i) => i.classList.contains('human'));
      console.log('nbrs', nbrSelHum, nbrTotHum);

      if (nbrSelHum.length == nbrTotHum.length) {
        let msg = 'Are you sure you want to lose all your humans?';
        this.game.giveUpDialog(msg, 'contentChoice.html', payload);
        return true;
      }
    }

    return false;
  }

  /// ################# PLANET #########################
  placePlanetAvail(planetIdxs) {
    let game = this.game;
    for (let [idx, plId] of Object.entries(planetIdxs)) {
      if (plId) continue;

      let partId = game.makePartId(game.PLANET_PREFIX, idx);

      dojo.addClass(partId, 'available');
      game.addTooltip(partId, '', _('Click to select or deselect this planet.'));
      this.connect($(partId), this.onSelectPlanet);
    }
  }

  placeCardMarkers(markerIdxs) {
    // this is intended for planets only, to place ship markers on the planets card
    if (!markerIdxs) return;

    let game = this.game;
    for (let [idx, plId] of Object.entries(markerIdxs)) {
      if (!plId) continue;
      let partId = game.makePartId(game.PLANET_PREFIX, idx);
      dojo.place(game.format_block('jstpl_card_marker', { plId: plId, color: game.players[plId]['color'] }), partId);
    }
  }

  onSelectPlanet(evt) {
    console.log('onSelectPlanet', evt.currentTarget);
    dojo.stopEvent(evt);
    let id = evt.currentTarget.id;

    // Deselect this planet if it's already selected
    if (dojo.hasClass(id, 'selected')) dojo.removeClass(id, 'selected');
    // If this element is available, select it, deselect all others
    else {
      dojo.query('.selected', 'current_card').removeClass('selected');
      dojo.addClass(id, 'selected');
    }
  }

  onConfirmPlanet() {
    let selected = dojo.query('.selected', 'current_card');
    if (selected.length != 1) {
      this.game.showMessage(_('You must select a planet or pass.'), 'error');
      return;
    }
    let idx = this.game.getPartFromId(selected[0].id);

    return idx;
  }

  onPassChoosePlanet() {
    let selected = dojo.query('.selected', 'current_card');
    if (selected.length != 0) {
      this.game.showMessage(_('You cannot pass if a planet is selected. Deselected it first.'), 'error');
      return false;
    }
    return true;
    ``;
  }

  notif_planetChoice(args) {
    let game = this.game;
    let plId = args.plId;

    dojo.place(
      game.format_block('jstpl_card_marker', { plId: plId, color: game.players[plId]['color'] }),
      'overall_player_board_' + plId,
    );
    let planetId = game.makePartId(game.PLANET_PREFIX, args.planetId);
    game.slideToDomNode(
      game.makePartId(this.MARKER_PREFIX, plId),
      planetId,
      800,
      0,
      { position: 'absolute' },
      { x: 6, y: 6 },
      // {'x': dojo.style(planetId,'left') + 6, 'y': dojo.style(planetId,'top') + 6}
    );
  }

  onLeavingChoosePlanet() {
    this.disconnectAll();

    // remove all classes, onClicks, and extraneous markers
    dojo.query('.selected').removeClass('selected');
    dojo.query('.available').forEach((n) => {
      // dojo.disconnect(n, 'onclick');
      dojo.removeClass(n, 'available');
    });

    dojo.query('.ship_marker', 'current_card').forEach((n) => dojo.destroy(n));
  }

  /// ################# HAZARDS #########################
  notif_hazardDiceRoll(args, isCurPlayer, gaveUp) {
    let game = this.game;

    // don't turn off dice_box, clean-up code will do so

    console.log('placing hazard with', args, args.hazResults.die1, isCurPlayer, gaveUp);
    let anim = game.myFadeOutAndDestroy(dojo.query('.die', 'dice_box'), 500);
    dojo.connect(anim, 'onEnd', () => {
      this._placeDice(args.hazResults.die1, args.hazResults.die2);
      if (isCurPlayer && !gaveUp) this._placeHazard(args.hazResults);
    });
    anim.play();
  }

  hazardMissed(args) {
    let endDiv = this._hazResultsToDiv(args.hazResults, true);
    console.log('Sliding to: ', endDiv);
    this.game.slideToObjectAndDestroy('current_hazard', endDiv, 500);
  }

  hazardHit(tileId, hazResults) {
    // Slide hazard to hit the exposed tile. Move it a few pixels onto the tile
    // so it appears to actually hit the ship :).
    let x = 0;
    let y = 0;
    let hazHeight = $('current_hazard').offsetHeight - 10;
    let hazWidth = $('current_hazard').offsetWidth - 10;
    switch (hazResults.orient) {
      case 0:
        y = -hazHeight;
        break; // from top, shift up by it's height
      case 90:
        x = 40;
        break; // from right, shift to right of tile
      case 180:
        y = 40;
        break; // from bottom, shift down by a tile's height
      case 270:
        x = -hazWidth;
        break; // from left, shift left by it's width
      default:
        this.game.throw_bug_report('Unexpected orient: ' + orient);
    }
    let anim = this.game.slideToObjectPos('current_hazard', 'tile_' + tileId, x, y, 500);
    dojo.connect(anim, 'onEnd', () => dojo.fadeOut({ node: 'current_hazard', delay: 500, onEnd: dojo.destroy }).play());
    anim.play();
  }

  _placeDice(die1, die2) {
    let game = this.game;

    dojo.style('dice_box', 'display', 'block');

    dojo.place(game.format_block('jstpl_die', { nbr: die1, idx: 1 }), 'current_card');
    dojo.place(game.format_block('jstpl_die', { nbr: die2, idx: 2 }), 'current_card');
    let i = 0;
    dojo.query('.die', 'current_card').forEach((n) => game.slideToDomNode(n, 'dice_box', 500, i++ * 100));
  }

  _placeHazard(hazard) {
    let game = this.game;
    // hazard needs: die1, die2, type, row_col, size, orient, missed (see GT_StatesCard.currentCardData)
    let sizeClass = hazard.size == 's' ? 'small' : 'big';
    let startDiv = this._hazResultsToDiv(hazard);

    // Place hazard and blink it
    dojo.place(
      game.format_block('jstpl_hazard', {
        size: sizeClass,
        type: hazard.type,
        row_col: hazard.row_col,
      }),
      startDiv,
    );
    let anim = dojo.fx.chain([
      dojo.fadeOut({ node: 'current_hazard' }, 500, 1500),
      dojo.fadeIn({ node: 'current_hazard' }),
      dojo.fadeOut({ node: 'current_hazard' }),
      dojo.fadeIn({ node: 'current_hazard' }),
    ]);
    anim.play();
  }

  _hazResultsToDiv(hazard, reverse) {
    // given a hazard result, create the row or column div
    // reverse will give the opposite side

    let roll = parseInt(hazard.die1) + parseInt(hazard.die2);

    let orient = hazard.orient;
    if (reverse) orient = (orient + 180) % 360;

    switch (orient) {
      case 0:
        return 'column_' + roll + '_top';
        break;
      case 90:
        return 'row_' + roll + '_right';
        break;
      case 180:
        return 'column_' + roll + '_bottom';
        break;
      case 270:
        return 'row_' + roll + '_left';
        break;
      default:
        this.game.throw_bug_report('Unexpected orient: ' + orient);
    }
  }
}
