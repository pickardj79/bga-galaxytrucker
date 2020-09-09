// const GTFE_Tile = require('./GTFE_Tile.js');

class GTFE_Card {

    MARKER_PREFIX = 'card_marker'

    constructor(game, id, type, varData) {
        this.game = game;
        this.id = id;

        // unclear if this is ever set
        this.type = type;

        // onclick connects
        this.connects = {};

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

    connect(node, func) {
        // assume all onclicks, for simplicity
        let handle = dojo.connect(node, 'onclick', func);
        this.connects[node.id] = handle;
        return handle;
    }

    disconnectAll() {
        for ( let [nodeId, handle] of Object.entries(this.connects) )
            dojo.disconnect(handle);
    }

    /// ################# MISC #########################
    processContentChoice(payload) {
        // Returns false if payload was not processed
        if (! payload['contentType'] in ['engine', 'crew']) {
            this.game.throw_bug_report("Unknown content type in GTFE_Card.processContentChoice");
            return true;
        }
        if (payload['contentType'] == 'engine' && payload['str'] == 0) {
            let msg = 'Are you sure you do not want to power any engines?'
            this.game.giveUpDialog(msg, 'contentChoice.html', payload);
            return true;
        }

        return false;
    }

    /// ################# PLANET #########################
    placePlanetAvail(planetIdxs) {
        let game = this.game;
        for ( let [idx, plId] of Object.entries(planetIdxs) ) {
            if (plId)
                continue;

            let partId = game.makePartId(game.PLANET_PREFIX, idx);

            dojo.addClass(partId, "available");
            game.addTooltip(partId, '', _('Click to select or deselect this planet.'));
            this.connect($(partId), this.onSelectPlanet);
        }
    }

    placeCardMarkers(markerIdxs) {
        // this is intended for planets only, to place ship markers on the planets card
        if(!markerIdxs)
            return;

        let game = this.game;
        for ( let [idx, plId] of Object.entries(markerIdxs) ) {
            if (!plId)
                continue;
            let partId = game.makePartId(game.PLANET_PREFIX, idx);
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
        // If this element is available, select it, deselect all others
        else {
            dojo.query('.selected', 'current_card').removeClass('selected');
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

        dojo.place( game.format_block( 'jstpl_card_marker',
            { plId: plId, color: game.players[plId]['color'] } ), 
            'overall_player_board_'+plId );
        let planetId = game.makePartId(game.PLANET_PREFIX, args.planetId);
        game.slideToDomNode( 
            game.makePartId(this.MARKER_PREFIX, plId), 
            planetId,800,0,
            {'position':'absolute'},
            {'x': 6, 'y': 6} 
            // {'x': dojo.style(planetId,'left') + 6, 'y': dojo.style(planetId,'top') + 6} 
        );

    }

    onLeavingChoosePlanet() {
        this.disconnectAll();

        // remove all classes, onClicks, and extraneous markers
        dojo.query('.selected').removeClass('selected');
        dojo.query('.available').forEach( n=> {
            // dojo.disconnect(n, 'onclick');
            dojo.removeClass(n, 'available');
        });

        dojo.query('.ship_marker', 'current_card').forEach( n => dojo.destroy(n) );
    }


}
