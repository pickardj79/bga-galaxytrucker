class GTFE_Card {

    PLANET_PREFIX = 'planet';
    MARKER_PREFIX = 'card_marker'

    constructor(game, id, type, varData) {
        this.game = game;
        this.id = id;
        this.type = type;

        // varData holds variable data about the state of the card
        // what it holds is dependent on the type of card and state of the game
        this.varData = varData;
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
        let cardBg = game.cardBg( this.id );
        dojo.style( 'current_card', 'background-position', cardBg.x+'px '+cardBg.y+'px' );

        // place planet elements
        for (let i = 1; i <= 4; i++) {
            dojo.place( game.format_block('jstpl_circle', 
                { idx: i, top: 7+i*47, classes: "planet" }),
                'current_card'
            );
        }

        // place planet cargo 
        return this;
    }

    /// ################# PLANET #########################
    placeAvailMarkers(planetIdxs, cargoIdxs, onclick) {
        let game = this.game;
        for ( let [idx, plId] of Object.entries(planetIdxs) ) {
            if (plId)
                continue;

            let partId = game.makePartId(this.PLANET_PREFIX, idx);

            dojo.addClass(partId, "available");
            game.addTooltip(partId, '', _('Click to select or deselect this planet.'));
            game.connect($(partId), 'onclick', onclick);
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

    onSelectPlanet(id) {
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

    onPlaceGoods(args) {
        console.log("GTFE_Card.onPlaceGoods Not implemented")
    }


}
