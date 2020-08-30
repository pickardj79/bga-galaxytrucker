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

        if (false && type == 'planets') {
            // setup planet circles, add ships if they're on there
            console.log("Placing card markers for",players);
            Object.values(players).forEach(player => {
                console.log("player",player);
                if (player['card_action_choice']) {
                    dojo.place( this.format_block( 'jstpl_card_marker',
                        { plId: player['id'], color: player['color']} ), 
                        game.makePartId(game.PART_PLANET, players['card_action_choice']),
                    );
                    let styles = {

                    };
                    dojo.style("card_marker_" + player['id'], styles);
                }
            });
        }
        return this;
    }

    /// ################# PLANET #########################
    onEnteringChoosePlanet(planetIdxs, onclick) {
        let partIds = this.placePlanetMarkers(planetIdxs, null, true);
        partIds.forEach( id => {
            this.game.addTooltip(id, '', _('Click to select or deselect this planet.'));
            this.game.connect($(id), 'onclick', onclick);
        });
    }

    onEnteringPlaceGoods(planetIdxs, cargoIdxs, onclick) {
        let partIds = this.placePlanetMarkers(planetIdxs, cargoIdxs, false);
        partIds.forEach( id => {
            this.game.addTooltip(id, '', _('Click to select or deselect this planet.'));
            this.game.connect($(id), 'onclick', onclick);
        });
    }

    placePlanetMarkers(planetIdxs, cargoIdxs, showAvail) {
        let game = this.game;
        let partIds = [];
        // for (let i in avail ) {
        for ( let [idx, plId] of Object.entries(planetIdxs) ) {
            // let idx = avail[i];
            // let idx = planetIdxs[i];
            let partId = game.makePartId(this.PLANET_PREFIX, idx);
            partIds.push(partId);

            // Create planet_X circle
            dojo.place( game.format_block('jstpl_circle', 
                { idx: idx, top: 5+idx*47, classes: "planet" }),
                'current_card'
            );

            // Add ship if occupied or available class if showAvail
            if (plId) {
                dojo.place( game.format_block( 'jstpl_card_marker',
                    { plId: plId, color: game.players[plId]['color'] } ), 
                    partId
                );
            }
            else if (showAvail) {
                dojo.addClass(partId, "available");
            } 
        }
        return partIds;
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
