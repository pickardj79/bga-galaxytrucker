/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * GalaxyTrucker implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * galaxytrucker.js
 *
 * GalaxyTrucker user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
  "dojo","dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/zone",
  "ebg/stock"
],
function (dojo, declare) {
  return declare("bgagame.galaxytrucker", ebg.core.gamegui, {
    constructor: function(){
      console.log('galaxytrucker constructor');
        // Here, you can init the global variables of your user interface
      // Example:
      // this.myGlobalValue = 0;

      // ship coordinates definition: the first index is the ship class, and in each ship
      // class array the indexes are the row numbers ships coordinates definition for x must
      // begin at 3, i.e. the first digit on each line corresponds to column no3 of ship board
      this.ships = {
        I: {   yMinIndex: 5,
               yMaxIndex: 9,
               leftPosRef: 12,
               leftDiscard: 350,
               topDiscard: 25,
               5:[0,0,0,0,1,0,0,0,0],
               6:[0,0,0,1,1,1,0,0,0],
               7:[0,0,1,1,1,1,1,0,0],
               8:[0,0,1,1,1,1,1,0,0],
               9:[0,0,1,1,0,1,1,0,0] },
        II: {  yMinIndex: 4,
               yMaxIndex: 9,
               leftPosRef: 12,
               leftDiscard: 350,
               topDiscard: 25,
               4:[0,0,0,0,1,0,0,0,0],
               5:[0,0,0,1,1,1,0,0,0],
               6:[0,0,1,1,1,1,1,0,0],
               7:[0,0,1,1,1,1,1,0,0],
               8:[0,1,1,1,1,1,1,1,0],
               9:[0,1,1,1,0,1,1,1,0] },
        III: { yMinIndex: 4,
               yMaxIndex: 9,
               leftPosRef: 12,
               leftDiscard: 350,
               topDiscard: 25,
               4:[0,0,0,0,1,0,0,0,0],
               5:[0,0,0,1,1,1,0,0,0],
               6:[1,0,1,1,1,1,1,0,1],
               7:[1,1,1,1,1,1,1,1,1],
               8:[1,1,1,1,1,1,1,1,1],
               9:[1,1,0,1,1,1,0,1,1] },
        IIIa:{ yMinIndex: 3,
               yMaxIndex: 11,
               leftPosRef: -64,
               leftDiscard: 245,
               topDiscard: 5,
               3:[0,0,0,1,1,1,0,0,0],
               4:[0,0,1,1,1,1,1,0,0],
               5:[0,0,1,1,0,1,1,0,0],
               6:[0,0,1,1,1,1,1,0,0],
               7:[0,0,0,1,1,1,0,0,0],
               8:[0,0,1,0,1,0,1,0,0],
               9:[0,0,1,1,1,1,1,0,0],
              10:[0,0,1,1,1,1,1,0,0],
              11:[0,0,1,0,1,0,1,0,0] } };
      this.abandonedShipIds = [ 16,17,36,37,56,57 ];

      this.plReportBug = "This shouldn't happen, please report this bug with the full error message.";
      this.statesWithoutOverlayTiles = [ 'buildPhase', 'takeOrderTiles', 'repairShips' ];
      this.powEngInfoHtml = "<p>"+_('Engine strength:')+" <span id='curr_str'>${curr}</span></p>"+
              "<p>"+_('Max engine strength:')+" <span id='max_str'>${max}</span></p>";
      this.chooseCrewInfoHtml = "<p><span id='curr_sel'>${curr}</span> / "+
              "<span id='needed_sel'>${needed}</span></p>";
      this.choosePlanetInfoHtml = "<p><span id='curr_sel'>${curr}</span></p>";
      this.noBuildMessage = false; // Set to true if a player doesn't want to see
                                    // the build message
      this.stateName = null; // Is updated in every onEnteringState
      this.current_tile = null; // Tile in current player's hand, if any
      this.currentTileWasAside = false; // if true, the tile in hand can't be dropped in revealed pile
      this.undoPossible = null; // Stores the last tileDivId placed by this player, so that they can take
                                          // this tile back until they grab another tile (rules)
      this.atLeast1Tile = 0; // At least 1 tile placed, to know if current player can look at the cards
      this.timerPlace = null; // From 3 (or 4 for class IV ships) to 0, 0 is the "start" circle.
      this.timeLeft = null; // Time left before the timer has finished running
      this.timeoutID = null; // keeps the timeout ID so that we can stop it when everybody has
                              // finished their ship (in onLeavingState buildPhase)
      this.dir = "0"; // orientation of the tile in hand (can be 0, 90 (when the tile has been turned
                      // once clockwise), 180 or 270)
      this.followMouseHandle = null; // Used to make the tile in hand follow the mouse
      this.tempMousePosX = this.tempMousePosY = null; // Used to track mouse position while waiting
                                          // for a picked tile notif, in order to set css top and left
                                          // for tile in hand as soon as the notif is rceived, even if
                                          // the mouse doesn't move (because in the callback function
                                          // connected in followMouse(), dojo.style sets the tile position
                                          // only when the mouse move, until which the tile is not visible)
      this.sandTimerHandle = null; // Used to connect and disconnect the timer to onFlipTimer
      this.currentCard = null;
      this.baseStrength = null;
      this.nbSelected = null;
      this.maxSelected = null;
      this.typeToSelect = null; // can be "crew", "cell", "goods"
      this.wholeCrewWillLeave = false; // used to display a confirmation dialog if exploring an
                                        // abandoned ship will cause to give up
    },

      /*
          setup:

            This method must set up the game user interface according to current game situation specified
            in parameters.

            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)

            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */

    setup: function( gamedatas ) {
        console.log( "Starting game setup" );
        console.log( gamedatas );
        var bWaitBuildOrTakeOrderPhase = ( gamedatas.gamestate.name == 'waitForPlayers'
                    || gamedatas.gamestate.name == 'buildPhase'
                    || gamedatas.gamestate.name == 'takeOrderTiles');
        if ( typeof g_replayFrom != "undefined" ) {
            dojo.addClass( 'ebd-body', 'replay_running' );
        }

        // Setting up player boards and other things
        for( var player_id in gamedatas.players ) {
            var player = gamedatas.players[player_id];
            // Setting up players boards
            this.placePlBoardItem( "minMaxCann", player_id );
            this.placePlBoardItem( "minMaxEng", player_id );
            this.placePlBoardItem( "nbCrew", player_id );
            this.placePlBoardItem( "expConn", player_id );
            // The following values should be evaluated to an empty
            // string if null (null if not calculated yet)
            $( "nbCrew_"+player_id ).innerHTML = player.nb_crew;
            $( "expConn_"+player_id ).innerHTML = player.exp_conn;
            if ( player.min_eng !== null ) {
                // min_eng is not null, this means that engine and cannon strength
                // have been calculated, so we display them
                $( "minMaxEng_"+player_id ).innerHTML = player.min_eng+"/"+player.max_eng;
                $( "minMaxCann_"+player_id ).innerHTML = (player.min_cann_x2 / 2)+
                                                        "/"+(player.max_cann_x2 / 2);
            }

            // Setting up ships
            this.setupShip( gamedatas.shipClass, player.id );
            // Setting up ship markers on flight board
            if ( player.player_position !== null ) {
                var shipPos = ( +(player.player_position)+40 ) % 40;
                dojo.place( this.format_block( 'jstpl_ship_marker',
                        { plId: player.id, color: player.color } ), 'flight_pos_'+shipPos );
            }
        }

        this.setupPlacedTiles( gamedatas.placed_tiles, bWaitBuildOrTakeOrderPhase );

        // Face down card piles
        //(always created only once on page loading, only the round_x CSS style is
        // modified each round, and they're hidden during flights)
        for( var i=1 ; i<=3 ; i++ ) {
            var divId = 'card_pile_'+i;
            this.addTooltip( divId, '', _('While building, you can click'+
                                    ' on a card pile to peek at them.') );
            this.connect( $(divId), 'onclick', 'onLookPile' );
            dojo.addClass( $(divId), 'round_'+gamedatas.round );
            this.connect( $('cards_reveal_'+i), 'onclick', 'onBanishPile' );
        }
        this.connect( $('cards_reveal_shadow'), 'onclick', 'onBanishPile' );

        if ( gamedatas.cards )
            this.setupRevealedCards( gamedatas.cards );

        if ( gamedatas.gamestate.name == 'waitForPlayers'
             ||  gamedatas.gamestate.name == 'buildPhase' ) {
            // Face down tiles pile
            for( var i=0 ; i < gamedatas.tilesLeft ; i++ )
                this.placeHiddenTile();
        }

        console.log( 'gamedatas.gamestate.name: ', gamedatas.gamestate.name );
        //// Only in BUILD PHASE
        if ( gamedatas.gamestate.name == 'buildPhase') {
            if ( this.isCurrentPlayerActive() ) {
              if ( gamedatas.current_tile !== null ) {
                this.current_tile = gamedatas.current_tile.id;
                var tileDivId = 'tile_'+this.current_tile;
                if ( gamedatas.current_tile.aside == 1 )
                    this.currentTileWasAside = true;
                dojo.place( tileDivId, 'in_hand_div' );
                if ( typeof g_replayFrom == "undefined" && !this.isTouchDevice )
                    this.followMouse( null );
              }
              else {
                this.current_tile = null;
                if ( gamedatas.undoPossible ) {
                  this.undoPossible = 'tile_'+gamedatas.undoPossible;
                  dojo.addClass( this.undoPossible, 'available' );
                  this.connect( $(this.undoPossible), 'onclick', 'onPickLastPlaced' );
                  // So that this player can take this tile back until
                  // they grab another tile (rules)
                }
              }
              if ( gamedatas.atLeast1Tile == 1 )
                  this.atLeast1Tile = 1;
            }

            for( var id in gamedatas.revealed_tiles ) {
                var tileDivId = 'tile_'+id;
                this.positionInRevealedPile( tileDivId, gamedatas.revealed_tiles[id], false );
            }
        }

        if ( bWaitBuildOrTakeOrderPhase ) {
            // Timer
            if ( gamedatas.gamestate.name == "waitForPlayers" ) {
                $('sandTimer').innerHTML = "90";
                this.timerPlace = gamedatas.round;
            }
            else if ( gamedatas.gamestate.name == "takeOrderTiles" ) {
                $('sandTimer').innerHTML = "0";
                this.timerPlace = 0;
            }
            else { // buildPhase state
                this.timerPlace = gamedatas.timerPlace;
                if ( typeof g_replayFrom != "undefined" ) {
                    // Replay mode, timer isn't started
                    $('sandTimer').innerHTML = "N/A";
                    // So that the timer is visible even in replay mode
                }
                else {
                    this.timeLeft = gamedatas.timeLeft;
                    this.updateTimer();
                }
            }
            dojo.place( 'sandTimer', 'timerPlace_'+this.timerPlace );
            dojo.style( 'sandTimer', 'display', 'inline' );
        }
        //// End of BUILD PHASE

        // Order tiles
        if ( typeof gamedatas.order_tiles != "undefined" ) {
            for ( var i in gamedatas.order_tiles ) {
                var ordTileArg = gamedatas.order_tiles[i];
                if ( ordTileArg == 'available' )
                    var dest = 'order_tile_slot_'+i;
                else
                    var dest = 'ordTileSlotOnShip_'+ordTileArg;
                dojo.place( this.format_block( 'jstpl_order_tile',
                            { i: i } ) , dest );
                if ( ordTileArg == 'available' && this.isCurrentPlayerActive() ) {
                    this.addTooltip( 'order_tile_'+i, '', _('Click here to finish your ship.') );
                    this.connect( $('order_tile_'+i), 'onclick', 'onFinishShip');
                }
            }
        }

        for( var i in gamedatas.content )
            this.placeContent ( gamedatas.content[i] );

        if ( gamedatas.currentCard !== "-1" ) {
            this.currentCard = gamedatas.currentCard;
            var cardBg = this.cardBg( gamedatas.currentCard );
            dojo.style( 'current_card', 'background-position', cardBg.x+'px '+cardBg.y+'px' );
        }

        this.addTooltip( 'sandTimer', '', _('Click here to flip the timer when it is finished.') );
        
        // Setup game notifications to handle (see "setupNotifications" method below)
        this.setupNotifications();

        console.log( "Ending game setup" );
    },


        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
    onEnteringState: function( stateName, args ) {
        console.log( 'Entering state: '+stateName );
        console.log( 'args for state: ', args.args);
        this.stateName = stateName;

        switch( stateName ) {

        case 'waitForPlayers':
            dojo.style( 'flight', 'display', 'none' );
            dojo.style( 'pile', 'display', 'block' );
            dojo.style( 'timer_cards_order', 'display', 'block' );
            // TODO set tooltips in setup?
            this.addTooltip( 'clickable_pile', '', _('Click on this zone to pick a new tile.') );
            this.addTooltip( 'revealed_pile_wrap', '', 
                            _('Click on this zone to drop the tile you have in hand.') );
            this.addTooltipToClass( 'turn', '', _('Click here to rotate the tile you have in hand.') );

            break;
        case 'buildPhase':
            if ( this.isCurrentPlayerActive() ) {
                if ( !this.noBuildMessage ) {
                    dojo.style( 'build_message', 'display', 'table' );
                    this.connect( $('close_buildMessage'), 'onclick', 'onCloseBuildMessage' );
                }
                // Maybe we should move some of the instructions below in this if block?
                // None seems to be problematic, since actions are prevented when player is inactive
                // Update: connections to events are problematic because divs are connected again
                // at the beginning of the next round, and trigger their action twice.
                console.log('current player is active');
                this.connect( $('turn_left'), 'onclick', 'onTurn' );
                this.connect( $('turn_right'), 'onclick', 'onTurn' );
                this.connect( $('clickable_pile'), 'onclick', 'onClickPile' );
                this.connect( $('revealed_pile_wrap'), 'onclick', 'onDropTile' );
                dojo.query('.square.available').forEach(
                    dojo.hitch( this, function( node ) {
                        this.connect( node, 'onclick', 'onPlaceTile');
                        } ) );
                dojo.query('.discard .tile', 'my_ship').forEach( // here or in setup?
                    dojo.hitch( this, function( node ) {
                        this.connect( node, 'onclick', 'onPickAside');
                        dojo.addClass( node, 'available');
                        } ) );
                dojo.addClass( 'clickable_pile', 'clickable');
                if ( this.current_tile !== null ) {
                    dojo.addClass( 'revealed_pile_wrap', 'clickable' );
                    dojo.query( '.turn' ).addClass( 'clickable' );
                }
            }
            dojo.style( 'flight', 'display', 'none' );
            dojo.style( 'pile', 'display', 'block' );
            dojo.style( 'timer_cards_order', 'display', 'block' );

            break;
            
        case 'takeOrderTiles':
            dojo.style( 'timer_cards_order', 'display', 'block' );
            dojo.style( 'flight', 'display', 'none' );
            break;

        case 'repairShips':
            dojo.style( 'sandTimer', 'display', 'none' ); // here? Necessary if we hide timer_cards_order?
            dojo.style( 'flight', 'display', 'none' );
            dojo.style( 'timer_cards_order', 'display', 'none' ); // here?
            break;

        case 'prepareFlight':
            dojo.query('.order_tile').forEach(dojo.destroy);
            dojo.query('.card', 'cards_reveal_wrap' ).forEach(dojo.destroy);
            dojo.style( 'flight', 'display', '' );
            break;

        case 'placeCrew':
            // In tiles where the active player has a choice to make between an alien and humans,
            // connect alien(s) and humans images to onChooseAlien method
            if ( this.isCurrentPlayerActive() )
                this.connectAlienChoices();
            break;
 
        case 'drawCard':
        case 'notImpl':
        case 'stardust':
        case 'openspace':
        case 'enemies':
        case 'abandoned':
        case 'meteoric':
        case 'planets':
        case 'combatzone':
        case 'epidemic':
        case 'sabotage':
        case 'powerCannons':
            dojo.style( 'current_card', 'display', 'block' );
            break;
        case 'powerEngines':
            dojo.style( 'current_card', 'display', 'block' );
            if ( this.isCurrentPlayerActive() ) {
                dojo.place( this.format_string( this.powEngInfoHtml, {
                                        curr: args.args.baseStr,
                                        max: args.args.maxStr
                                    } ), "info_box", "only" );
                dojo.style( 'info_box', 'display', 'block' );
                this.prepareContentChoice('cell');
                this.baseStrength = Number(args.args.baseStr);
                this.maxSelected = Number(args.args.maxSel);
                this.typeToSelect = "cell";
            }
            break;
        case 'exploreAbandoned':
            dojo.style( 'current_card', 'display', 'block' );
            if ( this.isCurrentPlayerActive() ) {
                this.wholeCrewWillLeave = args.args.wholeCrewWillLeave;
            }
            break;
        case 'chooseCrew':
            dojo.style( 'current_card', 'display', 'block' );
            if ( this.isCurrentPlayerActive() ) {
                dojo.place( this.format_string( this.chooseCrewInfoHtml, {
                                        curr: 0,
                                        needed: args.args.nbCrewMembers
                                    } ), "info_box", "only" );
                dojo.style( 'info_box', 'display', 'block' );
                this.prepareContentChoice( 'crew' ); // Connects and adds 'available' CSS class
                this.nbSelected = 0;
                this.maxSelected = Number(args.args.nbCrewMembers);
                this.typeToSelect = "crew";
            }
            break;
        case 'choosePlanet':
            dojo.style( 'current_card', 'display', 'block' );
            if ( this.isCurrentPlayerActive() ) {
                dojo.place( this.format_string( this.choosePlanetInfoHtml, {
                                        curr: 0,
                                    } ), "info_box", "only" );
                dojo.style( 'info_box', 'display', 'block' );
            }
            this.preparePlanetChoice(args.args.availIdx, args.args.unavailIdx);
            break;
        case 'powerShield':
        case 'loseGoods':
        case 'placeGoods':
        case 'takeReward':
            dojo.style( 'current_card', 'display', 'block' );
            break;

        case 'dummmy':
            break;
        }
    },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
    onLeavingState: function( stateName ) {
        console.log( 'Leaving state: '+stateName );

        switch( stateName ) {
        case 'buildPhase':
//            for( var i=1 ; i<=3 ; i++ )
//            {
//                this.removeTooltip( 'card_pile_'+i );// TODO either change this or destroy the face down piles
//            }
            this.removeTooltip( 'clickable_pile' ); // sure? It can always stay, for other rounds (not displayed outside of buildPhase anyway)
            this.removeTooltip( 'revealed_pile_wrap' ); // idem
            this.removeTooltip( 'turn' ); // idem  // does this work? turn is a class, not an id
            dojo.style( 'pile', 'display', 'none' ); // Should we still display it in takeOrderTiles phase?
            dojo.style( 'build_message', 'display', 'none' );
            this.disconnect( $('clickable_pile'), 'onclick' );
            this.disconnect( $('revealed_pile_wrap'), 'onclick' );
             // cancel timeout when leaving buildPhase
            clearTimeout( this.timeoutID );
            dojo.disconnect( this.sandTimerHandle );
            dojo.removeClass( 'sandTimer', 'clickable' );
            $('sandTimer').innerHTML = 0;
            if ( this.isCurrentPlayerActive() ) {
            // because it's already done if this player has already taken an order tile
                dojo.query('.available').forEach(
                    dojo.hitch( this, function( node ) {
                        // disconnect all squares and tiles
                        this.disconnect( node, 'onclick' );
                        dojo.removeClass( node, 'available' );
                        } ) );
            }
            break;
        case 'repairShips':
            // Temp, will be done in notif when repair action is implemented
            dojo.query('.ship_part_nb, .tile_error').forEach(dojo.destroy);
            break;
        case 'exploreAbandoned':
            this.wholeCrewWillLeave = false;
            break;
        case 'powerEngines':
        case 'chooseCrew':
            if ( this.isCurrentPlayerActive() ) {
                this.nbSelected = this.maxSelected = this.typeToSelect = this.baseStrength = null;
                dojo.style( 'info_box', 'display', 'none' );
                dojo.empty( 'info_box' );
                dojo.query('.content', 'my_ship').forEach(
                          dojo.hitch( this, function( node ) {
                              this.disconnect( node, 'onclick' );
                              dojo.removeClass( node, 'available');
                              dojo.removeClass( node, 'selected');
                              } ) );
            }
            break;
        case 'dummmy':
            break;
        }
    },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
    onUpdateActionButtons: function( stateName, args ) {
      console.log( 'onUpdateActionButtons: '+stateName );

      if( this.isCurrentPlayerActive() ) {
        switch( stateName ) {
        case 'waitForPlayers' :
            this.addActionButton( 'button_ready', _("I'm ready!"), 'onReady' );
            break;
        case 'placeCrew' :
            this.addActionButton( 'button_crewPlDone', _('Validate'), 'onCrewPlacementDone' );
            this.addActionButton( 'button_undoCrewPl', _('Undo'), 'onUndoCrewPlacement' );
            break;
        case 'repairShips' :
            this.addActionButton( 'button_finishRepairs', _('Validate repairs'),
                                    'onFinishRepairs' );
            break;
        case 'powerEngines' :
            this.addActionButton( 'button_contentChoice', _('Validate'),
                                    'onValidateContentChoice' );
            break;
          case 'exploreAbandoned' :
            this.addActionButton( 'button_explore_1', _('Yes'), 'onExploreChoice' );
            this.addActionButton( 'button_explore_0', _('No'), 'onExploreChoice' );
            break;
        case 'chooseCrew' :
            this.addActionButton( 'button_cancelExplore', _('Cancel'), 'onCancelExplore' );
            this.addActionButton( 'button_contentChoice', _('Validate'),
                                    'onValidateContentChoice' );
            break;
        case 'notImpl' :
            this.addActionButton( 'button_nextCard', _('Go on'), 'onGoOn' );
            break;
        case 'choosePlanet' :
            this.addActionButton('button_choosePlanet',_('Choose Planet'), 'onConfirmPlanet');
            this.addActionButton('button_passChoosePlanet',_('Pass'), 'onPassChoosePlanet'); 
            break;
        }
      }
    },

        ///////////////////////////////////////////////////
        //// Utility methods

        /*

            Here, you can define some utility methods that you can use everywhere in your javascript
            script.

        */

    getPart: function( word, i ) {
        var arr = word.split('_');
        return arr[i];
    },

    cardBg: function( cardId ) {
        var x = (cardId%20) * -165;
        var y = ( Math.floor(cardId/20) ) * -253;
        return { x: x, y: y };
    },

    updateTimer: function() {
        $('sandTimer').innerHTML = this.timeLeft;
        if ( this.timeLeft > 0 ) {
            this.timeLeft--;
            this.timeoutID = setTimeout(dojo.hitch(this, 'updateTimer'), 1000);
        }
        else {
            if ( this.gamedatas.gamestate.name == 'buildPhase' ) {
                if ( this.timerPlace === 0 )
                    // Inform the server that the time is finished, because it doesn't have a timer
                    // to trigger instructions by itself, but it will check if the time is correct.
                    this.ajaxAction( 'timeFinished', {} );
                else {
                    this.sandTimerHandle = dojo.connect( $('sandTimer'),
                                                        'onclick', this, 'onFlipTimer' );
                    dojo.addClass( 'sandTimer', 'clickable' ); // The sandTimer div always have
                    // the cursor: pointer property, because we want players to see (even
                    // when it's not the moment yet, i.e. when the timer is not finished) that
                    // it's the place to click when they want to flip the timer. So the clickable
                    // class doesn't change anything right now, but it may be used to change the
                    // display somehow (e.g. display an arrow image) to inform players that it is
                    // NOW possible to click here to flip the timer
                }
            }
        }
    },

    ajaxAction : function(action, args, func, err) {
        console.log("ajax action " + action);
        delete args.action;
        if (typeof func == "undefined" || func == null) {
            func = function(result) { };
        }

        if (typeof err == "undefined") {
                err = function(iserr) { };
        }

        var name = this.game_name;
//        if (this.checkAction(action)) {
            args.lock = true;
            this.ajaxcall("/" + name + "/" + name + "/" + action + ".html", args,
            this, func, err);
//        }
    },

    genericPickTile: function( evt, action ) {
        dojo.stopEvent( evt );

        if( this.current_tile !== null )
        {
            this.showMessage( _("You already have a tile in hand. Before picking a new one, "+
                "you must place this one on your ship or with the revealed tiles (if "+
                "allowed),  or set it aside."), "error" );
            return false;
        }
        if( !this.checkAction( action ) )
        // { return false; } // This player can't do this action now
          { console.log( 'checkAction genericPickTile failed' ); return false; } // temp

        if ( !this.isTouchDevice )
            { this.followMouse( evt ); }
        return true;
    },

    placeTileInHand: function( tileId ) {
        var tileDivId = 'tile_'+tileId;
        if ( this.undoPossible ) {
            this.disconnect( $(this.undoPossible), 'onclick' );
            dojo.removeClass( this.undoPossible, 'available' );
            this.undoPossible = null;
        }
        this.current_tile = tileId;
        this.dir = "0";
        if ( typeof g_replayFrom === 'undefined' && !this.isTouchDevice ) {
            dojo.place( tileDivId, 'in_hand_div' );
            dojo.style( tileDivId, { left: (this.tempMousePosX+1)+'px', 
                                      top: (this.tempMousePosY+1)+'px', } );
        }
        // slide to in_hand_div when replay/touch device? In this case dojo.place( tileDivId, 'in_hand_div' ) must be executed only in the if block above, not before this slideToDomNode
        else {
             this.slideToDomNode(tileDivId, 'in_hand_div');
        }
    },

    rotate: function( id, angle ) {
        var transform;
        dojo.forEach(
            ['transform', 'WebkitTransform', 'msTransform',
                'MozTransform', 'OTransform'],
            function (name) {
                if (typeof dojo.body().style[name] != 'undefined') {
                    transform = name;
                }
            });
        dojo.style( id, transform, 'rotate('+angle+'deg)' );
    },

    followMouse: function ( evt ) {
        console.log( '###### followMouse' );
        if ( evt !== null )
            this.setPickedTileOrTempPos ( evt );

        this.followMouseHandle = dojo.connect( 
                        document,
                        'onmousemove',
                        this, 
                        dojo.hitch( this, function( evt ) { this.setPickedTileOrTempPos ( evt );
                            } )
                        // equivalent to dojo.hitch( this, this.setPickedTileOrTempPos ( evt ) ) ?
                        );
    },

    setPickedTileOrTempPos: function ( evt ) {
        if ( this.current_tile === null ) {
            this.tempMousePosX = evt.clientX;
            this.tempMousePosY = evt.clientY;
        }
        else {
            dojo.style( 'tile_'+this.current_tile,
                    { left: (evt.clientX + 1)+'px',
                        top: (evt.clientY + 1)+'px' } );
        }
    },

    positionInRevealedPile: function ( tileDivId, space, slideFlag ) {
        var revSpaceDivId = 'rev_space_'+space;
        // Only 30 spaces are placed in the revealed pile at the beginning, so if we want
        // to place a tile on a space that doesn't exist, we must first create it
        if ( space > 29 && $(revSpaceDivId) === null ) {
            dojo.place( this.format_block( 'jstpl_rev_space', { i:space } ), 'revealed_pile' );
        }
        if (slideFlag) {
            this.slideToDomNode( tileDivId, revSpaceDivId );
        }
        else {
            dojo.place( tileDivId, revSpaceDivId );
            dojo.style( tileDivId, { top: '', left: '' } );
        }
        
        if ( this.isCurrentPlayerActive() ) {
            this.connect( $(tileDivId), 'onclick', 'onPickRevealed' );
            dojo.addClass( tileDivId, 'available' );
        }
    },

    placePlBoardItem: function( type, plId ) {
        dojo.place( this.format_block( 'jstpl_plBoardItem', {
                        type: type,
                        plId: plId,
                    } ), 'player_board_'+plId );
        // add a tooltip that says "min/max"? here?
    },

    setupRevealedCards: function( cards ) {
        for( var id in cards ) {
          //var thisCard = cards[i][id];
          var cardBg = this.cardBg( cards[id].id );
          dojo.place ( this.format_block( 'jstpl_card', {
                            id: id, // useless?
                            x: cardBg.x,
                            y: cardBg.y
                    } ), 'cards_reveal_'+cards[id].pile );
        }
    },

    setupShip: function( shipClass, player_id ) {
        var shipValues = this.ships[shipClass];
        var yMin = shipValues.yMinIndex;
        var yMax = shipValues.yMaxIndex;
        var cssClasses = '';
        if ( player_id == this.player_id ) {
            var shipDivId = 'my_ship';
            if ( ( this.gamedatas.gamestate.name == "buildPhase" 
                    && this.isCurrentPlayerActive() )
                || this.gamedatas.gamestate.name == "prepareRound" // possible?
                || this.gamedatas.gamestate.name == "waitForPlayers" ) {
                cssClasses += ' available';
            }
            // in the case of a page reload, setupPlacedTiles will be called by gamedatas after
            // setupShip, and if tiles are placed on these squares, available class will be removed
        }
        else {
            var shipDivId = 'ship_'+player_id;
        }
        dojo.addClass( shipDivId, 'ship_class_'+shipClass );
        // Add the slot where the order tile will be slided to when taken
        dojo.place( this.format_block( 'jstpl_ord_tile_slot', { id: player_id } ), shipDivId );

        for( var x=3; x<=11 ; x++ )
          for( var y=yMin; y<=yMax ; y++ ) {
            if (this.ships[shipClass][y][x-3] == 1) { // x-3 because we start ship coordinates
                                                    // for x at 3 in this.ships definition
           
                var leftPos = shipValues.leftPosRef+50*(x-3);
                if ( shipClass == 'IIIa' )
                    //var topPos = 411-50*(y-3); // For the moment I switch back to normal coordinates (not upside down just for IIIa).
                                            // I'll ask CGE if it's important to keep upside down coordinates for this ship, but I doubt
                    var topPos = 11+50*(y-3);
                else
                    var topPos = 11+50*(y-4); // may be different for other ship classes
                dojo.place( this.format_block( 'jstpl_square', {
                            plId: player_id,
                            x: x,
                            y: y,
                            left: leftPos,
                            top: topPos,
                            cssClasses: cssClasses
                    } ), shipDivId );
            }
          }
        // Add the two discard squares
        for( var x=-1; x>=-2 ; x-- ) {
            dojo.place( this.format_block( 'jstpl_square', {
                            plId: player_id,
                            x: x,
                            y: 'discard',
                            // Add 50px to left for the 2nd square (2nd square: x=-2)
                            left: shipValues.leftDiscard + ( (x == -2) ? 50 : 0 ),
                            top: shipValues.topDiscard,
                            cssClasses: cssClasses+' discard'
                        } ), shipDivId );
        }
    },

    setupPlacedTiles: function( tiles, bWaitBuildOrTakeOrderPhase ) {
      var nbTilesInSq1 = {};
      var nbTilesInSq2 = {};
      for( var id in tiles ) {
          var tile = tiles[id];
          var leftStr = topStr = ""; // used to display a sort of stack
                              // of tiles in discard when multiple tiles
          if ( tile.x < 0 ) {
            tile.y = 'discard';
            if ( bWaitBuildOrTakeOrderPhase ) {
              var z_index = 1;
            }
            else {
              var plId = tile.player;
              if ( typeof nbTilesInSq1[ plId ] == "undefined" ) {
                nbTilesInSq1[ plId ] = dojo.query( ".tile", "square_"+plId+"_-1_discard" ).length;
                nbTilesInSq2[ plId ] = dojo.query( ".tile", "square_"+plId+"_-2_discard" ).length;
              }
              if ( nbTilesInSq1[ plId ] <= nbTilesInSq2 [ plId ]) {
                tile.x = "-1";
                var z_index = ++nbTilesInSq1[ plId ];
              }
              else {
                tile.x = "-2";
                var z_index = ++nbTilesInSq2[ plId ];
              }
              var offset = ( z_index > 10 ) ? 20 : (z_index-1)*2;
              var leftStr = offset+'px'; // used to display a stack
                              // of tiles in discard when multiple tiles
              var topStr = "-"+offset+'px';
            }
          }
          else {
            var z_index = '';
          }
          var squareDivId = 'square_'+tile.player+'_'+tile.x+'_'+tile.y;

          this.attachToNewParent( 'tile_'+id, squareDivId );
          dojo.style( 'tile_'+id, { 'left': leftStr,
                                    'top': topStr,
                                    'z-index': z_index,  
              } );
          this.rotate( 'tile_'+id, tile.o );
          if ( typeof tile.placeOverlay !== "undefined" ) {
              // This tile is a cabin on a ship (not in discard), and we're not in a
              // state without overlay tiles, so we place an overlay tile on it
              // (used to display content that must NOT rotate with the tile)
              dojo.place( this.format_block( 'jstpl_overlay_tile', { i: id } ), squareDivId );
          }
          dojo.removeClass( squareDivId, 'available' );
      }
    },

    placeHiddenTile: function () {
        dojo.place( this.format_block( 'jstpl_hidden_tile',
                            { left: 10 + Math.floor(Math.random()*90),
                              top: Math.floor(Math.random()*55),
                              deg: Math.floor(Math.random()*90), } ),
                    'basic_pile', "first" );
    },

    placeContent: function ( tileContent )
    {
        var divId = tileContent.content_id;
        var tileId = tileContent.tile_id;
        var ctType = tileContent.content_type;
        var ctSubtype = tileContent.content_subtype;
        //var error = false;
        //var alien = false;
        var rotWithTile = false;

        switch( ctSubtype )
        {
          case 'cell':
            rotWithTile = true;
            var classes = 'cell';
            break;
          case 'human':
            var classes = 'crew human';
            break;
          case 'purple':
          case 'brown':
            //alien = true;
            var classes = 'crew alien '+ctSubtype;
            break;
          case 'ask_purple':
          case 'ask_brown':
            // This tile is special: we must place humans and alien(s) icons,
            // to show that the owner of this tile will have to make a choice
            var classes = 'crew alien_choice '+this.getPart( ctSubtype, 1); // color
            break;
          case 'ask_human':
            var classes = 'crew human_choice';
            break;
          case 'red':
          case 'yellow':
          case 'green':
          case 'blue':
            rotWithTile = true;
            var classes = 'goods '+ctSubtype;
            break;

          default:
            this.showMessage( "Error: unrecognized content type: "+
                        ctSubtype+" "+this.plReportBug, "error" );
            return;
        }

        var destDivId = ( rotWithTile ) ? 'tile_'+tileId : 'overlaytile_'+tileId;
        // position (given by CSS, eg. class p2on3) will depend on the
        // tile (eg. 2 cells batteries vs 3 cells batteries)
        //var posClass = ( alien ) ? 'p1on1' : 'p'+tileContent.place+'on'+tileContent.capacity;
        classes += ' p'+tileContent.place+'on'+tileContent.capacity;
        dojo.place( this.format_block( 'jstpl_content', {
                        content_id: divId,
                        classes: classes,
                    } ), destDivId );
    },

    connectAlienChoices: function() {
        dojo.query( '.alien_choice', 'my_ship' ).forEach(
                dojo.hitch( this, function( node ) {
                    this.connect( node, 'onclick', 'onChooseAlien');
                    dojo.addClass( node, 'available' );
                  } ) );
    },

    prepareContentChoice: function( type ) {
        dojo.query('.'+type, 'my_ship').forEach(
            dojo.hitch( this, function( node ) {
                this.connect( node, 'onclick', 'onSelectContent');
                dojo.addClass( node, 'available');
                } ) );
    },

    preparePlanetChoice: function(avail, unavail) {
        for (var i in avail ) {
            idx = avail[i];
            dojo.place( this.format_block('jstpl_circle', {
                idx: idx, top: 5+idx*47, classes: "planet available"
            }), 'current_card');
            this.addTooltip('planet_'+idx, '', _('Click here to select this planet.'));
            this.connect( $('planet_'+idx), 'onclick', 'onChoosePlanet');
        }
        for (var i in unavail ) {
            idx = unavail[i];
            dojo.place( this.format_block('jstpl_circle', {
                idx: idx, top: 5+idx*47, classes: "planet unavailable"
            }), 'current_card');
        }
    },

    updateInfoBox: function() {
        switch ( this.gamedatas.gamestate.name ) {
          case 'powerEngines':
            var currentStrength = this.baseStrength + this.nbSelected*2;
            console.log('base strength: ', this.baseStrength);
            console.log('current strength: ', currentStrength);
            // dojo.query is temporary, I'll use something more reliable when
            // the changes in content table are done.
            if ( this.baseStrength==0 && this.nbSelected>0 && dojo.query('.brown','my_ship').length ) {
                // baseStrength == 0 means that the alien bonus (if present) is not counted
                // in baseStrength (because no simple engine/cannon), so we must add it if
                // at least one cell is selected
                currentStrength += 2;
            }
            $("curr_str").innerHTML = currentStrength;
            break;
          case 'chooseCrew':
            $("curr_sel").innerHTML = this.nbSelected;
            break;
        }
    },

    attachToNewParentNoDestroy : function(mobile, new_parent) {
        if (mobile === null) {
            console.error("attachToNewParent: mobile obj is null");
            return;
        }
        if (new_parent === null) {
            console.error("attachToNewParent: new_parent is null");
            return;
        }
        if (typeof mobile == "string") {
            mobile = $(mobile);
        }
        if (typeof new_parent == "string") {
            new_parent = $(new_parent);
        }

        var src = dojo.position(mobile);
        dojo.style( mobile, "visibility", "hidden" );
        dojo.place(mobile, new_parent, "last");
        var tgt = dojo.position(mobile);
        var box = dojo.marginBox(mobile);
        var cbox = dojo.contentBox(mobile);
        var left = box.l + src.x - tgt.x;
        var top = box.t + src.y - tgt.y;
        dojo.style(mobile, "top", top + "px");
        dojo.style(mobile, "left", left + "px");
        dojo.style( mobile, "position", "absolute" );
        box.l += box.w-cbox.w;
        box.t += box.h-cbox.h;
        return box;
    },

    slideToDomNode: function( mobile, newParent, duration, delay, stylesOnEnd ) {
        console.log("Entering slideToDomNode");
        stylesOnEnd = (typeof stylesOnEnd !== "undefined") ? stylesOnEnd : {};
        this.attachToNewParentNoDestroy( mobile, newParent );
        dojo.style( mobile, "visibility", "visible" );
        dojo.style( mobile, "z-index", 1);
        var allStyles = { top: "", left: "", zIndex: "", position: "", visibility: "" };
        // If we need custom styles at the end of our anim, they're passed in stylesOnEnd
        // arg. We need to fill this array so that dojo.style at the end of our anim
        // unsets properties that don't need to be set to a special value.
        for ( var key in allStyles ) {
            if ( typeof stylesOnEnd[key] == "undefined" )
                stylesOnEnd[key] = "";
        }
        var anim = this.slideToObject( mobile, newParent, duration, delay ); 
        dojo.connect(anim, "onEnd", function(mobile) {
            dojo.style( mobile, stylesOnEnd );
        });
        anim.play();
    },


        ///////////////////////////////////////////////////
        //// Player's action

        /*

            Here, you are defining methods to handle player's action (ex: results of mouse click on
            game objects).

            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server

        */

    onCloseBuildMessage: function() {
        dojo.style('build_message', 'display', 'none');
        this.noBuildMessage = true;
    },

    onReady: function() {
        if ( this.checkAction( 'ImReady' ) )
            this.ajaxAction( 'ImReady', {} );
    },

    onDropTile: function( evt ) {
        console.log( 'onDropTile' );
        dojo.stopEvent( evt );

        if( ! this.checkAction( 'dropTile' ) // Is this useful?
            || this.current_tile === null ) {
            return;
        }
        if ( this.currentTileWasAside ) {
            this.showMessage( _("This tile was set aside before, so you can't place it "+
                    "here. Place it on your ship or set it aside again."), "error" );
            return;
        }
        this.ajaxAction( 'dropTile', { tile_id: this.current_tile } );
    },

    onClickPile: function( evt ) {
        console.log( 'onClickPile' );
        if ( !this.genericPickTile( evt, 'pickTile' ) )
            return;
        this.ajaxAction( 'pickTile', { } );
    },

    onPickRevealed: function( evt ) {
        console.log( 'onPickRevealed' );
        if ( !this.genericPickTile( evt, 'pickRevealed' ) )
            return;
        var id = this.getPart( evt.currentTarget.id, 1 );
        this.ajaxAction( 'pickRevealed', { tile_id: id } );
//        this.ajaxAction( 'pickRevealed', { tile_id: id }, null, 
//                            function(){ dojo.disconnect(this.followMouseHandle); } );
//          This disconnects even if the ajaxCall is successful, why?
    },

    onPickAside: function( evt ) {
        console.log( 'onPickAside' );
        if ( !this.genericPickTile( evt, 'pickAside' ) )
            return;
        var id = this.getPart( evt.currentTarget.id, 1 );
        this.ajaxAction( 'pickAside', { tile_id: id } );
    },

    onPickLastPlaced: function( evt ) {
        console.log( 'onPickLastPlaced' );
        if ( !this.genericPickTile( evt, 'pickLastPlaced' ) )
            return;
        var id = this.getPart( evt.currentTarget.id, 1 );
        this.ajaxAction( 'pickLastPlaced', { tile_id: id } );
    },

    onTurn: function( evt ) {
        console.log( 'onTurn' );
        dojo.stopEvent( evt );
        if( this.current_tile === null) {
            return;
        }
        var dir = evt.currentTarget.id.split('_');
        dir = dir[1];
        if ( dir == 'left' )
            this.dir = (this.dir + 270) % 360;
        else if ( dir == 'right' )
            this.dir = (this.dir + 90) % 360;
        else {
            this.showMessage( "onTurn: bad value for dir: "+dir, "error" );
            return;
        }
        this.rotate( 'tile_'+this.current_tile, this.dir );
    },

    onPlaceTile: function( evt ) {
        console.log( 'onPlaceTile' );
        dojo.stopEvent( evt );

        if( this.current_tile === null 
                || !this.checkAction('placeTile') )
        //    return;
          { console.log( 'checkAction onPlaceTile failed' ); return; } // temp

        var squareDiv = evt.currentTarget.id;
        var square = squareDiv.split('_');
        var tileId = this.current_tile;
        var x = square[2]; // square[2] is '-1' or '-2' for tiles that are set aside
        var y = square[3]; // square[3] is 'discard' for tiles that are set aside
        var discard = 0;

        if ( y === 'discard' ) {
            var o = "0"; // This tile was placed in discard
            discard = 1;
            y = 0;
        }
        else
            var o = this.dir;

        this.ajaxAction( 'placeTile', { player_id: this.player_id,
                                        component_id: tileId,
                                        x: x,
                                        y: y,
                                        o: o,
                                        discard: discard } );
    },

    onLookPile: function( evt ) {
        console.log ( 'onLookPile' ); // temp
        var id = this.getPart ( evt.currentTarget.id, 2 );
        if ( !this.checkAction( 'lookCards' ) ) {
            this.showMessage( _("You can look at a pile only when you are "+
                            "still building your ship."), "error" );
        }
        else if ( this.atLeast1Tile !== 1 ) {
            this.showMessage( _("You can look at a pile only when you have added "+
                            "at least one component to your ship."), "error" );
        }
        else {
            dojo.style( 'cards_reveal_'+id, 'display', 'block' );
            dojo.style( 'cards_reveal_wrap', 'display', 'block' );
        }
    },

    onBanishPile: function( evt ) {
        if ( evt !== null )
            dojo.stopEvent( evt );
        dojo.style( 'cards_reveal_wrap', 'display', 'none' );
        dojo.query( '.cards_reveal' ).style( 'display', 'none' );
    },

    onFlipTimer: function( evt ) {
        console.log( 'onFlipTimer' );
        dojo.stopEvent( evt );
        if ( this.isCurrentPlayerActive() && this.timerPlace == 1 )
            this.showMessage( _("You can't flip the timer on the last space now. You "+
                    "must finish your ship first (by taking an order tile)"), 'error' );
        else
            this.ajaxAction( 'flipTimer', { timerPlace: this.timerPlace } );
    },

    onFinishShip: function( evt ) {
        console.log( 'onFinishShip' );
        dojo.stopEvent( evt );
        if ( ! this.checkAction( 'finishShip' ) ) {
            return; // This player can't do this action now
        }
        if ( this.current_tile !== null ) {
            this.showMessage( _("You have a tile in hand. Before taking an order tile, you must "+
                "place your tile in hand on your ship (or discard zone) or with the revealed tiles."), "error" );
            return;
        }
        var orderTile = this.getPart( evt.currentTarget.id, 2 );
        this.ajaxAction( 'finishShip', { player_id: this.player_id, orderTile: orderTile } );
    },

    onFinishRepairs: function( evt ) {
        console.log( 'onFinishRepairs' );
        dojo.stopEvent( evt );
        if ( ! this.checkAction( 'finishRepairs' ) ) {
            return; // This player can't do this action now
        }
        this.ajaxAction( 'finishRepairs', {} );
    },

    onChooseAlien: function( evt ) {
        console.log( 'onChooseAlien' );
        dojo.stopEvent( evt );
        var overlayTileDiv = evt.currentTarget.parentElement;
        if ( dojo.hasClass( evt.currentTarget, 'brown' ) )
            var color = 'brown';
        if ( dojo.hasClass( evt.currentTarget, 'purple' ) )
            var color = 'purple';
        if ( typeof color == 'undefined' ) {
            this.showMessage ( "Error in onChooseAlien(), color is undefined. "
                                  +this.plReportBug, 'error' );
            return;
        }

        // Change classes and disconnect onChooseAlien for aliens on
        // this tile and other alien(s) of the same color if any
        // First we gather all alien_choice divs we need to process in a single dojo NodeList:
        var aliensToProcess = dojo.query( '.alien_choice', overlayTileDiv ). // select all
                // aliens in this tile. The end of line dot is for chaining with the following
        concat( dojo.query( '.alien_choice.'+color, 'my_ship' ) ); // select all aliens of the
                                                                    // same color in this ship
        // Then we apply changes
        dojo.forEach( aliensToProcess, dojo.hitch( this, function( node ) {
            dojo.removeClass( node, 'available' );
            this.disconnect( node, 'onclick' ); // Est-ce que je laisse connecté pour pouvoir afficher un message d'erreur "2 aliens de même couleur impossible, déselectionnez l'autre" ? Ou pour permettre de sélectionner un autre sans avoir à déselectionner ?
            if ( node === evt.currentTarget )
                dojo.addClass( node, 'chosen' );
            else
                dojo.addClass( node, 'unavailable' );
        } ) );
    },

    onChoosePlanet: function(evt) {
        console.log('onChoosePlanet', evt, this);
        dojo.stopEvent(evt);
    },

    onCrewPlacementDone: function( evt ) {
      console.log( 'onCrewPlacementDone' );
      dojo.stopEvent( evt );
      if ( this.checkAction( 'crewPlacementDone' ) ) {
        var alienChoices = [];

        dojo.query('.alien_choice.chosen').forEach(
          dojo.hitch( this, function( node ) {
            alienChoices.push( this.getPart( node.id, 1 ) );
          } ) );

        var nbAliens = alienChoices.length;
        if ( nbAliens > 2 ) { // must be changed to 3 if cyan aliens are implemented
            this.showMessage( "Error: too many aliens chosen. "+this.plReportBug, 'error' );
            return;
        }
        this.ajaxAction( 'crewPlacementDone', { alienChoices: alienChoices.join() } );
      }
    },

    onUndoCrewPlacement: function( evt ) {
        console.log( 'onUndoCrewPlacement' );
        dojo.stopEvent( evt );
        dojo.query('.unavailable').removeClass('unavailable');
        dojo.query('.chosen').removeClass('chosen');
        this.connectAlienChoices();
    },

    onSelectContent: function( evt ) {
        console.log( 'onSelectContent' );
        dojo.stopEvent( evt );
        if ( this.nbSelected === this.maxSelected )
            return;
        var contId = evt.currentTarget.id;
        dojo.replaceClass( contId, 'selected', 'available');
        this.disconnect( $(contId), 'onclick' );
        this.connect( $(contId), 'onclick', 'onUnselectContent');
        this.nbSelected++;
        console.log('nbSelected after ++: ', this.nbSelected);
        if ( this.nbSelected === this.maxSelected ) {
            dojo.query('.available.content').removeClass('available');
            // no need to disconnect all now, they'll be disconnected in notif
        }
        this.updateInfoBox(); // current strength in powerEngines, nbSelected in chooseCrew, etc.
    },

    onUnselectContent: function( evt ) {
        console.log( 'onUnselectContent' );
        dojo.stopEvent( evt );
        var contId = evt.currentTarget.id;
        dojo.removeClass( contId, 'selected');
        this.disconnect( $(contId), 'onclick' );
        this.connect( $(contId), 'onclick', 'onSelectContent');
        this.nbSelected--;
        if ( this.nbSelected === (this.maxSelected-1) ) {
            // all content divs were previously marked as unselectable in onSelectContent(),
            // so we add available class back to them
            dojo.query('.'+this.typeToSelect+':not(.selected)').addClass('available');
        }
        else {
            dojo.addClass( contId, 'available' );
        }
        this.updateInfoBox(); // current strength in powerEngines, nbSelected in chooseCrew, etc.
    },

    onValidateContentChoice: function( evt ) {
        console.log( 'onValidateContentChoice' );
        dojo.stopEvent( evt );
        if( this.checkAction( 'contentChoice' ) ) {
            var contArray = [];
            dojo.query( '.selected', 'my_ship' ).forEach( 
                    dojo.hitch( this, function( node ) {
                        contArray.push( this.getPart( node.id, 1) );
                    } ) );
            switch ( this.typeToSelect ) {
              case 'cell':
                var action = 'battChoice';
              break;
              case 'crew':
                if ( this.nbSelected !== this.maxSelected ) {
                    this.showMessage( _("Wrong number of crew members selected"), 'error' );
                    return;
                }
                var action = 'crewChoice';
              break;
            }
            this.ajaxAction( 'contentChoice', { contList: contArray.join(),
                                                actionName: action } );
        }
    },

    onExploreChoice: function( evt ) {
        console.log( 'onExploreChoice' );
        if ( !this.checkAction( 'exploreChoice' ) )
            return;
        var choice = this.getPart( evt.currentTarget.id, 2 );
        if ( this.wholeCrewWillLeave && choice == 1 ) {
            // Or a confirm button in the action bar instead of a dialog
            console.log('confirm ok?');
            this.confirmationDialog( _('Are you sure you want to lose your whole crew? '+
                                      'You will have to give up for this flight.'),
                                dojo.hitch( this, function() {
                                    this.ajaxcall( '/galaxytrucker/galaxytrucker/exploreChoice.html',
                                                  { lock:true, explChoice: 1 },
                                                  this, function(result) {} );
                                } ) );
        }
        else
            this.ajaxAction( 'exploreChoice', { explChoice: choice } );
    },

    onCancelExplore: function( evt ) {
        console.log( 'onCancelExplore' );
        dojo.stopEvent( evt );
        this.ajaxAction( 'cancelExplore', {} );
    },

    onConfirmPlanet: function (evt) {

    },

    onPassChoosePlanet: function (evt) {

    },

    onGoOn: function( evt ) {
        console.log( 'onGoOn' );
        dojo.stopEvent( evt );
        this.ajaxAction( 'goOn', {} );
    },

    onTestNextRound: function( evt ) {
        console.log( 'onTestNextRound' );
        dojo.stopEvent( evt );
        this.ajaxAction( 'tempTestNextRound', {} );
    },


        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:

            In this method, you associate each of your game notifications with your local method to handle it.

            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your galaxytrucker.game.php file.

        */
    setupNotifications: function() {
        console.log( 'notifications subscriptions setup' );

        dojo.subscribe( 'consoleLog', this, "notif_consoleLog");
        dojo.subscribe( 'onlyLogMessage', this, "notif_onlyLogMessage" );
        dojo.subscribe( 'replay_has_ended', this, "notif_GT_replay_has_ended" );
        dojo.subscribe( 'startTimer', this, "notif_startTimer" );
        dojo.subscribe( 'cardsPile', this, "notif_cardsPile" );
        dojo.subscribe( 'pickedTilePl', this, "notif_pickedTilePl" );
        dojo.subscribe( 'pickedTile', this, "notif_pickedTile" );
        dojo.subscribe( 'droppedTile', this, "notif_droppedTile" );
        dojo.subscribe( 'pickedRevealed', this, "notif_pickedRevealed" );
        dojo.subscribe( 'pickedAside', this, "notif_pickedAside" );
        dojo.subscribe( 'pickedLastPlaced', this, "notif_pickedLastPlaced" );
        dojo.subscribe( 'placedTile', this, "notif_placedTile" );
        dojo.subscribe( 'timerFlipped', this, "notif_timerFlipped" );
        dojo.subscribe( 'finishedShip', this, "notif_finishedShip" );
        dojo.subscribe( 'almostFinished', this, "notif_almostFinished" );
        dojo.subscribe( 'timeFinished', this, "notif_timeFinished" );
        // dojo.subscribe( 'confirmTimeFinished', this, "notif_confirmTimeFinished" );
        dojo.subscribe( 'loseComponent', this, "notif_loseComponent" );
        this.notifqueue.setSynchronous( 'loseComponent', 1500 );
        dojo.subscribe( 'updatePlBoardItems', this, "notif_updatePlBoardItems" );
        dojo.subscribe( 'buildingErrors', this, "notif_buildingErrors" );
        dojo.subscribe( 'placeShipMarker', this, "notif_placeShipMarker" );
        this.notifqueue.setSynchronous( 'placeShipMarker', 500 );
        dojo.subscribe( 'updateShipContent', this, "notif_updateShipContent" );
        dojo.subscribe( 'cardDrawn', this, "notif_cardDrawn" );
        this.notifqueue.setSynchronous( 'cardDrawn', 1000 );
        dojo.subscribe( 'moveShip', this, "notif_moveShip" );
        this.notifqueue.setSynchronous( 'moveShip', 800 );
        dojo.subscribe( 'loseContent', this, "notif_loseContent" );
        this.notifqueue.setSynchronous( 'loseContent', 1500 );
        dojo.subscribe( 'newRound', this, "notif_newRound" );

        // Example 1: standard notification handling
        // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );

        // Example 2: standard notification handling + tell the user interface to wait
        //            during 3 seconds after calling the method in order to let the players
        //            see what is happening in the game.
        // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
        // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
        //
    },

    notif_consoleLog: function(notif) {
        console.log("NOTIF: " + notif.args.msg);
    },

    notif_onlyLogMessage: function( ) {
    },

    notif_GT_replay_has_ended: function( ) {
        console.log( 'notif_GT_replay_has_ended' );
        //TODO ask server to send us timeLeft
        dojo.removeClass( 'ebd-body', 'replay_running');
        // If a tile is in hand, connect followMouse so that it follows the mouse
        if ( this.current_tile !== null && typeof g_replayFrom == "undefined"
            && !this.isTouchDevice ) {
            this.followMouse( null );
        }
    },

    notif_startTimer( ) {
        console.log( 'notif_startTimer' );
        if (  typeof g_replayFrom != "undefined"  ) { // If in Replay Mode, we don't display
                                    // the value of the timer and don't start it
            $('sandTimer').innerHTML = "N/A"; // So that the timer is visible even in replay mode
        }
        else {
            this.timeLeft = 90;
            this.updateTimer();
        }
    },

    notif_cardsPile: function( notif ) {
        console.log( 'notif_cardsPile' );
        this.setupRevealedCards( notif.args.cards );
        dojo.query( '.card_pile' ).addClass( 'clickable' );
    },

    notif_pickedTilePl: function( notif ) {
        console.log( 'notif_pickedTilePl' );
        var pickedTile = notif.args.pickedTile
        var tileDivId = 'tile_'+pickedTile;
        if ( pickedTile === null ) { // no tile left
            this.showMessage( "No more unrevealed tile.", 'info' );
            this.disconnect( $('clickable_pile'), 'onclick' );
        }
        else {
            this.placeTileInHand( pickedTile ); // This also disconnects last placed tile
            dojo.addClass( 'revealed_pile_wrap', 'clickable' );
            dojo.query( '.turn' ).addClass( 'clickable' );
        }
    },

    notif_pickedTile: function( notif ) {
        console.log( 'notif_pickedTile' );
        dojo.destroy( dojo.query('.hidden_tile').pop() );
        // Is this ok when there's no hidden tile left? (dojo.destroy(undefined))
    },

    notif_pickedRevealed: function( notif ) {
        console.log( 'notif_pickedRevealed' );
        console.log( notif );
        var tileId = notif.args.tile_id;
        var tileDivId = 'tile_'+tileId;

        dojo.removeClass( tileDivId, 'available' );
        this.disconnect( $(tileDivId), 'onclick' );

        if ( notif.args.player_id == this.player_id ) {
            this.placeTileInHand( tileId ); // This also disconnects last placed tile
            dojo.addClass( 'revealed_pile_wrap', 'clickable' );
            dojo.query( '.turn' ).addClass( 'clickable' );
        }
        else {
            this.slideTemporaryObject(
                '<div class="hidden_tile"></div>',
                'revealed_pile', tileDivId,
                'overall_player_board_'+notif.args.player_id );
            dojo.place( tileDivId, 'basic_pile' );
        }
    },

    notif_pickedAside: function( notif ) {
        console.log( 'notif_pickedAside' );
        console.log( notif );
        var tileId = notif.args.tile_id;
        var tileDivId = 'tile_'+tileId;

        if ( notif.args.player_id == this.player_id ) {
            this.disconnect( $(tileDivId), 'onclick' );
            dojo.removeClass( tileDivId, 'available' );
            this.currentTileWasAside = true;
            this.placeTileInHand( tileId ); // This also disconnects last placed tile
            var squareDivId = 'square_'+this.player_id+'_'+notif.args.discardSquare+'_discard' ;
            dojo.addClass( squareDivId, 'available' );
            this.connect( $(squareDivId), 'onclick', 'onPlaceTile');
            dojo.query( '.turn' ).addClass( 'clickable' );
        }
        else {
            dojo.place( tileDivId, 'basic_pile' );
        }
        dojo.style( tileDivId, 'z-index', '' );
    },

    notif_pickedLastPlaced: function( notif ) {
        console.log( 'notif_pickedLastPlaced' );
        console.log( notif );
        var tileId = notif.args.tile_id;
        var tileDivId = 'tile_'+tileId;

        if ( notif.args.player_id == this.player_id ) {
            dojo.removeClass( tileDivId, 'available' );
            this.placeTileInHand( tileId ); // This also disconnects last placed tile
            var squareDivId = 'square_'+this.player_id+'_'+notif.args.x+'_'+notif.args.y;
            dojo.addClass( squareDivId, 'available' );
            this.connect( $(squareDivId), 'onclick', 'onPlaceTile');
            dojo.query( '.turn' ).addClass( 'clickable' );
            if ( notif.args.aside )
                this.currentTileWasAside = true;
        }
        else {
            dojo.place( tileDivId, 'basic_pile' );
        }
        this.rotate( tileDivId, 0 );
    },

    notif_placedTile: function( notif ) {
        console.log( 'notif_placedTile' );
        console.log( notif );
        var squareDivId = 'square_'+notif.args.player_id+'_'+notif.args.x+'_'+notif.args.y;
        var tileId = notif.args.component_id;
        var tileDivId = 'tile_'+tileId;

        if ( notif.args.player_id == this.player_id ) {
            dojo.removeClass( squareDivId, 'available' );
            dojo.removeClass( 'revealed_pile_wrap', 'clickable' );
            dojo.query( '.turn' ).removeClass( 'clickable' );
            dojo.disconnect(this.followMouseHandle);
            this.disconnect( $(squareDivId), 'onclick' );
            this.current_tile = null;
            this.currentTileWasAside = false;
            dojo.addClass( tileDivId, 'available' ); // always? I think so

            if ( notif.args.y === "discard" ) {
                this.connect( $(tileDivId), 'onclick', 'onPickAside' );
            }
            else {
                this.connect( $(tileDivId), 'onclick', 'onPickLastPlaced' ); // So that
                                            // this player can take this tile back until
                                            // they grab another tile (rules)
                this.undoPossible = tileDivId; // So that we can deconnect it when
                                                // another tile is grabbed
                this.atLeast1Tile = 1; // A tile has been placed, so now 
                                        // this player can look at the cards
            }
            this.rotate( tileDivId, notif.args.o );
            this.slideToDomNode( tileDivId, squareDivId );
        }
        else {
            this.rotate( tileDivId, notif.args.o );
            dojo.place( tileDivId, squareDivId );
        }

        if ( notif.args.y === "discard" ) {
            dojo.style( tileDivId, 'z-index', 1 ); // can be only 1 in buildPhase, but
                    // need to be changed if ever we use this in other states
        }
    },

    notif_droppedTile: function( notif ) {
        console.log( 'notif_droppedTile' );
        console.log( notif );
        var tileDivId = 'tile_'+notif.args.tile_id;

        if ( notif.args.player_id == this.player_id ) {
            this.rotate( tileDivId, 0 );
            dojo.disconnect(this.followMouseHandle);
            this.current_tile = null;
            dojo.removeClass( 'revealed_pile_wrap', 'clickable' );
            dojo.query( '.turn' ).removeClass( 'clickable' );
        }
        else {
            this.placeOnObject( tileDivId, 'overall_player_board_'+notif.args.player_id );
        }
        this.positionInRevealedPile( tileDivId, notif.args.placeInRevealedPile, true );
    },

    notif_timerFlipped: function( notif ) {
        console.log( 'notif_timerFlipped' );
        console.log( notif );

        dojo.disconnect( this.sandTimerHandle );
        dojo.removeClass( 'sandTimer', 'clickable' );
        this.timerPlace = notif.args.timerPlace;
        dojo.place( 'sandTimer', 'timerPlace_'+this.timerPlace );
        if ( typeof g_replayFrom == "undefined" ) { // If in Replay Mode, we don't display the value of the timer and don't start it
            this.timeLeft = 90;
            this.updateTimer();
        }
    },

    // When a player has taken an order tile
    notif_finishedShip: function( notif ) {
        console.log( 'notif_finishedShip' );
        console.log( notif );

        if ( this.player_id == notif.args.player_id ) {
            this.undoPossible = null;
            var shipDivId = my_ship;
            dojo.style( 'build_message', 'display', 'none' );
            dojo.disconnect(this.followMouseHandle); // ?? Shouldn't be connected when taking an order tile
            this.disconnect( $('clickable_pile'), 'onclick' );
            dojo.removeClass( 'clickable_pile', 'clickable' );
            this.disconnect( $('revealed_pile_wrap'), 'onclick' );// Why?
            dojo.removeClass( 'revealed_pile_wrap', 'clickable' );
            this.disconnect( $('turn_left'), 'onclick' );
            this.disconnect( $('turn_right'), 'onclick' );
            dojo.query( '.turn, .card_pile' ).removeClass( 'clickable' );
            if ( this.gamedatas.gamestate.name == "buildPhase" ) { // because if in takeOrderTiles,
            // this is already done, so no need to query all available tiles/squares for nothing
                dojo.query('.available').forEach(
                    dojo.hitch( this, function( node ) {
                        // disconnect all squares and tiles
                        this.disconnect( node, 'onclick' );
                        dojo.removeClass( node, 'available' );
                        } ) );
            }

            dojo.query('.order_tile').forEach(
                dojo.hitch( this, function( node ) {
                    this.disconnect( node, 'onclick' );
                    } ) );
        }
        else
            var shipDivId = 'ship_'+notif.args.player_id;

        var orderTileDivId = 'order_tile_'+notif.args.orderTile;
        this.disconnect( $(orderTileDivId), 'onclick' ); // this doesn't seem to work, at least sometimes
        this.removeTooltip( orderTileDivId ); // Why doesn't this remove the tooltip?
        this.slideToDomNode( orderTileDivId, 'ordTileSlotOnShip_'+notif.args.player_id );
    },

    notif_almostFinished: function( notif ) {
        console.log( 'notif_almostFinished' );
        console.log( notif );
    },

    notif_timeFinished: function( notif ) {
        console.log( 'notif_timeFinished' );
        console.log( notif );
    },

    notif_loseComponent: function( notif ) {
        console.log( 'notif_loseComponent' );
        console.log( notif );
        var plId = notif.args.plId;
        var nbTilesInSq1 = dojo.query( ".tile", "square_"+plId+"_-1_discard" ).length;
        var nbTilesInSq2 = dojo.query( ".tile", "square_"+plId+"_-2_discard" ).length;
        var delay = 0;
        var interval = ( 1000 / notif.args.numbComp );

        for ( var i in notif.args.compToRemove ) {
            var tileId = notif.args.compToRemove[i];
            var tileDivId = 'tile_'+tileId;
            // TODO if we use the same notif during flight, remove content
            // and overlay tile before
            // See on which discard square these tiles must be placed
            if ( nbTilesInSq1 <= nbTilesInSq2 ) {
                var square = 1;
                var z_index = ++nbTilesInSq1;
            }
            else {
                var square = 2;
                var z_index = ++nbTilesInSq2;
            }
            this.rotate( tileDivId, 0 );
            var offset = ( z_index > 10 ) ? 20 : (z_index-1)*2;
            var stylesOnEnd = { 'left': offset+"px",
                                      'top': "-"+offset+"px",
                                      'zIndex': z_index,  
                };
            this.slideToDomNode( tileDivId, 
                                'square_'+notif.args.plId+'_-'+square+'_discard',
                                1000, delay, stylesOnEnd );
            delay += interval;
        }
    },

    notif_updatePlBoardItems: function( notif ) {
        console.log( 'notif_updatePlBoardItems' );
        console.log( notif ); // temp
        for ( var i in notif.args.items ) {
            var item = notif.args.items[i];
            $( item.type+"_"+notif.args.plId ).innerHTML = item.value;
        }
    },

    notif_buildingErrors: function( notif ) {
        console.log( 'notif_buildingErrors' );
        console.log( notif );
        
        for (var i in notif.args.errors) {
          var error = notif.args.errors[i];
          //dojo.addClass( 'tile_'+error.tileId, 'error'+error.side );
          dojo.place( this.format_block( 'jstpl_tile_error',
                  { side: error.side } ),
                  'square_'+error.plId+'_'+error.x+'_'+error.y);
        }
        for (var pl in notif.args.shipParts)
          for (var nb in notif.args.shipParts[pl]) {
            var part = notif.args.shipParts[pl][nb];
            for (var tileId in part) {
              var tile = part[tileId];
              dojo.place( this.format_block( 'jstpl_ship_part_nb',
                  { nb: nb } ),
                  'square_'+pl+'_'+tile.x+'_'+tile.y);
            }
          }
    },

    notif_placeShipMarker: function( notif ) {
        console.log( 'notif_placeShipMarker' );
        console.log( notif );

        var plId = notif.args.player_id;
        var shipPos = ( +(notif.args.plPos)+40 ) % 40;
        dojo.place( this.format_block( 'jstpl_ship_marker',
                            { plId: plId, color: notif.args.plColor } ),
                            'overall_player_board_'+plId );
        this.slideToDomNode( 'ship_marker_'+plId, 'flight_pos_'+shipPos );
    },

    notif_updateShipContent: function( notif )
    {
        console.log( 'notif_updateShipContent' );
        console.log( notif );
        if ( this.player_id == notif.args.player_id )
            var shipDivId = 'my_ship';
        else
            var shipDivId = 'ship_'+notif.args.player_id;

        if ( notif.args.gamestate == 'prepareFlight' ) {
            // In prepareFlight state, we place transparent overlay tiles over cabins,to
            // place content (crew) that mustn't rotate with the tile.
            for ( var i in notif.args.tiles_with_overlay ) {
                var tile = notif.args.tiles_with_overlay[i];
                var squareDivId = 'square_'+notif.args.player_id+'_'+tile['x']+'_'+tile['y'];
                dojo.place( this.format_block( 'jstpl_overlay_tile', { i: tile['id'] } ),
                            squareDivId );
            }
        }
        // First, if we are in placeCrew state, we destroy alien_choice and human_choice divs
        // that may be present
        if ( notif.args.gamestate == 'placeCrew' ) {
            dojo.query( '.alien_choice, .human_choice', shipDivId ).forEach(dojo.destroy);
        }

        for ( var i in notif.args.ship_content_update ) {
            this.placeContent( notif.args.ship_content_update[i] );
        }
    },

    notif_cardDrawn: function( notif ) {
        console.log( 'notif_cardDrawn' );
        console.log( notif );
        this.currentCard = notif.args.cardId;
        var cardBg = this.cardBg( notif.args.cardId );
        dojo.style( 'current_card', 'background-position', cardBg.x+'px '+cardBg.y+'px' );
    },

    notif_moveShip: function( notif ) {
        console.log( 'notif_moveShip' );
        console.log( notif );
        var shipPos = ( +(notif.args.newPlPos)+400 ) % 40;
        this.slideToDomNode( 'ship_marker_'+notif.args.player_id, 
                              'flight_pos_'+shipPos, 800 );
    },

    notif_loseContent: function( notif ) {
      console.log( 'notif_loseContent' );
      console.log( notif );
      if ( this.isCurrentPlayerActive() ) {
          dojo.query('.selected', 'my_ship').removeClass('selected');
      }
      for ( var i in notif.args.content ) {
        var cont = notif.args.content[i];
        dojo.style( cont.divId, 'z-index', '50' ); // Not working, certainly due to stacking context. TODO
        if ( cont.toCard ) {
            this.slideToObjectAndDestroy( cont.divId, "current_card", 500, i*200 );
        }
        else {
            switch ( cont.orient ) {
              case '90':
                var top = "-100";
                var left = "400";
                break;
              case '180':
                var top = "-400";
                var left = "-100";
                break;
              case '270':
                var top = "100";
                var left = "-400";
                break;
              case '0':
              default:
                var top = "400";
                var left = "100";
                break;
            }
            var anim = dojo.fx.combine([
                dojo.fx.slideTo({ node:cont.divId, left:left, top:top,
                                  units:"px", duration: 1700, delay:i*200 }),
                dojo.fadeOut({ node:cont.divId, duration: 1700, delay:i*200 })
            ]);
            dojo.connect( anim, "onEnd", function(){  dojo.destroy( cont.divId ); } );
            anim.play();
        }
      }
    },

    notif_newRound: function( notif ) {
        console.log( 'notif_newRound' );
        console.log( notif );

        if ( notif.args.flight !== 1 ) {
            // clean all elements from previous round that need to be cleaned
            console.log( 'cleaning elements from previous round' );
            dojo.query('.content').forEach(dojo.destroy);
            dojo.query('.tile').forEach(
                      dojo.hitch( this, function( node ) {
                            dojo.place( node.id, 'basic_pile' );
                            // rotate tiles back to 0 deg
                            this.rotate( node.id, '0' );
                            // TODO Should we set top and left inline style
                            // to "" now? (offset in discard)
                            } ) );
            dojo.query('.ship').forEach(dojo.empty);
            dojo.query('.hidden_tile').forEach(dojo.destroy);
            dojo.query('.ship_marker').forEach(dojo.destroy);
            dojo.style( 'current_card', 'display', 'none' );
            dojo.query('.rev_space.additional').forEach(dojo.destroy);
            dojo.query('.plBoardSpan').forEach(dojo.empty); // Maybe not all,
                                                // depending on what we add
            this.currentCard = null; // Here? In onEnteringState or onLeavingState? Is it important?
        }

        dojo.style( 'pile', 'display', 'block' );
        // display timer (it will be started in notif_startTimer at the beginning of buildPhase)
        this.timerPlace = notif.args.round;
        $('sandTimer').innerHTML = "90";
        dojo.place( 'sandTimer', 'timerPlace_'+this.timerPlace );
        dojo.style( 'sandTimer', 'display', 'inline' );

        for( var player_id in this.gamedatas.players ) {
            var player = this.gamedatas.players[player_id];
            // Setting up ships
            this.setupShip( notif.args.shipClass, player.id );
        }

        // Pile of face down tiles
        for( var i=0 ; i < notif.args.tilesLeft ; i++ )
            this.placeHiddenTile();

        // cards
        dojo.query('.card_pile').replaceClass( 'round_'+notif.args.round,
                                        'round_'+(notif.args.round-1) );
        // Warning: if ever a game variant that skips a round is implemented, we must
        // also remove 'round_'+(notif.args.round-2) in the line above, or use a
        // "previousRound" variable

        // placed tiles (starting components if any, none in class IIA)
        if ( notif.args.startingTiles )
            this.setupPlacedTiles( notif.args.startingTiles );

        // order tiles
        for ( var i=1; i<=notif.args.nbPlayers; i++ ) {
            dojo.place( this.format_block( 'jstpl_order_tile', { i: i } ) , 'order_tile_slot_'+i );
            this.addTooltip( 'order_tile_'+i, '', _('Click here to finish your ship.') );
            this.connect( $('order_tile_'+i), 'onclick', 'onFinishShip');
        }

        this.atLeast1Tile = 0;
    },

  });
});
