<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * GalaxyTrucker implementation : © <Your name here> <Your email address here>
  *
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  *
  * galaxytrucker.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );
require_once('modules/GT_DBCard.php');
require_once('modules/GT_DBPlayer.php');
require_once('modules/GT_PlayerBoard.php');
require_once('modules/GT_PlayerContent.php');
require_once('modules/GT_GameStates.php');

class GalaxyTrucker extends Table {
        function __construct( ) {


        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();self::initGameStateLabels( array(
                "flight" => 10,
                "round" => 11, // 'round' is only the round 'level', i.e. 1 for class I,
                            // 3 for class III or IIIa, etc., and is used to know which cards
                            // to add in the adventure cards deck
                            // May be different from 'flight' when using some variants like
                            // shorter or longer games
                "cardOrderInFlight" => 12,
                "timerStartTime" => 13,
                "timerPlace" => 14,
                "buildingStartTime" => 15, // (for stats)
                "currentCard" => 16,
                "overlayTilesPlaced" => 17, // used in GetAllDatas to know if the client
                                    // must place overlay tiles in case of a page reload
                "testGameState" => 99, // use a test scenario from GT_GameStates 

                // flight_variants is a game option (gameoptions.inc.php)
                // if gameoptions.inc.php changes, they must be reloaded through BGA control panel: https://boardgamearena.com/doc/Game_options_and_preferences:_gameoptions.inc.php
                "flight_variants" => 100,
        ) );

        }

  protected function getGameName( ) {
      // Used for translations and stuff. Please do not modify.
      return "galaxytrucker";
  }

    /*
        setupNewGame:

        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
  protected function setupNewGame( $players, $options = array() ) {
    // Set the colors of the players with HTML color code
    // The default below is red/green/blue/orange/brown
    // The number of colors defined here must correspond to the maximum number
    // of players allowed for the game
    $default_colors = array( "0000ff", "008000", "ffff00", "ff0000" );

    // Create players
    // Note: if you added some extra field on "player" table in the database
    // (dbmodel.sql), you can initialize it there.
    $sql = "INSERT INTO player (player_id, player_color, player_canal,".
            "player_name, player_avatar) VALUES ";
    $values = array();
    foreach( $players as $player_id => $player ) {
        $color = array_shift( $default_colors );
        $values[] = "('".$player_id."','$color','".$player['player_canal']."','".
                        addslashes( $player['player_name'] )."','".
                        addslashes( $player['player_avatar'] )."')";
    }
    $sql .= implode( ',', $values );
    self::DbQuery( $sql );
    self::reattributeColorsBasedOnPreferences( $players, array( "0000ff", "008000",
                                                                "ffff00", "ff0000" ) );
    self::reloadPlayersBasicInfos();
    $players = self::loadPlayersBasicInfos();

    /************ Start the game initialization *****/
    self::log("Initializing new game");

    // Init global values with their initial values
    //self::setGameStateInitialValue( 'my_first_global_variable', 0 );
    self::setGameStateInitialValue( 'flight', 1 );
    self::setGameStateInitialValue( 'cardOrderInFlight', 0 );
    self::setGameStateInitialValue( 'currentCard', null );
    self::setGameStateInitialValue( 'round', 1 ); // will be changed in stPrepareRound
                        // in case of a short flight variant that begins with a level 2 flight
    self::setGameStateInitialValue( 'timerStartTime', 0 );
    self::setGameStateInitialValue( 'timerPlace', -1 );
    self::setGameStateInitialValue( 'overlayTilesPlaced', 0 );

    // Init game statistics
    // (note: statistics used in this file must be defined in your stats.inc.php file)
    //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
    //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player stat (for all pl.)

    // setup the initial game situation

    $sql = "INSERT INTO component (component_id, component_player, component_x, component_y, ".
                    "component_orientation, aside_discard) VALUES ";
    $values = array();
    for( $i=1 ; $i<=144 ; $i++ ) {
        $values[] = "(".$i.",NULL,NULL,NULL,0,NULL)";
    }
    $sql .= implode( ',', $values );
    self::DbQuery( $sql );

    $sql = "INSERT INTO card (card_round, card_id) VALUES ";
    $values = array();
    for( $round=1 ; $round<=3 ; $round++ ) {
        for( $i=0 ; $i<=19 ; $i++ ) {
            $id = $i + 20 * ($round-1);
            $values[] = "(".$round.",".$id.")";
        }
    }
    $sql .= implode( ',', $values );
    $queryRet = self::DbQuery( $sql );
    self::traceExportVar( $queryRet, 'queryRet', 'setupNewGame' );

    $sql = "INSERT INTO revealed_pile (space, tile_id) VALUES ";
    $values = array();
    for( $space=0 ; $space<=139 ; $space++ ) {// Expansions: value '139' must be raised if
                                        // the total number of available tiles is raised
        $values[] = "(".$space.",NULL)";
    }
    $sql .= implode( ',', $values );
    self::DbQuery( $sql );

    self::log("Game initialized");
    self::setGameStateInitialValue( 'testGameState', 1 ); 
    // $this->gamestate->setAllPlayersMultiactive();

    /************ End of the game initialization *****/
  }

    /*
        getAllDatas:

        Gather all informations about current game situation (visible by the current player).

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
  protected function getAllDatas() {
    self::log("Starting getAllDatas()");
    $result = array();
    $state = $this->gamestate->state();

    $current_player_id = self::getCurrentPlayerId();    // !! We must only return
                                                // informations visible by this player !!

    ////// Get information about players
    $sql = "SELECT player_id id, player_color color, player_score score, turn_order, ".
                    "player_position, undo_possible, exp_conn, nb_crew, min_eng, max_eng, ".
                    "min_cann_x2, max_cann_x2, still_flying FROM player ";
    $result['players'] = self::getCollectionFromDB( $sql );
    $result['nbPlayers'] = count( $result['players'] );

    ////// Gather all information about current game situation
    ////// (visible by player $current_player_id)
    $flightVariant = self::getGameStateValue( 'flight_variants' );
    // The first, second or third (or fourth?) flight in this game:
    $flight = self::getGameStateValue( 'flight' );
    // The 'level' of current flight, i.e. 1 for class I, 3 for class III or IIIa, etc.
    $result['round'] = $this->flightVariant[$flightVariant][$flight]['round'];
    // Ship class, used to display appropriate ship board and available squares:
    $result['shipClass'] = $this->flightVariant[$flightVariant][$flight]['shipClass'];

    // TODO? Fetch the whole component table once, instead of making 5 different requests?

    // All PLACED TILES from all players, including tiles SET ASIDE / DISCARDED
    $result['placed_tiles'] = self::getCollectionFromDB( "SELECT component_id id, component_x x, ".
            "component_y y, component_player player, component_orientation o, aside_discard ".
            "FROM component WHERE component_player IS NOT NULL AND component_x IS NOT NULL");
    if ( self::getGameStateValue ('overlayTilesPlaced') === "1" ) {
      // We add a property to each cabin to tell the client to place an overlay tile (should we
      // add a column in DB instead?)
      foreach ( $result['placed_tiles'] as $tile ) {
        if ( $this->tiles[ $tile['id'] ][ 'type' ] == 'crew' && $tile['aside_discard'] == null ) {
            $result['placed_tiles'][ $tile['id'] ][ 'placeOverlay' ] = true;
        }
      }
    }
    $result['cards'] = array(); // This array will stay empty unless we're in buildPhase
            // and current player is still active, but we send it anyway because
            // setupCardsPiles() in setup() expects it

    // Only in waitForPlayers and buildPhase:
    // Pile of face down tiles
    if ( $state['name'] == 'waitForPlayers'
        || $state['name'] == 'buildPhase' ) {
        $result['tilesLeft'] = self::getUniqueValueFromDB( "SELECT COUNT(component_id) ".
            "FROM component WHERE component_player IS NULL" );
    }

    ////////// Some informations are only needed during BUILD PHASE (and takeOrderTiles):
    if ( $state['name'] == 'buildPhase'
        || $state['name'] == 'prepareRound' ) { // possible?
        // some informations are only needed when current player is STILL ACTIVE
        if ( in_array( $current_player_id, $this->gamestate->getActivePlayerList() ) ) {
              // If it's possible that getAllDatas is executed during a "game" gamestate 
              //(prepareRound), we must add || $state['name'] == 'prepareRound' to this if condition
            $result['current_tile'] = self::getObjectFromDB( "SELECT component_id id, ".
                    "aside_discard aside FROM component WHERE component_player=$current_player_id ".
                    "AND component_x IS NULL" );
            $result['undoPossible'] = $result['players'][$current_player_id]['undo_possible'];
            // Check if current player has placed at least 1 tile, to allow them to look at
            // adventure cards (may be different for ship classes from expansions)
            $result['atLeast1Tile'] = 0;
            foreach ( $result['placed_tiles'] as $tile ) {
                if ( $tile['player'] == $current_player_id 
                        && ( $tile['x'] != 7 || $tile['y'] != 7 ) ) {
                          // Expansions: need to be changed for expansions' ship classes that don't
                          // have a starting component at the beginning
                    $result['atLeast1Tile'] = 1;
                    break;
                }
            }
            if ( $result['atLeast1Tile'] == 1 ) {
                $result['cards'] = GT_DBCard::getAdvDeckPreview($this);
            }
        }
        $result['revealed_tiles'] = self::getCollectionFromDB( "SELECT tile_id id, space ".
                "FROM revealed_pile WHERE tile_id IS NOT NULL ORDER BY space DESC", true);
                // ordered because we get max space value just below (with current()), to
                // compute number of lines in revealed_pile

        // TIMER
        $timerPlace = self::getGameStateValue( 'timerPlace' );
        if ( $timerPlace !== "-1" ) {
            $result['timerPlace'] = (int) $timerPlace;
        }
        $elapsedTime = time() - self::getGameStateValue( 'timerStartTime' );
        $result['timeLeft'] = ($elapsedTime <= 90) ? (90 - $elapsedTime) : 0;
    }
    ////////// END of BUILD-PHASE-only informations


    // ORDER TILES:
    if ( in_array( $state['name'], array('prepareRound', 'waitForPlayers',
                        'buildPhase', 'takeOrderTiles', 'repairShips') ) ) {
        // order_tiles array initialization
        $result['order_tiles'] = array();
        for ( $pos = 1; $pos <= $result['nbPlayers']; $pos++ ) {
            $result['order_tiles'][$pos] = 'available';
        }
        // Which tiles have already been taken, and by who (to display them on ship boards)
        foreach( $result['players'] as $player_id => $player ) {
            if ( $player['turn_order'] ) {
                $result['order_tiles'][$player['turn_order']] = $player_id;
            }
        }
    }

    // send TILE CONTENT (crew, cells, goods, in content DB table) to client, and also
    // alien choices still to be made
    $result['content'] = self::getObjectListFromDB( "SELECT * FROM content" );

    $result['currentCard'] = self::getGameStateValue( 'currentCard' );

    return $result;
  }

    /*
        getGameProgression:

        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).

        This method is called each time we are in a game state with the "updateGameProgression" property set to true
        (see states.inc.php)
    */
  function getGameProgression() {
    // TODO: need to be changed if more or less than 3 flights in game, or if more cards in
    // the adventure deck (expansions)
    switch ( self::getGameStateValue( 'flight' ) ) {
      case 1:
        $before = 2;
        break;
      case 2:
        $before = 15;
        break;
      case 3:
        $before = 34;
        break;
    }
    $card = self::getGameStateValue( 'cardOrderInFlight' );
    return floor(($card + $before ) * 2);
  }

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

  function log( $msg ) {
    self::trace("##### $msg");
  }

  function log_console( $msg ) {
    self::notifyAllPlayers( "consoleLog", '', array( 'msg' => $msg ) );
  }

  function dump_var($msg, $var) {
    self::trace("##### $msg :: " . var_export($var,TRUE));
  }

  function dump_console($msg, $var) {
    self::notifyAllPlayers("consoleLog", '', "$msg :: " . var_export($var,TRUE));
  }

  function throw_bug_report($msg) {
    $func = debug_backtrace()[1]['function'];
    $line = debug_backtrace()[1]['line'];
    throw new BgaVisibleSystemException( $msg . " (at $func line $line)! " . $this->plReportBug );
  }


  function traceExportVar( $varToExport, $varName, $functionStr ) {
    self::trace( "###### $functionStr(): $varName is ".var_export( $varToExport, true)." " );
  }

  function cardsIntoPile( $cdRound, $nbCards ) {
    for ( $i=1; $i<=4; $i++ ) {
        // TODO replace RAND
        self::DbQuery( "UPDATE card SET card_pile=$i WHERE card_round=$cdRound ".
                      "AND card_pile IS NULL AND used=0 ORDER BY RAND() LIMIT $nbCards" );
    }
  }

  function getPlayerBoard( $player_id ) {
    return self::getDoubleKeyCollectionFromDB( "SELECT component_x x, component_y y, ".
                  "component_id id, component_orientation o FROM component ".
                  "WHERE component_player=$player_id AND component_x>0" );
  }

  function getPlContent( $plId ) {
    return self::getCollectionFromDB( "SELECT * FROM content WHERE player_id=$plId" );
  }

  function newPlayerBoard( $player_id, $plBoard=null ) {
    if ( $plBoard )
      return new GT_PlayerBoard($this, $plBoard, $player_id);
    else
      return new GT_PlayerBoard($this, self::getPlayerBoard($player_id), $player_id);
  }

  function newPlayerContent( $player_id, $plContent=null ) {
    if ($plContent )
      return new GT_PlayerContent($this, $plContent, $player_id );
    else
      return new GT_PlayerContent($this, self::getPlContent($player_id), $player_id );
  }

  function resetUndoPossible( $plId, $action="" ) {
    // This function is executed when a tile is picked by a player (anywhere), because at this
    // moment, we must remove the possibility (as per the game rules, p.2) for this player to
    // take back the last tile they placed on their ship. This is executed at the end of the
    // function (SET undo_possible=NULL).
    // At the same time, we must also (EXCEPT in one case, see below) set the aside_discard value
    // of the still-removable last placed tile (if any) to NULL. Explanation: if the last placed
    // tile on the ship is still removable (in the code below: $lastPlaced !== null), it may have
    // a '1' in aside_discard column, 'component' table, in the case it was set aside before
    // (because we need to remember it was placed aside before, because this player can still
    // pick it back, and in this case they're not allowed to put it back in the revealed pile).
    // And at the moment resetUndoPossible() is executed, we don't need anymore to remember if
    // the last placed tile was set aside before (because it's now impossible to take it back).
    // So if $lastPlaced is not null, we set aside_discard of this last placed to NULL (without
    // bothering checking if it's already NULL).
    // BUT we MUSTN'T do this in one case: if the action that triggers resetUndoPossible() is
    // the player taking back the last tile that they placed on their ship ($action ==
    // 'lastPlaced'), because in this case the last placed tile is not "glued" on the ship, it
    // goes in player's hand, so we still need to rememeber if this tile was set aside before.
    
    if ( $action !== 'lastPlaced' ) {
        $lastPlaced = self::getUniqueValueFromDB( "SELECT undo_possible FROM player ".
                                      "WHERE player_id=$plId" );
        if ( $lastPlaced !== null ) {
            self::DbQuery( "UPDATE component SET aside_discard=NULL ".
                              "WHERE component_id=$lastPlaced" );
        }
    }
    self::DbQuery( "UPDATE player SET undo_possible=NULL WHERE player_id=$plId" );
  }

  function checkIfTileInHand( $plId, $caller ){
    if ( self::getUniqueValueFromDB( "SELECT component_id FROM component ".
          "WHERE component_player=$plId AND component_x IS NULL" ) !== null ) {
        throw new BgaVisibleSystemException( "$caller: you have a tile in hand. ".
                                                  $this->plReportBug );
    }
  }

  function placeInRevealed( $tile_id, $player_id ){
    // Get the first empty space in the revealed pile
    $firstAvailSp = self::getUniqueValueFromDB( "SELECT space FROM revealed_pile ".
                                  "WHERE tile_id IS NULL ORDER BY space LIMIT 1" );
    if ( $firstAvailSp === null ) {
        throw new BgaVisibleSystemException( "Error: no empty space found in revealed pile ".
                        "(\$firstAvailSp is null). ".$this->plReportBug );
    }

    // Place dropped tile in revealed pile
    self::DbQuery( "UPDATE component SET component_player=-1, component_orientation=0 ".
                      "WHERE component_id=$tile_id" );
    self::DbQuery( "UPDATE revealed_pile SET tile_id=$tile_id WHERE space=$firstAvailSp" );

    self::notifyAllPlayers( "droppedTile", '', array(
                                      'tile_id' => $tile_id,
                                      'player_id' => $player_id,
                                      'placeInRevealedPile' => $firstAvailSp ) );
  }

  function updNotifPlInfos( $plId, $plBoard=null, $plContent=null, $bNbCrew=false, $bExpConn=false )
  {
    // This function is called each time a player loses batteries, aliens, components
    // (in this last case $bExpConn is true), and once when ships are built and content
    // (including aliens) placed. It gets updated values for this player and uses them
    // to update the player table and notify players so that they can update these
    // informations in BGA's side player boards.
    if ( $plBoard == null ) { $plBoard = self::getPlayerBoard( $plId ); }
    if ( $plContent == null ) { $plContent = self::getPlContent( $plId ); }
    
    $brd = $this->newPlayerBoard($plId, $plBoard);
    $plyrContent = $this->newPlayerContent($plId, $plContent);

    $minMaxCann = $brd->getMinMaxStrengthX2 ( $plyrContent, 'cannon' );
    $minMaxEng = $brd->getMinMaxStrengthX2 ( $plyrContent, 'engine' );
    $items = array ( array (
                        'type' => 'minMaxCann',
                        'value' => ($minMaxCann['min']/2)."/".($minMaxCann['max']/2),
                         ),
                    array (
                        'type' => 'minMaxEng',
                        'value' => ($minMaxEng['min']/2)."/".($minMaxEng['max']/2),
                         ),
                    );
    $sql = "UPDATE player SET ";
    if ( $bNbCrew ) {
      $nbCrewMembers = $plyrContent->nbOfCrewMembers();
      $sql .= "nb_crew=".$nbCrewMembers.", ";
      $items[] = array ( 'type' => "nbCrew", 'value' => $nbCrewMembers );
    }
    if ( $bExpConn ) {
      $nbExp = $brd->nbOfExposedConnectors();
      $sql .= "exp_conn=".$nbExp.", ";
      $items[] = array ( 'type' => "expConn", 'value' => $nbExp );
    }
    $sql .= "min_cann_x2=".$minMaxCann['min'].", max_cann_x2=".$minMaxCann['max'].", ".
            "min_eng=".($minMaxEng['min']/2).", max_eng=".($minMaxEng['max']/2)." ".
            "WHERE player_id=$plId";
    self::DbQuery( $sql );
    self::notifyAllPlayers( "updatePlBoardItems", "", array(
                  'plId' => $plId,
                  'items' => $items,
                  ) );
  }

  function computeNewPlayerPos( $players, $playerId, $nbDays ){
    $newPlPos = $players[$playerId]['player_position'];
    $otherPlPos = array();
    foreach ( $players as $player ) {
        if ( $player['player_id'] != $playerId ) {
            $otherPlPos[] = $player['player_position'];
        }
    }

    for ( $i = 1; $i <= abs($nbDays); $i++ ) {
        do {
            if ( $nbDays > 0 )
                $newPlPos++;
            else
                $newPlPos--;
        } while ( in_array( $newPlPos, $otherPlPos ) ); // Note: this does not check if players are on the same space but one lap behind / ahead
    }
    // TODO check if players are getting lapped
    return $newPlPos;
  }

  // To avoid problems due to transtyping, $nbDays must be an integer
  function moveShip( $plId, $nbDays, $players=null ) {
    if (!$players)
      $players = GT_DBPlayer::getPlayersInFlight($this);

    $plName = $players[$plId]['player_name'];
    if ( $nbDays === 0 ) {
        self::notifyAllPlayers( "onlyLogMessage",
                                clienttranslate( '${player_name} doesn\'t move'),
                                array ( 'player_name' => $plName ) );
        return null;
    }
    else {
        $trslStr = ($nbDays>0) ? clienttranslate('${player_name} gains ${numDays} flight days')
                  : clienttranslate('${player_name} loses ${numDays} flight days');
        $newPlPos = self::computeNewPlayerPos( $players, $plId, $nbDays );
        self::DbQuery("UPDATE player SET player_position=$newPlPos WHERE player_id=$plId");
        self::notifyAllPlayers( "moveShip", $trslStr,
                            array(
                              'player_id' => $plId,
                              'player_name' => $plName,
                              'numDays' => abs($nbDays),
                              'newPlPos' => $newPlPos,
                            ) );
        //TODO check if lapping or getting lapped here?
        return $newPlPos; // used to update $players array when other ships will move in the same action
    }
  }

  function noExplore ( $plId, $player_name ) {
    GT_DBPlayer::setCardDone($this, $plId);
    self::notifyAllPlayers( "onlyLogMessage", clienttranslate( '${player_name} '.
          'doesn\'t stop'), array ( 'player_name' => $player_name ) );

  }

  function getConnectorType( $tile, $side ) {
    // compute side presented by this tile
    $tileSide = ( 360 + $side - $tile['o'] ) % 360; // we add 360 so that it can't be negative
    // return connector type
    return $this->tiles[ $tile['id'] ][ $this->orient[$tileSide] ];
    // $this->orient[0] is 'n', $this->orient[90] is 'e', etc.
  }

  function getNbOfCrewMembers ( $plId, $plContent=null ) {
      return self::getUniqueValueFromDB( "SELECT COUNT(content_type) FROM content ".
                    "WHERE content_type='crew' AND player_id=$plId");
  }


//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in galaxytrucker.action.php)
    */

  function ImReady() {
    self::checkAction( 'ImReady' );
    $player_id = self::getCurrentPlayerId();
    // TODO do we have some checks to do? Maybe not, since this action only de-activate current player
    $this->gamestate->setPlayerNonMultiactive( $player_id, "readyGo" );
  }

  function pickTile( ) {
    self::checkAction( 'pickTile' );
    $player_id = self::getCurrentPlayerId();

    // Sanity checks
    self::checkIfTileInHand ( $player_id, 'Pick tile' );

    $pickedTile = self::getUniqueValueFromDB( "SELECT component_id FROM component ".
                  "WHERE component_player IS NULL ORDER BY RAND() LIMIT 1" );// TODO replace RAND
    if ( $pickedTile !== null ) {
        self::DbQuery( "UPDATE component SET component_player=$player_id ".
                          "WHERE component_id=$pickedTile" );
        self::resetUndoPossible( $player_id );
    }
    // If $pickedTile is null, there's no more unrevealed tile, and the client will
    // disconnect clickable_pile

    self::notifyPlayer( $player_id, "pickedTilePl", '', array(
          'pickedTile' => $pickedTile ) );
    self::notifyAllPlayers( "pickedTile", '', array() );
  }

  function pickRevealed( $tile_id ) {
      self::checkAction( 'pickRevealed' );
      $player_id = self::getCurrentPlayerId();

      // Sanity check
      self::checkIfTileInHand ( $player_id, 'Pick revealed' );

      $location = self::getUniqueValueFromDB( "SELECT component_player FROM component ".
                                              "WHERE component_id=$tile_id" );
      if ( $location !== '-1' )
          throw new BgaUserException ( self::_("This component has already been taken ".
                                                        "by someone else") );

      self::DbQuery( "UPDATE component SET component_player=$player_id ".
                                            "WHERE component_id=$tile_id" );
      self::DbQuery( "UPDATE revealed_pile SET tile_id=NULL WHERE tile_id=$tile_id" );
      self::resetUndoPossible( $player_id );
      
      self::notifyAllPlayers( "pickedRevealed", '', array(
                                        'tile_id' => $tile_id,
                                        'player_id' => $player_id ) );
  }

  function pickAside( $tile_id ) {
      self::checkAction( 'pickAside' );
      $player_id = self::getCurrentPlayerId();

      // Sanity checks:
      self::checkIfTileInHand ( $player_id, 'Pick aside' );
      // Is this tile in discard?
      $pickedTile = self::getObjectFromDB( "SELECT * FROM component ".
                                            "WHERE component_id=$tile_id" );
      $x = $pickedTile['component_x'];
      $aside = $pickedTile['aside_discard'];
      if ( $pickedTile['component_player'] != $player_id ||
            $aside !== "1" ||
            ( $x != "-1" && $x != "-2" ) ) {
          throw new BgaVisibleSystemException ( "This tile is not in your discard. ".
                $this->plReportBug." (pl: $player_id x: $x aside: $aside)" );
      }

      self::DbQuery( "UPDATE component SET component_x=NULL, component_y=NULL ".
                      "WHERE component_id=$tile_id" );
      self::resetUndoPossible( $player_id );
      self::notifyAllPlayers( "pickedAside", '', array(
                                        'tile_id' => $tile_id,
                                        'player_id' => $player_id,
                                        'discardSquare' => $x ) );
  }

  function pickLastPlaced( $tile_id ) {
      self::checkAction( 'pickLastPlaced' );
      $player_id = self::getCurrentPlayerId();

      // Sanity checks:
      // Not necessary to check if a tile is in hand, because in this case,
      // undo_possible should be null
      // Is this tile the last placed tile, and is it still possible to take it back ?
      $undoTile = self::getUniqueValueFromDB ( "SELECT undo_possible FROM player ".
                                                "WHERE player_id=$player_id" );
      if ( $undoTile !== $tile_id ) { // both are strings
          throw new BgaVisibleSystemException ( "You can't take back this tile. ".
                $this->plReportBug." (pl: $player_id tile id: $tile_id undo: $undoTile)" );
      }
      // We must get x and y coords for this tile, so that the client can connect again
      // the corresponding square to onPlaceTile, and also aside_discard, so that the
      // client knows if this tile was set aside before
      $pickedTile = self::getObjectFromDB ( "SELECT component_x x, component_y y, ".
                        "aside_discard aside FROM component WHERE component_id=$tile_id" );
      // another sanity check?
      if ( !($pickedTile['x']>0) || !($pickedTile['y']>0) ) {
          throw new BgaVisibleSystemException ( "PickLastPlaced: tile coords are ".
                $pickedTile['x']." and ".$pickedTile['y'].". ".$this->plReportBug );
      }
                
      self::DbQuery( "UPDATE component SET component_x=NULL, component_y=NULL ".
                    "WHERE component_id=$tile_id" );
      self::resetUndoPossible( $player_id, 'lastPlaced' );
      self::notifyAllPlayers( "pickedLastPlaced", '', array(
                                        'tile_id' => $tile_id,
                                        'x' => $pickedTile['x'],
                                        'y' => $pickedTile['y'],
                                        'aside' => $pickedTile['aside'],
                                        'player_id' => $player_id ) );
  }

  function dropTile( $tile_id ) {
      self::checkAction( 'dropTile' );
      $player_id = self::getCurrentPlayerId();

      // Various checks
      $location = self::getObjectFromDB( "SELECT component_player player, component_x x, ".
            "aside_discard aside FROM component WHERE component_id=$tile_id" );
      if ( $location['player'] != $player_id || $location['x'] !== null )
          throw new BgaVisibleSystemException( "Drop tile: you don't have this tile in hand. ".
                                                $this->plReportBug );
      if ( $location['aside'] !== null )
          throw new BgaVisibleSystemException( "You can't place a previously set aside ".
                                                "tile here. ".$this->plReportBug );

      self::placeInRevealed( $tile_id, $player_id ); // DB and client notif
  }

  function placeTile( $component_id, $x, $y, $o, $discard ) {
      self::checkAction( 'placeTile' );
      $player_id = self::getCurrentPlayerId();
      $firstPlacedTile = true; // To know if me must send this player the cards in
                      // piles' ids. Will be set to false if this tile is placed in
                      // discard, or if at least one tile has already been placed
      $cards = null;
      $allTiles = self::getCollectionFromDB( "SELECT component_id id, component_x x, component_y y, ".
                        "aside_discard aside FROM component WHERE component_player=$player_id" );
                        //TODO maybe aside_discard is not needed
                        // TODO to reduce the number of database requests, we could use
                        // getPlayerBoard (needed later) instead of this custom request

      // Various checks
      if ( !array_key_exists($component_id, $allTiles) || $allTiles[$component_id]['x'] !== null )
          throw new BgaVisibleSystemException( "Place tile: You don't have this tile (".
                        $component_id.") in hand. ".$this->plReportBug );
      // TODO We also need to check if the tile is placed on a valid square (not outside
      // the board or the discard layer 1)

      if ( $discard == 1 ) {
          // This tile is being set aside
          $firstPlacedTile = false;
          if ( $x !== "-1" && $x !== "-2" )
              throw new BgaVisibleSystemException( "x is $x, should be -1 or -2 for a ".
                        "tile that is set aside. ".$this->plReportBug );
          if ( $o !== "0" ) // Do we really need to raise an exception for that? We could
                            // log it and set $o to 0.
              throw new BgaVisibleSystemException( "tile orient is $o, should be 0. ".
                                                                $this->plReportBug );
          foreach ( $allTiles as $tile ) {
              if ( $tile['x']==$x )
                  throw new BgaVisibleSystemException( "There is already a tile (".
                        $tile['id'].") on square $x in discard. ".$this->plReportBug );
          }
          $y = 'NULL'; // we want component_y to be null in DB for tiles that are set aside
      }
      else {
          foreach ( $allTiles as $tile ) {
              if ( $tile['x']==$x && $tile['y']==$y )
                  throw new BgaVisibleSystemException( "There is already a tile (".
                        $tile['id'].") on square $x $y . ".$this->plReportBug );
              // At the same time, we check if this player has already placed at least one
              // tile (not a starting cabin, id 31 to 34), if it's not the case we'll send
              // them the revealed cards' ids because they are now allowed to look at them
              if ( $tile['x']>0 && ($tile['id']<31 || $tile['id']>34) )
                  $firstPlacedTile = false;
          }
          $brd = $this->newPlayerBoard($player_id);
          $tileToCheck = array( 'x' => $x, 'y' => $y, 'id' => $component_id, 'o' => $o );
          if ( ! $brd->checkIfTileConnected( $tileToCheck ) )
              throw new BgaUserException ( self::_("Wrong tile placement : this tile ".
                                                "isn't connected to your ship") );
      }

      $sql = "UPDATE component SET component_x=$x, component_y=$y, component_orientation=$o";
      if ( $y === 'NULL' ) {
          $sql .= ", aside_discard='1'";
          $y = 'discard'; // this is what notif_placedTile in .js expects
      }
      else {
        // Not in discard, so we store this tile's id so that it can be taken back until
        // another component is grabbed (rules)
        self::DbQuery( "UPDATE player SET undo_possible=$component_id ".
                                    "WHERE player_id=$player_id" );
      }
      $sql .= " WHERE component_id=$component_id";
      self::DbQuery( $sql );

      if ( $firstPlacedTile ) {
        $cards = GT_DBCard::getAdvDeckPreview($this);
        self::notifyPlayer( $player_id, 'cardsPile', "",
                            array( 'cards' => $cards ) );
      }

      self::notifyAllPlayers( "placedTile", '',
              array( 'player_id' => $player_id,
                  'component_id' => $component_id,
                  'x' => $x,
                  'y' => $y, // is 'discard' if set aside
                  'o' => $o, ) );
  }

  function flipTimer( $timerPlace ) {
      // $timerPlace is the number of the circle where the timer WAS (and currently still is),
      // not the one where it WILL BE
      $player_id = self::getCurrentPlayerId();
      $player_name = self::getCurrentPlayerName();

      // Checks
      // We use checkPossibleAction instead of checkAction because a player can
      // flip the timer when inactive
      $this->gamestate->checkPossibleAction( 'flipTimer' );
      $elapsedTime = ( time() - self::getGameStateValue('timerStartTime') );
      // TODO : throw a user exception instead of a system exception if the timer
      // was just flipped (less than 2s?)
      if ( $timerPlace < 1 || $timerPlace !== self::getGameStateValue('timerPlace') )
          throw new BgaVisibleSystemException( "Flip timer: wrong value for timerPlace (".
                                var_export($timerPlace, true)."). ".$this->plReportBug );
      if ( $elapsedTime < 90 )
          throw new BgaVisibleSystemException( "Flip timer: timer is not finished. ".
                                            $elapsedTime."s. ".$this->plReportBug );
      $turnOrder = self::getUniqueValueFromDB( "SELECT turn_order FROM player ".
                                                "WHERE player_id=$player_id" );
      if ( $timerPlace == 1 && $turnOrder === null )
          throw new BgaVisibleSystemException( "Flip timer: you can't flip the timer on ".
            "the last space when you are still building your ship. ".$this->plReportBug );

      // Set new timer place and start time in DB
      self::setGameStateValue( 'timerPlace', $timerPlace-1 );
      self::setGameStateValue( 'timerStartTime', time() );
      // Notify players
      self::notifyAllPlayers( "timerFlipped",
                clienttranslate( '${player_name} has flipped the timer.'),
                array( 'player_id' => $player_id,
                    'player_name' => $player_name,
                    'timerPlace' => $timerPlace-1 ) );
  }

  function timeFinished() {
    // We can't set a timer that triggers the end of building server-side,
    // so the clients send a timeFinished ajax action when their 90s timer
    // is finished on the last timer space. This method checks if time is
    // really finished, does things, then changes state.
    
    // But if just after the first timeFinished action, another client sends
    // a second one before having received the state change notif (which
    // prevents it to send a timeFinished action), we must ignore this
    // second timeFinished, but we must still send a quiet notif, because
    // the client consider that an action is still in progress untill it
    // gets an answer from the server.
    if ( !self::checkAction( 'timeFinished', false ) ) {
        self::notifyPlayer( self::getCurrentPlayerId(), "timeFinished", "", array() );
        return;
    }

    $players = self::loadPlayersBasicInfos(); // maybe useless
    // Check if time is really finished
    $elapsedTime = ( time() - self::getGameStateValue('timerStartTime') );
    $timerPlace = self::getGameStateValue('timerPlace');
    if ( $timerPlace !== "0" || $elapsedTime < 89 )
        throw new BgaVisibleSystemException( "Time finished: time is not finished. ".
                      $elapsedTime."s on space $timerPlace . ".$this->plReportBug );
      // TODO We should still send a notif with remaining time to the client, shouldn't we ?
      // Because if there's a bug that affect all players, nothing will stop the building 
      // and they'll be able to build forever.
    if ( $elapsedTime < 90 ) { // Don't know if I'll keep this
        self::notifyPlayer( self::getCurrentPlayerId(), "almostFinished",
                 'Error: time was almost finished, but not exactly.', array() );
        return;
    }

    // Do players still have a tile in hand?
    // (Is it better to do this here or in stTakeOrderTiles? I think it's ok
    // to deal with this here
    $tilesInHand = self::getCollectionFromDB( "SELECT component_id id, ".
              "component_player player, aside_discard aside FROM component ".
              "WHERE component_player>0 AND component_x IS NULL" );
    foreach ( $tilesInHand as $tileId => $tile ) {
      $plId = $tile['player'];
      if ( $tile['aside'] == 1 ) {
        // This tile was set aside before, so it must go in this player's discard zone
        // In order to place it in a free square (there must be at least one, since
        // the tile in hand was in the discard before), we must first check
        // if there's already a tile in discard
        $occupiedSquare = self::getUniqueValueFromDB( "SELECT component_x x FROM ".
        "component WHERE component_player=$plId AND aside_discard=1 AND component_x<0" );
        if ( !$occupiedSquare ) $squareToDiscardTo = -1;// No tile in discard, so the tile
                                                      // in hand goes on the 1st square
        elseif ( $occupiedSquare == -1 ) $squareToDiscardTo = -2;
        elseif ( $occupiedSquare == -2 ) $squareToDiscardTo = -1;
        else throw new BgaVisibleSystemException( "Bad value for \$occupiedSquare: ".
                      var_export($occupiedSquare, true)." . ".$this->plReportBug );

        self::DbQuery( "UPDATE component SET component_x=$squareToDiscardTo, ".
                      "component_orientation=0 WHERE component_id=$tileId" );
        self::notifyAllPlayers( "placedTile", '',
                array( 'player_id' => $plId,
                    'component_id' => $tileId,
                    'x' => $squareToDiscardTo,
                    'y' => 'discard',
                    'o' => 0 ) );
      }
      else {
          // This tile was not set aside before, so we'll drop it in revealed pile
          self::placeInRevealed( $tileId, $plId ); // DB and client notif
      }
    }

    self::DbQuery( "UPDATE player SET undo_possible=NULL" );
    $this->gamestate->nextState( 'timeFinished' );
  }

  function finishShip( $orderTile, $player_id=Null ) {
      // Many things in galaxy trucker's code are based on the asumption that this
      // function is ALWAYS executed for each player each round.

      if (!$player_id) {
          self::checkAction( 'finishShip' );
          $player_id = self::getCurrentPlayerId();
      }

      $players = self::loadPlayersBasicInfos(); // TODO load ONCE all the columns
          // we need in the player table, to minimize the number of SQL requests
      $round = self::getGameStateValue('round');

      // Check if order tile is still available
      $count = self::getUniqueValueFromDB( "SELECT COUNT(player_id) FROM player ".
                                            "WHERE turn_order=$orderTile" );
      if ( $count !== '0' )
          throw new BgaUserException(  self::_("This order tile is not available.") );

      // Sanity check: is there a tile in hand?
      $tile = self::getObjectFromDB( "SELECT component_id id FROM component ".
                    "WHERE component_player=$player_id AND component_x IS NULL" );
      if ( $tile !== null )
          throw new BgaVisibleSystemException( "You still have a tile in hand. "
                                                        .$this->plReportBug );

      // Set turn order according to the order tile taken and the round. Player position
      // will be set later, because we want it to be null until stPrepareFlight,
      // when ship markers are placed on the board (if getAllDatas get a null
      // value for player_position, the client won't display ship markers,
      // which is what we want before prepareFlight)
      self::DbQuery( "UPDATE player SET turn_order=$orderTile, ".
                    "undo_possible=NULL WHERE player_id=$player_id" );

      // Last placed tile may have aside_dicard=1, so we set it back to null
      self::DbQuery( "UPDATE component SET aside_discard=NULL ".
                      "WHERE component_x>0 AND component_player=$player_id" ); 

      self::notifyAllPlayers( "finishedShip",
              clienttranslate( '${player_name} has finished his/her ship!'.
                                ' Order tile: ${orderTile}' ),
              array( 'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'orderTile' => $orderTile,
      ) );

      $this->gamestate->setPlayerNonMultiactive( $player_id, "shipsDone" );
  }

  function finishRepairs( ) {
      self::checkAction( 'finishRepairs' );
      $player_id = self::getCurrentPlayerId();
      $brd = $this->newPlayerBoard($player_id);

      // TODO
      
      $nbExp = $brd->nbOfExposedConnectors();
      // Maybe we'll include DB update and notif in nbOfExposedConnectors()
      self::DbQuery( "UPDATE player SET exp_conn=$nbExp WHERE player_id=$player_id" );
      self::notifyAllPlayers( "updatePlBoardItems", '', array(
                'plId' => $player_id,
                'items' => array( array( 'type' => 'expConn', 'value' => $nbExp ) ),
                ) );
      $this->gamestate->setPlayerNonMultiactive( $player_id, 'repairsDone' );
  }

  function crewPlacementDone( $alienChoices ) {
      self::checkAction( 'crewPlacementDone' );
      $plId = self::getActivePlayerId();
      $player_name = self::getActivePlayerName();
      $contAsk = self::getCollectionFromDB( "SELECT content_id, tile_id, square_x, ".
                    "square_y, content_subtype FROM content WHERE player_id=$plId ".
                    "AND content_subtype IN ('ask_brown','ask_purple')" );
      $nbAliens = count( $alienChoices );
      $brown = 0;
      $purple = 0;
      $tilesToFill = array();
      $tilesWithAlien = array();
      self::traceExportVar($alienChoices,'alienChoices','crewPlacementDone');

      // Check if player input is correct
      foreach ( $alienChoices as $contId ) {
          if ( !array_key_exists( $contId, $contAsk ) )
              throw new BgaVisibleSystemException( "Wrong content id (".$contId.
                                "), not an alien choice for you. ".$this->plReportBug );
          if ( $contAsk[ $contId ][ 'content_subtype' ] == 'ask_brown' )
              $brown++;
          if ( $contAsk[ $contId ][ 'content_subtype' ] == 'ask_purple' )
              $purple++;
      }
      self::traceExportVar($brown,'brown','crewPlacementDone');
      if ( $brown > 1 || $purple > 1 )
          throw new BgaVisibleSystemException( "You can't have several aliens of ".
                                      "the same color. ".$this->plReportBug );
      // TODO check if no more than a single alien choice per tile

      // Add humans or aliens in relevant cabins
      $sqlImplode = array();
      //$shipContentUpdate = array();
      self::traceExportVar($contAsk,'contAsk','crewPlacementDone');
      foreach ( $contAsk as $contId => $content ) {
          $tileId = $content['tile_id'];
          // We fill an array with all the tiles where there is a choice. It will be used
          // to know what content to get from DB at the end of crewPlacementDone(), and
          // to place human crew where no alien is chosen.
          if ( ! array_key_exists( $tileId, $tilesToFill ) )
              $tilesToFill[$tileId] = $content; // We store the whole content here, to
                                              // have square_x and square_y
          if ( in_array( $contId, $alienChoices ) ) {
              $tilesWithAlien[] = $tileId;
              // Place an alien of the chosen color
              $sqX = $content['square_x'];
              $sqY = $content['square_y'];
              $color = substr( $content['content_subtype'], 4 ); // remove 'ask_'
              // Warning: $color will be used in notification message
              $sqlImplode[] = "('".$plId."', '".$tileId."', '".
                      $sqX."', '".$sqY."', 'crew', '".$color."', 1, 1)";
          }
      }
      // Place 2 humans in other cabins
      forEach ( $tilesToFill as $tileId => $content ) {
          if ( ! in_array( $tileId, $tilesWithAlien ) ) {
              $sqX = $content['square_x'];
              $sqY = $content['square_y'];
              for ( $i=1;$i<=2;$i++ ) {
                $sqlImplode[] = "('".$plId."', '".$tileId."', '".$sqX."', '".$sqY.
                        "', 'crew', 'human', ".$i.", 2)";
              }
          }
      }

      // Database update:
      // Remove alien choices in "content" DB table
      self::DbQuery( "DELETE FROM content WHERE player_id=$plId AND ".
                                              "content_subtype LIKE 'ask_%'" );
      // Add aliens and humans
      $sql = "INSERT INTO content (player_id, tile_id, square_x, square_y, ".
                                "content_type, content_subtype, place, capacity) ".
                                "VALUES ".implode( ',', $sqlImplode );
      self::DbQuery( $sql );
      // Get new content (with auto-incremented content_id) to notify players
      $shipContentUpdate = self::getCollectionFromDB( "SELECT * FROM content ".
              "WHERE tile_id IN (".implode( ',', array_keys($tilesToFill) ).")" );

      // This player has chosen his/her aliens:
      self::DbQuery( "UPDATE player SET alien_choice=0 WHERE player_id=".$plId );

      // Notify all players
      if ( $nbAliens == 0 )
          $notifyText = clienttranslate( '${player_name} has chosen no alien.' );
      elseif ( $nbAliens == 1 )
          $notifyText = clienttranslate( '${player_name} has chosen one alien: ').
                                            $this->translated[$color].'.';
      elseif ( $nbAliens == 2 )
          $notifyText = clienttranslate( '${player_name} has chosen a brown alien '.
                                            'and a purple alien.' );
          // Expansions: modify if cyan aliens expansion is implemented
      self::notifyAllPlayers( "updateShipContent", $notifyText, array( // on utilise notif_updateShipContent ou pas ?
                      'player_name' => $player_name,
                      'player_id' => $plId,
                      'ship_content_update' => $shipContentUpdate,
                      'gamestate' => 'placeCrew'
      ) );

      // Update of min / max strength (DB and notif)
      self::updNotifPlInfos( $plId, null, null, true );
      $this->gamestate->nextState( 'crewPlacementDone' );
  }

  // ############ CARD ACTIONS ############### 

  function battChoice( $battChoices ) {
      // current this is setup only for powering engines during open space
      self::checkAction( 'contentChoice' );
      $plId = self::getActivePlayerId();
      GT_ActionsCard::battChoice($this, $plId, $battChoices); 
      GT_DBPlayer::setCardDone($this, $plId);
      $this->gamestate->nextState( 'battChosen' );
  }

  function exploreChoice( $choice ) {
      self::checkAction( 'exploreChoice' );
      $plId = self::getActivePlayerId();
      $cardId = self::getGameStateValue( 'currentCard' );
      $nextState = GT_ActionsCard::exploreChoice($this, $plId, $cardId, $choice); 

      $this->gamestate->nextState($nextState);
  }

  function cancelExplore() {
    self::checkAction( 'cancelExplore' );
    $plId = self::getActivePlayerId();
    $player_name = self::getActivePlayerName();

    self::noExplore( $plId, $player_name ); 
    $this->gamestate->nextState( 'nextPlayer');
  }

  function crewChoice( $crewChoices ) {
      self::checkAction( 'contentChoice' );
      $plId = self::getActivePlayerId();
      $cardId = self::getGameStateValue( 'currentCard' );
      GT_ActionsCard::crewChoice($this, $plId, $cardId, $crewChoices);

      $this->gamestate->nextState( 'nextCard' ); 
  }

  function goOn( ) {
      self::checkAction( 'goOn' );
      $this->gamestate->nextState( 'goOn' );
  }

  function tempTestNextRound( ) {
      self::checkAction( 'tempTestNextRound' );
      $this->gamestate->nextState( 'tempTestNextRound' );
  }

  
//////////////////////////////////////////////////////////////////////////////
//////////// Temporary test functions
////////////

    /*
        These methods are only used to quickly check if game logic works properly, or to debug it.
    */

  function test_countTileType($type, $hold=null, $orientation=null) {
      $player_id = self::getCurrentPlayerId();
      $brd = $this->newPlayerBoard($player_id);
      $ret = $brd->countTileType($type, $hold, $orientation);
      $msg = "###### countTileType() $type $hold $orientation returns ".var_export($ret, true)." ";
      self::trace($msg);
      self::log_console($msg);
  }

  function test_checkShield( $sideToProtect ) // temp
  {
      $player_id = self::getCurrentPlayerId();
      $brd = $this->newPlayerBoard($player_id);
      $plyrContent = $this->newPlayerContent($player_id);
      $ret = $brd->checkIfPowerableShield( $plyrContent, $sideToProtect );
      $msg = "###### checkIfPowerableShield() returns ".var_export( $ret, true )." ";
      self::trace($msg);
      self::log_console($msg);
  }

  function test_checkCannon( $sideToCheck, $rowOrCol ) // temp
  {
      $player_id = self::getCurrentPlayerId();
      $brd = $this->newPlayerBoard($player_id);
      $plyrContent = $this->newPlayerContent($player_id);
      $ret = $brd->checkIfCannonOnLine( $plyrContent, $rowOrCol, $sideToCheck );
      $msg = "###### checkIfCannonOnLine() returns ".var_export( $ret, true )." ";
      self::trace($msg);
      self::log_console($msg);
  }

  function test_checkLine( $sideToCheck, $rowOrCol ) // temp
  {
      $player_id = self::getCurrentPlayerId();
      $brd = $this->newPlayerBoard($player_id);
      $ret = $brd->getLine( $rowOrCol, $sideToCheck );
      $msg = "###### getLine() returns ".var_export( $ret, true )." ";
      self::trace($msg);
      self::log_console($msg);
  }

  function test_checkConn( $sideToCheck, $rowOrCol ) // temp
  {
      $player_id = self::getCurrentPlayerId();
      $brd = $this->newPlayerBoard($player_id);
      $ret = $brd->checkIfExposedConnector( $rowOrCol, $sideToCheck );
      $msg = "###### checkIfExposedConnector returns ".var_export( $ret, true )." ";
      self::trace($msg);
      self::log_console($msg);
  }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argPowerEngines() {
        $plId = self::getActivePlayerId();
        $player = self::getObjectFromDb( "SELECT min_eng, max_eng FROM player ".
                                "WHERE player_id=".$plId );
        $nbDoubleEngines = self::countDoubleEngines( $plId );
        $plyrContent = $this->newPlayerContent( $plId );
        $nbCells = $plyrContent->checkIfCellLeft();
        return array( 'baseStr' => $player['min_eng'], 'maxStr' => $player['max_eng'],
                        'maxSel' => ( min( $nbDoubleEngines, $nbCells ) ) );
    }

    function argExploreAbandoned() {
        $plId = self::getActivePlayerId();
        $currentCard = self::getGameStateValue( 'currentCard' );
        if ( $this->card[$currentCard]['type'] == 'abship' 
            && self::getNbOfCrewMembers( $plId ) == $this->card[$currentCard]['crew'] ) {
            $wholeCrewWillLeave = true;
        }
        else
            $wholeCrewWillLeave = false;
        return array( 'wholeCrewWillLeave' => $wholeCrewWillLeave );
    }

    function argChooseCrew() {
        $currentCard = self::getGameStateValue( 'currentCard' );
        return array( 'nbCrewMembers' => $this->card[$currentCard]['crew'] );
    }

    /*

    Example for game state "MyGameState":

    function argMyGameState()
    {
        // Get some values from the current game situation in database...

        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }
    */

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

  function stPrepareRound() {
    // Are globals 'flight' and 'round' updated here or in stJourneysEnd()?
    // flight in stJourneysEnd(), but 'round' must be set here, since for the
    // first round it can't be set in setupNewGame()
    self::log("Starting stPrepareRound");
    $players = self::loadPlayersBasicInfos();
    $nbPlayers = count( $players );
    $flight = self::getGameStateValue( 'flight' );
    $flightVariant = self::getGameStateValue( 'flight_variants' );
    $round = $this->flightVariant[$flightVariant][$flight]['round'];
    self::setGameStateValue( 'round', $round );
    self::setGameStateValue( 'timerPlace', $round ); // So that the timer can be displayed
                                // by getAllDatas before it is started (in stBuildPhase)
    self::setGameStateValue( 'cardOrderInFlight', 0 );
    self::setGameStateValue( 'currentCard', -1 );
    // We need the ship class at the end of stPrepareRound to notify players
    $shipClass = $this->flightVariant[$flightVariant][$flight]['shipClass'];

    // Reset some values and clean content table
    //    still_flying may need to be set to 0 in
    //    the highly improbable case of a player having built a ship without
    //    humans (with expansions' ship classes without starting component)
    self::DbQuery( "UPDATE player SET still_flying=1, turn_order=NULL, player_position=NULL, nb_crew=NULL, ".
        "exp_conn=NULL, min_eng=NULL, max_eng=NULL, min_cann_x2=NULL, max_cann_x2=NULL" );
    self::DbQuery( "DELETE FROM content" ); 
    self::setGameStateValue( 'overlayTilesPlaced', 0 );

    // Prepare cards
    // Used cards are not used in next rounds (rules)
    self::DbQuery( "UPDATE card SET used=1 WHERE card_order IS NOT NULL" );
    self::DbQuery( "UPDATE card SET card_order=NULL" );

    switch ( $round ) {
      case 1:
        self::cardsIntoPile( 1, 2 );
        break;

      case 2:
        self::cardsIntoPile( 1, 1 );
        self::cardsIntoPile( 2, 2 );
        break;

      case 3:
        self::cardsIntoPile( 1, 1 );
        self::cardsIntoPile( 2, 1 );
        self::cardsIntoPile( 3, 2 );
        break;
      
      default:
        throw new BgaVisibleSystemException("Invalid round `$round` in stPrepareRound");
    }

    // Prepare ships
    self::DbQuery( "UPDATE component SET component_player=NULL, component_x=NULL, ".
                    "component_y=NULL, component_orientation=0, aside_discard=NULL" );
    self::DbQuery( "UPDATE revealed_pile SET tile_id=NULL" );

    // Starting crew components
    $startingTiles = array();
    foreach( $players as $player_id => $player ) {
        $id = array_search( $player['player_color'], $this->start_tiles );
        self::DbQuery( "UPDATE component SET component_x=7, component_y=7, ".
                        "component_player=$player_id WHERE component_id=$id" );
                        // Expansions: need to be changed for expansions' ship classes
                        // that don't have a starting component at the beginning
        $startingTiles[$id] = array( 'id' => $id, 'x' => 7, 'y' => 7,
                                    'player' => $player_id, 'o' => 0 );
                                // Expansions: for class Xc, starting components need to
                                // be set aside instead of in the center square
    }
    // Since with class IIa ships, we don't use starting components, but we
    // may use them next round, we can't delete them.
    // So we set component_player to 0, which means that these components are
    // not used this round, either because there's less than 4 players,
    // or because we don't use starting components at all
    self::DbQuery( "UPDATE component SET component_player=0 WHERE component_player IS NULL ".
                    "AND component_id>=31 AND component_id<=34" );

    ////// Prepare notif
    // Get cards that can be looked at by players
    $cardsInPiles = array();
    for( $i=1 ; $i<=3 ; $i++ )
        $cardsInPiles[$i] = GT_DBCard::getAdvDeckPile($this, $i);

    // Get number of tiles in face down pile
    $tilesLeft = self::getUniqueValueFromDB( "SELECT COUNT(component_id) ".
                        "FROM component WHERE component_player IS NULL" );
    // Start timer
    self::setGameStateValue( 'timerPlace', $round );
//    $startTime = time();
    // Globals are stored as signed INT, so this will be problematic around year 2038 :)
    // except if we substract a constant number of seconds each time we call time().
//    self::setGameStateValue( 'timerStartTime', $startTime ); // Pourquoi c'est là ET ds stBuildPhase ? oubli ?
//    self::setGameStateValue( 'buildingStartTime', $startTime );

    ////// Notify all
    self::notifyAllPlayers( "newRound", "", array(
                        'shipClass' => $shipClass,
                        'tilesLeft' => $tilesLeft,
                        'startingTiles' => $startingTiles,
                        'nbPlayers' => $nbPlayers,
                        'flight' => $flight,
                        'round' => $round,
                        ) );

    // Images may take some time to load, so for the first flight, when
    // the page is loading, we wait for players to announce they're ready,
    // using a waitForPlayers multipleactiveplayer gamestate

    // Setup a test game state
    if ( self::getGameStateValue( 'testGameState' ) ) {
      $gt_state = new GT_GameState($this, $players);
      $gt_state->prepareRound();
    }
    else {
      $nextState = ( $flight == 1 ) ? 'waitForPlayers' : 'buildPhase';
      $this->gamestate->setAllPlayersMultiactive();
      $this->gamestate->nextState( $nextState );
    }
  }

  function stActivatePlayersForBuildPhase() {
    $this->gamestate->setAllPlayersMultiactive();
    $this->gamestate->nextState( );
  }

  function stBuildPhase() {
    // Start timer
    $startTime = time();
    // Globals are stored as signed INT, so this will be problematic around year 2038 :)
    // except if we substract a constant number of seconds each time we call time().
    self::setGameStateValue( 'timerStartTime', $startTime );
    self::setGameStateValue( 'buildingStartTime', $startTime );
    self::notifyAllPlayers( "startTimer", "", array() );
  }

  function stTakeOrderTiles() {
    // Players who haven't taken an order tile yet
  }

  function stRepairShips() {
    self::log("Starting stRepairShips");
    $players = self::loadPlayersBasicInfos();
    $playersToActivate = array ();
    $errors = array();
    $playersShipPartsToKeep = array();
    $tilesToRemoveInDb = array();

    self::setGameStateValue( 'timerPlace', -1 );
    self::setGameStateValue( 'timerStartTime', 0 );

    foreach ( $players as $player_id => $player ) {
        $player_name = $player['player_name'];
        $shipParts = array();
        $playersShipPartsToKeep[$player_id] = array();
        $actionNeeded = false;
        $brd = $this->newPlayerBoard($player_id);

        // At first, we check if there are badly oriented engines in the ship, because
        // since we'll remove them without asking player, we can in next steps check for
        // other errors without considering them (so we remove them from $brd), and
        // also check if the ship holds together when these engines are removed
        $engToRemove = $brd->badEngines();
        $tilesToRemoveInDb = array_merge($engToRemove, $tilesToRemoveInDb);
        $brd->removeTiles($engToRemove);
        if ( $engToRemove ) {
            $idToRemove = array_map(function($x) { return $x['id']; }, $engToRemove);
            self::notifyAllPlayers( "loseComponent", clienttranslate('${player_name} '.
                            'loses ${numbComp} engine(s): wrong orientation'), array(
                    'plId' => $player_id,
                    'player_name' => $player_name,
                    'numbComp' => count($idToRemove),
                    'compToRemove' => $idToRemove,
                    ) );
        }

        // 2nd step: check if this ship holds together, taking into account
        // illegal connections
        // Check if these ship parts are valid (at least one cabin), only needed if
        // more than one part OR with ship classes without starting component
        $shipParts = $brd->checkShipIntegrity();
        if ( $shipParts > 1 ) {
            $partsToKeep = array_keys( $shipParts );
            foreach ( $shipParts as $partNumber => $part ) {
                $compToRemove = array();
                foreach ( $part as $tileId => $tile ) {
                    if ( $this->tiles[$tileId]['type'] == 'crew' ) {
                        //$playersShipPartsToKeep[$player_id][$partNumber] = $part;
                        continue 2;
                    }
                }
                // no cabin was found in this part, so it has to be removed from the ship
                $cntParts = count($part);

                $brd->removeTiles(array_values($part));
                foreach ( $part as $tileId => $tile ) {
                    $compToRemove[] = $tileId;
                    $tilesToRemoveInDb[] = $tile;
                }
                unset( $partsToKeep[ array_search($partNumber, $partsToKeep) ] );
                $numbComp = count($compToRemove);
                if ( $numbComp == 1 )
                    $notifyText = clienttranslate( '${player_name} loses a component not '.
                                                    'connected to the ship');
                else
                    $notifyText = clienttranslate( '${player_name}\'s ship doesn\'t hold together.'.
                            ' A part with ${numbComp} components (without cabin) is removed.');
                self::notifyAllPlayers( "loseComponent", $notifyText, array(
                        'plId' => $player_id,
                        'player_name' => $player_name,
                        'numbComp' => $numbComp,
                        'compToRemove' => $compToRemove,
                        ) );
                
            }

            if ( count( $partsToKeep ) > 1 ) {
                $actionNeeded = true;
                // re-index ship parts from 1 for client
                $index=1;
                foreach ( $partsToKeep as $partIndex) {
                    $playersShipPartsToKeep[$player_id][$index++] = $shipParts[$partIndex];
                }
            }
        }

        // 3rd step: check for other errors once badly oriented engines and invalid
        // ship parts have been removed. We also count exposed connectors, this will
        // be stored in exp_conn if this player doesn't need to repair their ship
        $errors = $brd->checkTiles();
        if ($errors)
              $actionNeeded = true;

        if ( $actionNeeded ) {
            $playersToActivate[] = $player_id;
        }
        else {
            // This player's ship is ready for the flight, so we count exposed connectors
            // now. Otherwise, we'll count them when they have repaired their ship.
            $nbExp = $brd->nbOfExposedConnectors();
            // Maybe we'll include DB update and notif in nbOfExposedConnectors()
            self::DbQuery( "UPDATE player SET exp_conn=$nbExp WHERE player_id=$player_id" );
            self::notifyAllPlayers( "updatePlBoardItems", '', array(
                    'plId' => $player_id,
                    'items' => array( array( 'type' => 'expConn', 'value' => $nbExp ) ),
                    ) );
        }
    } // End of foreach player

    if ( $tilesToRemoveInDb ) {
        $idsToRemove = array_map(function($x) { return $x['id']; }, $tilesToRemoveInDb);
        self::DbQuery( "UPDATE component SET aside_discard=1, component_x=-1, ". // TODO Pb if several tiles in discard for the same player
                    "component_y=NULL, component_orientation=0 ".
                    "WHERE component_id IN (".implode(',', $idsToRemove).")" );
    }

    if ( $playersToActivate ) {
      // Notify players so that the clients can mark ship parts
      // to choose from and/or connections with errors
      self::notifyAllPlayers( "buildingErrors", '', array(
              'errors' => $errors,
              'shipParts' => $playersShipPartsToKeep,
              ) );
    }

    $this->gamestate->setPlayersMultiactive( $playersToActivate, 'repairsDone' );
    // maybe this will change if we use turn order instead of everyone at the same time
    self::log("Finished stRepairShips");
  }

  // ######################### 
  function stPrepareFlight() {
    // This query's ordered by turn_order so that we can know which player to activate
    // first for alien placement (if he/she has a choice to do)
    $players = self::getCollectionFromDB( "SELECT player_id, player_color, turn_order ".
                                            "FROM player ORDER BY turn_order" );
    $round = self::getGameStateValue( 'round' );
    $nextState = 'crewsDone'; // will be changed to 'nextCrew' in the loop below only if
                            // we need to ask at least one player for alien choice
    self::setGameStateValue( 'overlayTilesPlaced', 1 );

    // Shuffle cards in pile to create the adventure deck
    $cardsInAdvDeck = GT_DBCard::getAdvDeckForPrep($this);

    if (!$cardsInAdvDeck)
      self::throw_bug_report("No cards in adventure deck");

    do {
      shuffle ($cardsInAdvDeck);
    } while ( $cardsInAdvDeck[0]['round'] != $round ); // rules : keep shuffling until
                                        // the top card matches the number of the round.
    GT_DBCard::updateAdvDeck($this, $cardsInAdvDeck);

    foreach( $players as $plId => $player ) {
        // In this foreach loop: 1. POSITION 2. CONTENT (including overlay tiles
        // and choice for aliens)
        // 1. POSITION
        // Update player position in DB and notify, a ship marker will be placed
        $orderTile = $player['turn_order'];
        $playerPos = 0 - ( ($orderTile-1) * ($round+1) );
        self::DbQuery( "UPDATE player SET player_position=$playerPos ".
                                      "WHERE player_id=$plId" );
        self::notifyAllPlayers( "placeShipMarker", "", array(
                'player_id' => $plId,
                'plPos' => $playerPos,
                'plColor' => $player['player_color'],
                ) );

      // 2. CONTENT (including overlay tiles and choice for aliens)
      // We scan this player's ship, to load battery cells and humans where there's
      // no choice. For cabins that can have an alien (life support connected),
      // we place (in DB and in UI) content units representing possible choices
      // (ask_human in any case, and ask_brown and/or ask_purple), so that we can
      // check player actions in placeCrew state and give informations to players.
      $plBoard = self::getPlayerBoard( $plId );
      $brd = $this->newPlayerBoard($plId);
      $tilesWithOverlay = array();
      $alienChoices = false;
      //sql request: INSERT INTO content (player_id, tile_id, square_x, square_y,
      //                    content_type, content_subtype, place, capacity) VALUES
      $sqlImplode = array();
      // TODO: refactor all this to be methods of GT_PlayerBoard
      foreach ( $plBoard as $plBoard_x ) {
        foreach ( $plBoard_x as $tile ) {
          $tileType = $this->tiles[ $tile['id'] ][ 'type' ];

          switch ($tileType) {
            case 'battery':
              //get tile's capacity, then load it
              $capacity = $this->tiles[ $tile['id'] ][ 'hold' ];
              for ( $place=1; $place<=$capacity; $place++ ) {
                $sqlImplode[] = "('".$plId."', '".$tile['id']."', '".$tile['x']."', '".
                                $tile['y']."', 'cell', 'cell', ".$place.", ".$capacity.")";
                        // This may change if we decide to use the same JS
                        // function for every type of content update
              }
              break;
            case 'crew': // Expansions: and case 'luxury':
              // Cabin tiles need an overlay tile (to place content (crew) that mustn't
              // rotate with the tile), so we fill an array that will be sent to client
              $tilesWithOverlay[] = array( 'id' => $tile['id'],
                                'x' => $tile['x'], 'y' => $tile['y'] );
              
              $humans = false;
              if ( $tile['id'] > 30 && $tile['id'] < 35 ) {
                  // Aliens can't go in the pilot cabin, so we place 2 humans here.
                  $humans = true;
              }
              else { // Expansions: if luxury
                  // Not a starting component, so we check if this cabin is connected
                  // to a life support
                  $brownPresent = false;
                  $purplePresent = false;
                  $nbAlienChoices = 0;

                  for ( $side=0 ; $side<=270 ; $side+=90 ) {
                    // Is there an adjacent tile on this side ?
                    if ( $adjTile = $brd->getAdjacentTile ($tile, $side) ) {
                      // There is one, so let's check if it's connected and if
                      // it's a life support and its type (color)
                      if ( in_array( self::getConnectorType( $tile, $side ), array(1,2,3) ) ) {
                        $adjTileType = $this->tiles[ $adjTile['id'] ][ 'type' ];
                        switch ( $adjTileType )
                        {
                        case 'brown':
                          if ( ! $brownPresent ) // Because we don't want to count twice
                                    // the same color, in case more than one life support
                                    // is connected to this cabin
                              $nbAlienChoices++;
                          $brownPresent = true;
                          break;
                        case 'purple':
                          if ( ! $purplePresent )
                              $nbAlienChoices++;
                          $purplePresent = true;
                          break;
                        }
                      }
                    }
                  }

                  if ( $nbAlienChoices )
                  {
                      // There's at least one life support connected, so we place content
                      // units representing possible choices
                      $alienChoices = true;
                      if ( $brownPresent ) {
                          $sqlImplode[] = "('".$plId."', '".$tile['id']."', '".
                                  $tile['x']."', '".$tile['y']."', 'crew', ".
                                  "'ask_brown', 1, ".($nbAlienChoices+1).")";
                      }
                      if ( $purplePresent ) {
                          $sqlImplode[] = "('".$plId."', '".$tile['id']."', '".
                                  $tile['x']."', '".$tile['y']."', 'crew', 'ask_purple', ".
                                  $nbAlienChoices.", ".($nbAlienChoices+1).")";
                          // We use $nbAlienChoices because if ask_brown is also present,
                          // we want place=2 and capacity=3 (ask_human is included in
                          // capacity). If only ask_purple, we want place=1 and capacity=2.
                      }
                      $sqlImplode[] = "('".$plId."', '".$tile['id']."', '".
                              $tile['x']."', '".$tile['y']."', 'crew', 'ask_human', ".
                              ($nbAlienChoices+1).", ".($nbAlienChoices+1).")";
                  }
                  else
                      $humans = true;
              }

              // Now that we have checked for life supports, we can embark
              // humans in this cabin if there's no other choice
              if ( $humans ) {
                for ( $i=1;$i<=2;$i++ ) {
                  $sqlImplode[] = "('".$plId."', '".$tile['id']."', '".
                      $tile['x']."', '".$tile['y']."', 'crew', 'human', ".$i.", 2)";
                  // This may change if we decide to use the same JS function for
                  // every type of content update, and this may change for luxury cabins
                }
              }
              break;
          } // end of switch $tileType
        }
      } // end of $plBoard scan

      // We check if this player will choose his/her alien(s) first. It's the case if:
      // - he/she has any choice to do (if it's not the case, $alienChoices is empty)
      // - and if it's the first player to have a choice in flight order (in this case,
      // $nextState is still set to crewsDone, because we scan player ships in turn
      // order, the db query was ordered by turn_order)
      if ( $alienChoices && ($nextState == 'crewsDone') ) {
        $nextState = 'nextCrew';
        $this->gamestate->changeActivePlayer( $plId );
      }

      // Database update:
      $sql = "INSERT INTO content (player_id, tile_id, square_x, square_y, content_type, ".
                  "content_subtype, place, capacity) VALUES ".implode(',',$sqlImplode);
      self::DbQuery( $sql );
      // What if this player has built a ship without any content? Possible only
      // with some expansions' ship classes, and this needs to be handled before, I
      // think (give up before start)

      // Get content to notify players (with auto-incremented content_id)
      $plContent = self::getPlContent( $plId );

      if ( $alienChoices ) {
        // We could still display something in this player's side player board, even
        // if their choice isn't made yet, to help other players choose
        self::DbQuery( "UPDATE player SET alien_choice=1 WHERE player_id=$plId" );
      }
      else {
        // This player can't place aliens, so we can calculate their strength and
        // number of crew members right now (this can help other players to choose)
        self::updNotifPlInfos( $plId, $plBoard, $plContent, true );
      }

      self::notifyAllPlayers( "updateShipContent", "", array(
                    'player_id' => $plId,
                    'tiles_with_overlay' => $tilesWithOverlay,
                    'ship_content_update' => $plContent,
                    'gamestate' => 'prepareFlight'
                    ) );
    } // end of foreach players

    // What if all players have built a ship without any content? Possible only with some
    // expansions' ship classes, and this needs to be handled before (give up before start)

    if ( self::getGameStateValue( 'testGameState' ) ) {
      $gt_state = new GT_GameState($this, $players);
      $gt_state->prepareFlight($nextState);
    }
    else {
      $this->gamestate->nextState( $nextState );
    }
  }

  // ######################### 
  function stCheckNextCrew() {
      // In turn order, we check if a player still has a choice to do
      $nextPlayer = self::getUniqueValueFromDB( "SELECT player_id FROM player ".
                    "WHERE alien_choice=1 ORDER BY turn_order LIMIT 1" );
      if ( $nextPlayer ) {
          $this->gamestate->changeActivePlayer( $nextPlayer );
          $this->gamestate->nextState( 'nextCrew' );
      }
      else {
          $this->gamestate->nextState( 'crewsDone' );
      }
  }

  function stDrawCard() {
    GT_DBPlayer::clearCardProgress($this);

    $cardOrderInFlight = self::getGameStateValue( 'cardOrderInFlight' );
    $cardOrderInFlight++;
    self::setGameStateValue( 'cardOrderInFlight', $cardOrderInFlight );
    $currentCard = self::getUniqueValueFromDB ( "SELECT card_id id FROM card ".
                                      "WHERE card_order=$cardOrderInFlight" );
    self::setGameStateValue( 'currentCard', $currentCard );

    if ( is_null($currentCard) ) { 
        // no more cards, this flight is done
        $nextState = 'cardsDone' ;
    }
    else {
        // temp, so that there is an active player when going to notImpl state
        if ( self::getUniqueValueFromDB('SELECT global_value FROM global WHERE global_id=2') == 0 )// temp
            self::activeNextPlayer();// temp
        
        $cardType = $this->card[ $currentCard ][ 'type' ];

        if ( in_array( $cardType, array( "slavers", "smugglers", "pirates" ) ) )
            $nextState = 'enemies';
        else if ( in_array( $cardType, array( "abship", "abstation" ) ) )
            $nextState = 'abandoned';
        else
            $nextState = $cardType;

        self::notifyAllPlayers( "cardDrawn",
                    clienttranslate( 'New card drawn: ${cardTypeStr}'), array(
                        'i18n' => array ( 'cardTypeStr' ),
                        'cardTypeStr' => $this->cardNames[$cardType],
                        'cardRound' => $this->card[ $currentCard ]['round'],
                        'cardId' => $currentCard,
                        ) );
    }

    $this->gamestate->nextState( $nextState );
  }

  function stStardust() {
      $players = GT_DBPlayer::getPlayersInFlight($this, '', $order='ASC');
    
      foreach ( $players as $plId => $player ) {
          $newPlPos = self::moveShip( $plId, -($player['exp_conn']), $players );
          if ( $newPlPos !== null ) {
              //update this player's position so that it is taken into account if other
              // ships move in the same action
              $players[$plId]['player_position'] = $newPlPos;
          }
      }
      $this->gamestate->nextState( 'nextCard' );
  }

  function stOpenspace() {
      $nextState = "nextCard"; // Will be changed to powerEngines if someone
                                // needs to choose if they use batteries
      $players = GT_DBPlayer::getPlayersForCard($this);

      foreach ( $players as $plId => $player ) {
          if ( $player['max_eng'] == 0 ) {
              // TODO ouch! This player has to give up
              self::notifyAllPlayers( "onlyLogMessage", clienttranslate( '${player_name} '.
                      'has no activable engine, but is lucky because giving up is not '.
                      'implemented yet'),
                      array ( 'player_name' => $player['player_name'] ) );
              GT_DBPlayer::setCardDone($this, $plId);
          }
          elseif ( $player['min_eng'] == $player['max_eng'] ) {
              // No choice to do for this player, so we move it now and notify players.
              // Do not pass $players to moveShip. We need to consider all players still in flight,
              //    $players here is only those who still have yet to act 
              $newPlPos = self::moveShip( $plId, (int)$player['min_eng'] );
              if ( $newPlPos !== null ) {
                  //update this player's position so that it is taken into account if other
                  // ships move in the same action
                  $players[$plId]['player_position'] = $newPlPos;
              }
              GT_DBPlayer::setCardDone($this, $plId);
          }
          else {
              // min and max different means that this player can activate a double
              // engine or more, so we need an activeplayer state to ask them 
              // Infos for player: here or in args? In args.
              GT_DBPlayer::setCardInProgress($this, $plId);
              $this->gamestate->changeActivePlayer( $plId );
              $nextState = "powerEngines";
              break; // End of this foreach loop because we need to ask this 
                      // player before processing the following players.
          }
      } // end of foreach players

      $this->gamestate->nextState( $nextState );
  }

  function stAbandoned() {
    $nextState = "nextCard"; // Will be changed to exploreAbandoned  if someone
                              // has a big enough crew
    $cardId = self::getGameStateValue( 'currentCard' );
    $players = GT_DBPlayer::getPlayersForCard($this);
    
    foreach ( $players as $plId => $player ) {
        if ( $player['nb_crew'] < $this->card[$cardId]['crew'] ) {
            self::notifyAllPlayers( "onlyLogMessage", clienttranslate( '${player_name} '.
                    'doesn\'t have a big enough crew to benefit from this card'),
                    array ( 'player_name' => $player['player_name'] ) );
            GT_DBPlayer::setCardDone($this, $plId);
        }
        else {
            // This player has enough crew members to use the card, so we need
            // to ask them if they want to.
            GT_DBPlayer::setCardInProgress($this, $plId);
            $this->gamestate->changeActivePlayer( $plId );
            $nextState = "exploreAbandoned";
            break; // End of this foreach loop because we need to ask this 
                    // player before processing the following players.
        }
    }

    $this->gamestate->nextState( $nextState );
  }

  function stPlanets() {
      // Setup active player to choose a planet
      $nextState = "nextCard";

      $cardId = self::getGameStateValue( 'currentCard' );
      $players = GT_DBPlayer::getPlayersForCard($this);

      foreach ( $players as $plId => $player ) {
          GT_DBPlayer::setCardInProgress($this, $plId);
          $this->gamestate->changeActivePlayer( $plId );
          $nextState = "choosePlanet";
          break; 
      }

      $this->gamestate->nextState( $nextState );
  }

  function stJourneysEnd() {
      // Rewards and penalties
      // TODO

      self::DbQuery( "UPDATE player SET still_flying=0" );

      // Is it the end of the game?
      $flight = self::getGameStateValue( 'flight' );
      $flightVariant = self::getGameStateValue( 'flight_variants' );
      $numberOfFlights = $this->flightVariant[$flightVariant]['nbOfFlights'];
      if ( $flight == $numberOfFlights )
          $this->gamestate->nextState( 'endGame' );
      else {
          self::incGameStateValue( 'flight', 1 );
          $this->gamestate->nextState( 'nextRound' );
      }
  }


//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:

        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
    */

    function zombieTurn( $state, $active_player )
    {
        $statename = $state['name'];

        if ($state['type'] == "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                    break;
            }

            return;
        }

        if ($state['type'] == "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $sql = "
                UPDATE  player
                SET     player_is_multiactive = 0
                WHERE   player_id = $active_player
            ";
            self::DbQuery( $sql );

            $this->gamestate->updateMultiactiveOrNextState( '' );
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }

///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:

        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.

    */

    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            $sql = "ALTER TABLE xxxxxxx ....";
//            self::DbQuery( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            $sql = "CREATE TABLE xxxxxxx ....";
//            self::DbQuery( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }
}
