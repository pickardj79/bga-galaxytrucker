// const GTFE_Tile = require('./GTFE_Tile.js');

class GTFE_Card {

    PLANET_PREFIX = 'planet';
    MARKER_PREFIX = 'card_marker'
    ALL_PLANET_GOODS_CLASSES = [1,2,3,4,5].map( i => "planet_goods_" + i).join(" ");
    ALL_TRASH_GOODS_CLASSES = [...Array(15).keys()].map( i => "trash_" + i).join(" ");

    constructor(game, id, type, varData) {
        this.game = game;
        this.id = id;
        this.type = type;


        // varData holds variable data about the state of the card
        // what it holds is dependent on the type of card and state of the game
        this.varData = varData;
    }

    static cardBg( cardId ) {
        var x = (cardId%20) * -165;
        var y = ( Math.floor(cardId/20) ) * -253;
        return { x: x, y: y };
    }

    setId(id) {
        this.id = id;
        return this;
    }

    setupImage() {
        if (this.id == null || this.id === "-1")
            return this;
        
        let game = this.game;

        dojo.style( 'current_card', 'display', 'block' );
        let cardBg = GTFE_Card.cardBg( this.id );
        dojo.style( 'current_card', 'background-position', cardBg.x+'px '+cardBg.y+'px' );

        // place planet elements
        for (let i = 1; i <= 4; i++) {
            dojo.place( game.format_block('jstpl_circle', 
                { idx: i, top: 7+i*47, classes: "planet" }),
                'current_card'
            );
        }

        return this;
    }

    /// ################# PLANET #########################
    placePlanetAvail(planetIdxs) {
        let game = this.game;
        for ( let [idx, plId] of Object.entries(planetIdxs) ) {
            if (plId)
                continue;

            let partId = game.makePartId(this.PLANET_PREFIX, idx);

            dojo.addClass(partId, "available");
            game.addTooltip(partId, '', _('Click to select or deselect this planet.'));
            game.connect($(partId), 'onclick', this.onSelectPlanet);
        }
    }

    placeCardMarkers(planetIdxs) {
        let game = this.game;
        for ( let [idx, plId] of Object.entries(planetIdxs) ) {
            if (!plId)
                continue;
            let partId = game.makePartId(this.PLANET_PREFIX, idx);
            dojo.place( game.format_block( 'jstpl_card_marker',
                { plId: plId, color: game.players[plId]['color'] } ), 
                partId
            );
        }
    }

    onSelectPlanet(evt) {
        console.log('onSelectPlanet', evt.currentTarget);
        dojo.stopEvent(evt);
        let id = evt.currentTarget.id;

        // Deselect this planet if it's already selected
        if (dojo.hasClass(id, 'selected'))
            dojo.removeClass(id, 'selected');
        // If this element is available, select it, deselected all others
        else {
            dojo.query('.selected', 'current_card').forEach(
                dojo.hitch(this.game, (node) => {
                    dojo.removeClass(node, 'selected')
                })
            );
            dojo.addClass(id, 'selected');
        }
    }

    onConfirmPlanet() {
        let selected = dojo.query('.selected', 'current_card');
        if (selected.length != 1) {
            this.game.showMessage( _("You must select a planet or pass."), 'error');
            return;
        }
        let idx = this.game.getPartFromId(selected[0].id);

        return idx;
    }

    onPassChoosePlanet() {
        let selected = dojo.query('.selected', 'current_card');
        if (selected.length != 0) {
            this.game.showMessage( _("You cannot pass if a planet is selected. Deselected it first."), 'error');
            return false;
        }
        return true;``
    }

    notif_planetChoice(args) {
        let game = this.game;
        let plId = args.plId;

        dojo.query('.selected', 'current_card').removeClass('selected');
        dojo.query('.available', 'current_card').removeClass('available');
        dojo.place( game.format_block( 'jstpl_card_marker',
            { plId: plId, color: game.players[plId]['color'] } ), 
            'overall_player_board_'+plId );
        let planetId = game.makePartId(this.PLANET_PREFIX, args.planetId);
        game.slideToDomNode( 
            game.makePartId(this.MARKER_PREFIX, plId), 
            planetId,800,0,
            {'position':'absolute'},
            {'x': 6, 'y': 6} 
            // {'x': dojo.style(planetId,'left') + 6, 'y': dojo.style(planetId,'top') + 6} 
        );

    }

    /// ################# PLANET #########################
    placeGoods(cardType, planetIdx) {
        let game = this.game;
        if (cardType['type'] == 'planets') {
            if (!planetIdx in cardType['planets'])
                game.throw_bug_report("planetIdx invalid in GTFE_Card.placeGoods: " + planetIdx);
            // place the goods with the given planet
            let goodsIdx = 1;
            for (let type of cardType['planets'][planetIdx]) {
                dojo.place( game.format_block( 'jstpl_content', {
                    content_id: "planetcargo_" + planetIdx + "_" + goodsIdx,
                    classes: 'goods planet_goods_' + goodsIdx + " " + type,
                } ), game.makePartId(this.PLANET_PREFIX, planetIdx) ); 
                goodsIdx += 1;
            }
        }
        else if (cardType['type'] == 'abstation') {
            game.throw_bug_report("abstation placeGoods not implemented");
        }
        this.activateGoods();
    }


    activateGoods() {
        dojo.query('.goods', 'my_ship').forEach( node => {
            dojo.connect( node, 'onclick', this.onSelectGoods);
            dojo.addClass(node, 'available');
        });

        dojo.query('.goods', 'current_card').forEach( node => {
            dojo.connect( node, 'onclick', this.onSelectGoods);
            dojo.addClass(node, 'available');
        });

        // activate all tiles for clicking
        // add cargo or hazard class to all cargo tiles - those are the activatable ones
        for (let tile of dojo.query('.tile', 'my_ship')) {
            let id = tile.id.split('_')[1];
            let type = this.game.tiles[id]['type']; 
            if (type != 'cargo' && type != 'hazard')
                continue;
            dojo.addClass(tile, type);
            dojo.addClass(tile, 'available');
            dojo.connect(tile, 'onclick', 
                dojo.partial(this.onSelectTile_PlaceGoods, this));
        }  

        // activate Air Lock for clicks
        dojo.addClass('trash_box', 'available');
        dojo.connect($('trash_box'), 'onclick', dojo.partial(this.onSelectTrash_PlaceGoods, this));
    }

    onSelectGoods(evt) {
        dojo.stopEvent(evt);
        let id = evt.currentTarget.id;

        if (dojo.hasClass(id, 'selected'))
            dojo.removeClass(id, 'selected');
        else
            dojo.addClass(id, 'selected');
    }

    onSelectTile_PlaceGoods(this_card, evt) {
        console.log('onSelectTile_PlaceGoods', evt.currentTarget);
        dojo.stopEvent(evt);
        let game = this_card.game;
        let nodeId = evt.currentTarget.id;
        
        // This could be a click on goods
        if (!nodeId.startsWith('tile_'))
            return;

        // Check that there's enough cargo space available
        let tileId = nodeId.split('_')[1];
        let goodsToPlace = dojo.query('.goods.selected');

        if (goodsToPlace.length == 0)
            return;

        let tileObj = game.newGTFE_Tile(tileId);
        let goodsOnTile = tileObj.queryGoods();
        
        if (tileObj.hold - goodsOnTile.length < goodsToPlace.length) {
            game.showMessage(_("Not enough cargo space there."), "error");
            goodsToPlace.removeClass('selected');
            return;
        }

        // red cargo must go on hazard cargo holds
        if (goodsToPlace.filter( node => dojo.hasClass(node, "red")).length > 0 
            && game.tiles[tileId]['type'] != 'hazard')
        {
            game.showMessage(_("Red goods must go on hazardous cargo hold."), "error");
            goodsToPlace.removeClass('selected');
            return;
        }

        // All good, move cargo to available spots
        // Class of content on tile is of form pXonY where Y is hold of tile
        let i = 1;
        for (let good of goodsToPlace) {
            dojo.removeClass(good, this_card.ALL_PLANET_GOODS_CLASSES);
            dojo.removeClass(good, this_card.ALL_TRASH_GOODS_CLASSES);
            dojo.removeClass(good, tileObj.ALL_TILE_CONTENT_CLASSES);
            dojo.addClass(good, tileObj.getEmptyContentClass());
            game.slideToDomNode(good, nodeId, 500, 100*i);
            i += 1;
        }

        goodsToPlace.removeClass('selected');
    }

    onSelectTrash_PlaceGoods(this_card, evt) {
        console.log('onSelectTrash_PlaceGoods', evt.currentTarget);
        dojo.stopEvent(evt);
        let game = this_card.game;
        let nodeId = evt.currentTarget.id;

        let goodsToPlace = dojo.query('.goods.selected');
        if (goodsToPlace.length == 0)
            return;

        let bogus_tile = game.newGTFE_Tile(1); // for static function calls
        console.log("TILE CONTENT", bogus_tile.ALL_TILE_CONTENT_CLASSES);

        let idx = 1;
        let delayCtr = 1;
        let goodsInTrash = dojo.query('.goods', 'trash_box');
        console.log("idx in trash", goodsInTrash);
        for (let good of goodsToPlace) {
            dojo.removeClass(good, 'selected');
            dojo.removeClass(good, this_card.ALL_PLANET_GOODS_CLASSES);
            dojo.removeClass(good, bogus_tile.ALL_TILE_CONTENT_CLASSES);
            dojo.removeClass(good, this_card.ALL_TRASH_GOODS_CLASSES);
            game.slideToDomNode(good, nodeId, 500, delayCtr * 100);
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

            console.log("placing good", i, good);
            dojo.addClass(good, tgt_class); 
        }
    }

}
