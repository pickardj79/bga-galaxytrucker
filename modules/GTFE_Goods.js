class GTFE_Goods {
  ALL_CARD_GOODS_CLASSES = [1, 2, 3, 4, 5].map((i) => 'card_goods_' + i).join(' ');
  ALL_CARD_TOTAL_REWARDS_CLASSES = [1, 2, 3, 4, 5].map((i) => 'card_total_rewards_' + i).join(' ');
  ALL_TRASH_GOODS_CLASSES = [...Array(15).keys()].map((i) => 'trash_' + i).join(' ');

  constructor(game) {
    this.game = game;
    this.connects = {};
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

  onLeavingPlaceGoods() {
    this.disconnectAll();

    // remove all classes, onClicks, and extraneous markers
    dojo.query('.selected').removeClass('selected');
    dojo.query('.available').forEach((n) => {
      // dojo.disconnect(n, 'onclick');
      dojo.removeClass(n, 'available');
    });

    dojo.style('trash_box', 'display', null);

    // clean up markers needed for this card
    dojo.query('.card_goods').forEach((n) => dojo.destroy(n));
    dojo.query('.ship_marker', 'current_card').forEach((n) => dojo.destroy(n));
    dojo.query('#card_rewards').removeClass(this.ALL_CARD_TOTAL_REWARDS_CLASSES);
  }

  /// ################# GOODS #########################
  placeGoods(cardType, planetIdx, plId) {
    let game = this.game;

    let idSuffix = 'cardgoods';
    let classPrefix = undefined;
    let goods = undefined;
    let tgtNodeId = undefined;
    if (cardType['type'] == 'planets') {
      if (!planetIdx in cardType['planets'])
        game.throw_bug_report('planetIdx invalid in GTFE_Card.placeGoods: ' + planetIdx);

      idSuffix += '_' + planetIdx;
      classPrefix = 'planet_goods';
      goods = cardType['planets'][planetIdx];
      tgtNodeId = game.makePartId(game.PLANET_PREFIX, planetIdx);
    } else {
      classPrefix = 'card_reward';
      goods = cardType['reward'];
      dojo.query('#card_rewards').addClass('card_total_rewards_' + goods.length);
      tgtNodeId = 'card_rewards';
    }

    let goodsIdx = 1;
    for (let goodsType of goods) {
      let fullIdSuffix = idSuffix + '_' + goodsIdx;
      let goodId = 'content_' + fullIdSuffix;
      let goodsClass = classPrefix + '_' + goodsIdx;
      dojo.place(
        game.format_block('jstpl_content', {
          content_id: fullIdSuffix,
          classes: 'goods card_goods ' + goodsClass + ' ' + goodsType,
        }),
        'overall_player_board_' + plId,
      );
      game.slideToDomNode(goodId, tgtNodeId, 500, goodsIdx * 100);

      goodsIdx += 1;
    }

    this.activateGoods();
  }

  activateGoods() {
    dojo.query('.goods', 'my_ship').forEach((node) => {
      this.connect(node, this.onSelectGoods);
      dojo.addClass(node, 'available');
    });

    dojo.query('.goods', 'current_card').forEach((node) => {
      this.connect(node, this.onSelectGoods);
      dojo.addClass(node, 'available');
    });

    // activate all tiles for clicking
    this.game
      .newGTFE_Ship()
      .cargoTiles()
      .forEach((tile) => {
        dojo.addClass(tile, 'available');
        this.connect(tile, dojo.partial(this.onSelectTile_PlaceGoods, this));
      });

    // activate Air Lock for clicks
    dojo.style('trash_box', 'display', 'block');
    dojo.addClass('trash_box', 'available');
    this.connect($('trash_box'), dojo.partial(this.onSelectTrash_PlaceGoods, this));
  }

  onSelectGoods(evt) {
    dojo.stopEvent(evt);
    let id = evt.currentTarget.id;

    if (dojo.hasClass(id, 'selected')) dojo.removeClass(id, 'selected');
    else dojo.addClass(id, 'selected');
  }

  onSelectTile_PlaceGoods(this_obj, evt) {
    console.log('onSelectTile_PlaceGoods', evt.currentTarget);
    dojo.stopEvent(evt);
    let game = this_obj.game;
    let nodeId = evt.currentTarget.id;

    // This could be a click on goods, we only want tile clicks
    if (!nodeId.startsWith('tile_')) return;

    // Check that there's enough cargo space available
    let tileId = nodeId.split('_')[1];
    let goodsToPlace = dojo.query('.goods.selected');

    if (goodsToPlace.length == 0) return;

    let tileObj = game.newGTFE_Tile(tileId);
    let goodsOnTile = tileObj.queryGoods();

    if (tileObj.hold - goodsOnTile.length < goodsToPlace.length) {
      game.showMessage(_('Not enough cargo space there.'), 'error');
      goodsToPlace.removeClass('selected');
      return;
    }

    // red cargo must go on hazard cargo holds
    if (
      goodsToPlace.filter((node) => dojo.hasClass(node, 'red')).length > 0 &&
      game.tiles[tileId]['type'] != 'hazard'
    ) {
      game.showMessage(_('Red goods must go on hazardous cargo hold.'), 'error');
      goodsToPlace.removeClass('selected');
      return;
    }

    // All good, move cargo to available spots
    // Class of content on tile is of form pXonY where Y is hold of tile
    let i = 1;
    for (let good of goodsToPlace) {
      tileObj.slideContent(good, null, 500, 100 * i);
      dojo.removeClass(good, this_obj.ALL_CARD_GOODS_CLASSES);
      dojo.removeClass(good, this_obj.ALL_TRASH_GOODS_CLASSES);
      i += 1;
    }

    goodsToPlace.removeClass('selected');
  }

  onSelectTrash_PlaceGoods(this_obj, evt) {
    console.log('onSelectTrash_PlaceGoods', evt.currentTarget);
    dojo.stopEvent(evt);
    let game = this_obj.game;
    let nodeId = evt.currentTarget.id;

    let goodsToPlace = dojo.query('.goods.selected');
    if (goodsToPlace.length == 0) return;

    let bogus_tile = game.newGTFE_Tile('1'); // for static function calls
    console.log('TILE CONTENT', bogus_tile.ALL_TILE_CONTENT_CLASSES);

    let idx = 1;
    let delayCtr = 1;
    let goodsInTrash = dojo.query('.goods', 'trash_box');
    console.log('idx in trash', goodsInTrash);
    for (let good of goodsToPlace) {
      dojo.removeClass(good, 'selected');
      game.slideToDomNode(good, nodeId, 500, delayCtr * 100);
      dojo.removeClass(good, this_obj.ALL_CARD_GOODS_CLASSES);
      dojo.removeClass(good, bogus_tile.ALL_TILE_CONTENT_CLASSES);
      dojo.removeClass(good, this_obj.ALL_TRASH_GOODS_CLASSES);
      delayCtr += 1;

      // find next available spot in the trash
      let tgt_class = '';
      let tgt_found = false;
      do {
        tgt_class = 'trash_' + idx;
        tgt_found = false;
        for (let good of goodsInTrash) {
          // found an item with this class. Go to next class and try again
          if (good.classList.contains(tgt_class)) {
            idx += 1;
            tgt_found = true;
            break;
          }
        }
      } while (tgt_found);
      idx += 1;

      console.log('placing good', i, good);
      dojo.addClass(good, tgt_class);
    }
  }

  onValidateChooseCargo() {
    // find all cargo on my_ship
    // validate red on hazard and no more than hold on each tile
    // return content and tiles for the ajax
    let cargo = {};
    this.game
      .newGTFE_Ship()
      .cargoTiles()
      .forEach((tile) => {
        let tileObj = this.game.newGTFE_Tile(tile.id);
        let goods = tileObj.queryGoods();

        // Some basic checks which should never happen and will be repeated on the back-end
        // These are superfluous but could catch a client-side error
        if (goods.length > tileObj.hold) {
          this.game.showMessage(_('Not enough cargo space on tile ' + tileObj.id), 'error');
          return;
        }

        // red cargo must go on hazard cargo holds
        if (goods.filter((node) => dojo.hasClass(node, 'red')).length > 0 && tileObj.type != 'hazard') {
          this.game.showMessage(_('Red goods must go on hazardous cargo hold, tile '.tileObj.id), 'error');
          return;
        }

        cargo[tile.id] = goods.map((x) => x.id);
      });
    return cargo;
  }

  notif_cargoChoice(args) {
    // Note - animations are mostly done for active player

    // Deletes (array[content_ids: int])
    let toCard = !this.game.isCurrentPlayerActive();
    for (let id of args.deleteContent) this.game.newGTFE_Tile(1).loseContent({ id: id }, 0, toCard);

    // New Content (tileId: [{cont1},{cont2}])
    let slideFrom = this.game.isCurrentPlayerActive() ? null : 'current_card';
    for (let [tileId, allContent] of Object.entries(args.newTileContent)) {
      let tile = this.game.newGTFE_Tile(tileId);
      for (let cont of allContent) {
        tile.placeContent(cont, slideFrom);
      }
    }

    // Moved Content (tileId: [{cont1},{cont2}])
    for (let [tileId, allContent] of Object.entries(args.movedTileContent)) {
      let tile = this.game.newGTFE_Tile(tileId);
      for (let cont of allContent) {
        tile.slideContent('content_' + cont.content_id, cont.place);
      }
    }

    dojo.query('.');
  }
}
