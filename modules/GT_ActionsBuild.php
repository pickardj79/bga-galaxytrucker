<?php

require_once('GT_DBContent.php');

class GT_ActionsBuild extends APP_GameClass {
    public function __construct($game, $plId) {
        $this->game = $game;
        $this->plId = $plId;
    }

    /// ############### Player Actions #########################
    function pickTile() {
        // Pick a random tile from the pile

        $game = $this->game;
        $plId = $this->plId;

        $this->checkIfTileInHand ( 'Pick tile' );

        # Update DB first to avoid race condition between looking in DB then updating 
        $game->DbQuery("UPDATE component SET component_player=$plId "
            . "WHERE component_player IS NULL ORDER BY RAND() LIMIT 1"
        );
        $pickedTile = $this->getTileInHand();

        // $pickedTile = $game->getUniqueValueFromDB( "SELECT component_id FROM component ".
                    // "WHERE component_player IS NULL ORDER BY RAND() LIMIT 1" );// TODO replace RAND
        if ( $pickedTile !== null ) {
            // $game->DbQuery( "UPDATE component SET component_player={$this->plId} ".
                            // "WHERE component_id=$pickedTile" );
            $this->resetUndoPossible();
        }
        // If $pickedTile is null, there's no more unrevealed tile, and the client will
        // disconnect clickable_pile

        $game->notifyPlayer( $plId, "pickedTilePl", '', array( 'pickedTile' => $pickedTile ) );
        $game->notifyAllPlayers( "pickedTile", '', array() );
    }

    function pickRevealed( $tile_id ) {
  
        $game = $this->game;

        $this->checkIfTileInHand ( 'Pick revealed' );
  
        $location = $game->getUniqueValueFromDB( "SELECT component_player FROM component ".
                                                "WHERE component_id=$tile_id" );
        if ( $location !== '-1' )
            $game->user_exception("This component has already been taken by someone else");
  
        $game->DbQuery( "UPDATE component SET component_player={$this->plId} ".
                                              "WHERE component_id=$tile_id" );
        $game->DbQuery( "UPDATE revealed_pile SET tile_id=NULL WHERE tile_id=$tile_id" );
        $this->resetUndoPossible();
        
        $game->notifyAllPlayers( "pickedRevealed", '', array(
                                 'tile_id' => $tile_id,
                                 'player_id' => $this->plId) );
    }

    function pickAside($tile_id) {

        $game = $this->game;
        $this->checkIfTileInHand ( 'Pick aside' );
        
        // Is this tile in discard?
        $pickedTile = $game->getObjectFromDB( "SELECT * FROM component ".
                                              "WHERE component_id=$tile_id" );
        $x = $pickedTile['component_x'];
        $aside = $pickedTile['aside_discard'];
        if ( $pickedTile['component_player'] != $this->plId ||
                $aside !== "1" ||
                ( $x != "-1" && $x != "-2" ) ) {
            $game->throw_bug_report("This tile is not in your discard." .
                " (pl: {$this->plId} x: $x aside: $aside)") ;
        }

        $game->DbQuery( "UPDATE component SET component_x=NULL, component_y=NULL ".
                        "WHERE component_id=$tile_id" );
        $this->resetUndoPossible();
        $game->notifyAllPlayers( "pickedAside", '', array(
                                            'tile_id' => $tile_id,
                                            'player_id' => $this->plId,
                                            'discardSquare' => $x ) );

    }

    function pickLastPlaced( $tile_id ) {

        $game = $this->game;
        $plId = $this->plId;

        // Sanity checks:
        // Not necessary to check if a tile is in hand, because in this case,
        // undo_possible should be null
        // Is this tile the last placed tile, and is it still possible to take it back ?
        $undoTile = $game->getUniqueValueFromDB ( "SELECT undo_possible FROM player ".
                                                    "WHERE player_id=$plId" );
        if ( $undoTile !== $tile_id ) { // both are strings
            $game->throw_bug_report("You can't take back this tile. "
                    ." (pl: $plId tile id: $tile_id undo: $undoTile)" );
        }

        // We must get x and y coords for this tile, so that the client can connect again
        // the corresponding square to onPlaceTile, and also aside_discard, so that the
        // client knows if this tile was set aside before
        $pickedTile = $game->getObjectFromDB ( "SELECT component_x x, component_y y, ".
                            "aside_discard aside FROM component WHERE component_id=$tile_id" );
        // another sanity check?
        if ( !($pickedTile['x']>0) || !($pickedTile['y']>0) ) {
            $game->throw_bug_report("PickLastPlaced: tile coords are ".
                    $pickedTile['x']." and ".$pickedTile['y']);
        }
                    
        $game->DbQuery( "UPDATE component SET component_x=NULL, component_y=NULL ".
                        "WHERE component_id=$tile_id" );
        $this->resetUndoPossible( 'lastPlaced' );
        $game->notifyAllPlayers( "pickedLastPlaced", '', array(
                                            'tile_id' => $tile_id,
                                            'x' => $pickedTile['x'],
                                            'y' => $pickedTile['y'],
                                            'aside' => $pickedTile['aside'],
                                            'player_id' => $plId) );
    }

    function dropTile( $tile_id ) {

        // Various checks
        $location = $this->game->getObjectFromDB( "SELECT component_player player, component_x x, ".
                "aside_discard aside FROM component WHERE component_id=$tile_id" );
        if ( $location['player'] != $this->plId || $location['x'] !== null )
            $game->throw_bug_report("Drop tile: you don't have this tile in hand.");

        if ( $location['aside'] !== null )
            $game->throw_bug_report("You can't place a previously set aside tile here.");

        $this->placeInRevealed( $tile_id ); // DB and client notif
    }


    function placeTile( $component_id, $x, $y, $o) {
        $game = $this->game;
        $player_id = $this->plId;

        $cards = null;
        $allTiles = $game->getCollectionFromDB( "SELECT component_id id, component_x x, component_y y, ".
                            "aside_discard aside FROM component WHERE component_player=$player_id" );
                            //TODO maybe aside_discard is not needed
                            // TODO to reduce the number of database requests, we could use
                            // getPlayerBoard (needed later) instead of this custom request

        // Various checks
        if ( !array_key_exists($component_id, $allTiles) || $allTiles[$component_id]['x'] !== null )
            $game->throw_bug_report("Place tile: You don't have this tile ($component_id) in hand.");

        // TODO We also need to check if the tile is placed on a valid square (not outside
        // the board or the discard layer 1)

        $firstPlacedTile = true;
        foreach ( $allTiles as $tile ) {
            if ( $tile['x']==$x && $tile['y']==$y )
                $game->throw_bug_report("There is already a tile ({$tile['id']} on square $x $y");

            // At the same time, we check if this player has already placed at least one
            // tile (not a starting cabin, id 31 to 34), if it's not the case we'll send
            // them the revealed cards' ids because they are now allowed to look at them
            if ( $tile['x']>0 && !array_key_exists($tile['id'], $game->start_tiles))
                $firstPlacedTile = false;
        }

        $brd = $game->newPlayerBoard($player_id);
        $tileToCheck = array( 'x' => $x, 'y' => $y, 'id' => $component_id, 'o' => $o );
        if ( ! $brd->checkIfTileConnected( $tileToCheck ) )
            $game->throw_bug_report("Wrong tile placement : this tile isn't connected to your ship");

        $sql = "UPDATE component "
            . "SET component_x=$x, component_y=$y, component_orientation=$o "
            . "WHERE component_id=$component_id";
        $game->DbQuery( $sql );

        $game->DbQuery( "UPDATE player SET undo_possible=$component_id ".
                        "WHERE player_id=$player_id" );

        $game->notifyAllPlayers( "placedTile", '',
                array( 'player_id' => $player_id,
                    'component_id' => $component_id,
                    'x' => $x,
                    'y' => $y, 
                    'o' => $o, ) );
        
        return $firstPlacedTile;
    }

    function discardTile($component_id, $x, $y, $o) {
        // Discarded tiles have $x == -1 or -2
        $game = $this->game;
        $player_id = $this->plId;

        if ( $x !== "-1" && $x !== "-2" )
            $game->throw_bug_report("x is $x, should be -1 or -2 for a tile that is set aside.");

        if ( $o !== "0" ) // Do we really need to raise an exception for that? We could
                            // log it and set $o to 0.
            $game->throw_bug_report("tile orient is $o, should be 0.");

        $setAsideTiles = $game->getCollectionFromDB( "SELECT component_id id, component_x x, component_y y, ".
                            "aside_discard aside FROM component " . 
                            "WHERE component_player=$player_id and component_x in (-1, -2)" );
        if ($setAsideTiles) {
            $t = $setAsideTiles[0];
            $game->throw_bug_report("There is already a tile ({$t['id']}) on square {$t['x']} in discard");
        }

        $sql = "UPDATE component SET component_x=$x, component_y=NULL, "
            . "component_orientation=$o, aside_discard=1 "
            . "WHERE component_id = $component_id";
        $game->DbQuery( $sql );

        $game->notifyAllPlayers( "placedTile", '',
                array( 'player_id' => $player_id,
                    'component_id' => $component_id,
                    'x' => $x,
                    'y' => 'discard',
                    'o' => $o, ) );
    }

    function flipTimer( $timerPlace, $player_name ) {
        // $timerPlace is the number of the circle where the timer WAS (and currently still is),
        // not the one where it WILL BE

        $game = $this->game;
        $player_id = $this->plId;

        $elapsedTime = ( time() - $game->getGameStateValue('timerStartTime') );

        // TODO : throw a user exception instead of a system exception if the timer
        // was just flipped (less than 2s?)
        if ( $timerPlace < 1 || $timerPlace !== $game->getGameStateValue('timerPlace') )
            $game->throw_bug_report_dump("Flip timer: wrong value for timerPlace", $timePlace);

        if ( $elapsedTime < 90 )
            $game->throw_bug_report("Flip timer: timer is not finished: $elapsedTime seconds. ");

        $turnOrder = $game->getUniqueValueFromDB( "SELECT turn_order FROM player ".
                                                    "WHERE player_id=$player_id" );
        if ( $timerPlace == 1 && $turnOrder === null )
            $game->throw_bug_report("Flip timer: you can't flip the timer on ".
                "the last space when you are still building your ship. ");

        // Set new timer place and start time in DB
        $game->setGameStateValue( 'timerPlace', $timerPlace-1 );
        $game->setGameStateValue( 'timerStartTime', time() );
        // Notify players
        $game->notifyAllPlayers( "timerFlipped",
                    clienttranslate( '${player_name} has flipped the timer.'),
                    array( 'player_id' => $player_id,
                        'player_name' => $player_name,
                        'timerPlace' => $timerPlace-1 ) );
    }
    

    function timeFinished() {
        $game = $this->game;

        $players = $game->loadPlayersBasicInfos(); // maybe useless

        // Check if time is really finished
        $elapsedTime = ( time() - $game->getGameStateValue('timerStartTime') );
        $timerPlace = $game->getGameStateValue('timerPlace');
        if ( $timerPlace !== "0" || $elapsedTime < 89 )
            $game->throw_bug_report("Flip timer: timer is not finished: $elapsedTime seconds. ");

        // TODO We should still send a notif with remaining time to the client, shouldn't we ?
        // Because if there's a bug that affect all players, nothing will stop the building 
        // and they'll be able to build forever.
        // OR: add a check (set a flag here) to placing tiles that prevents placing tiles after timeFinished

        // Do players still have a tile in hand?
        // (Is it better to do this here or in stTakeOrderTiles? I think it's ok
        // to deal with this here
        $tilesInHand = $game->getCollectionFromDB( "SELECT component_id id, ".
                "component_player player, aside_discard aside FROM component ".
                "WHERE component_player>0 AND component_x IS NULL" );
        foreach ( $tilesInHand as $tileId => $tile ) {
            $plId = $tile['player'];
            if ( $tile['aside'] == 1 ) {
                // This tile was set aside before, so it must go in this player's discard zone
                // In order to place it in a free square (there must be at least one, since
                // the tile in hand was in the discard before), we must first check
                // if there's already a tile in discard
                $occupiedSquare = $game->getUniqueValueFromDB( "SELECT component_x x FROM "
                    . "component WHERE component_player=$plId AND aside_discard=1 AND component_x<0" );

                // No tile in discard, so the tile in hand goes on the 1st square
                if ( !$occupiedSquare ) $squareToDiscardTo = -1;
                elseif ( $occupiedSquare == -1 ) $squareToDiscardTo = -2;
                elseif ( $occupiedSquare == -2 ) $squareToDiscardTo = -1;
                else $game->throw_bug_report_dump("Bad value for \$occupiedSquare: ", $occupiedSquare);

                $game->DbQuery( "UPDATE component SET component_x=$squareToDiscardTo, ".
                            "component_orientation=0 WHERE component_id=$tileId" );
                $game->notifyAllPlayers( "placedTile", '',
                        array( 'player_id' => $plId,
                            'component_id' => $tileId,
                            'x' => $squareToDiscardTo,
                            'y' => 'discard',
                            'o' => 0 ) );
            }
            else {
                // This tile was not set aside before, so we'll drop it in revealed pile
                $this->placeInRevealed( $tileId );
            }
        }

        $game->DbQuery( "UPDATE player SET undo_possible=NULL" );
    }

    function finishShip( $orderTile ) {

        $game = $this->game;
        $player_id = $this->plId;

        $players = $game->loadPlayersBasicInfos();
        $round = $game->getGameStateValue('round');

        // Check if order tile is still available
        $count = $game->getUniqueValueFromDB( "SELECT COUNT(player_id) FROM player ".
                                                "WHERE turn_order=$orderTile" );
        if ( $count !== '0' )
            $game->throw_bug_report("This order tile is not available.");

        // Sanity check: is there a tile in hand?
        if ( $this->getTileInHand() !== null )
            $game->throw_bug_report("You still have a tile in hand.");

        // Set turn order according to the order tile taken and the round. Player position
        // will be set later, because we want it to be null until stPrepareFlight,
        // when ship markers are placed on the board (if getAllDatas get a null
        // value for player_position, the client won't display ship markers,
        // which is what we want before prepareFlight)
        $game->DbQuery( "UPDATE player SET turn_order=$orderTile, ".
                        "undo_possible=NULL WHERE player_id=$player_id" );

        $game->DbQuery( "UPDATE component SET aside_discard=NULL ".
                        "WHERE component_x>0 AND component_player=$player_id" ); 

        $game->notifyAllPlayers( "finishedShip",
                clienttranslate( '${player_name} has finished thier ship!'.
                                    ' Order tile: ${orderTile}' ),
                array( 'player_id' => $player_id,
                        'player_name' => $players[$player_id]['player_name'],
                        'orderTile' => $orderTile,
        ) );
    }


    function crewPlacementDone( $alienChoices, $player_name ) {
        $game = $this->game;
        $plId = $this->plId;

        $contAsk = $game->getCollectionFromDB( "SELECT content_id, tile_id, square_x, ".
                        "square_y, content_subtype FROM content WHERE player_id=$plId ".
                        "AND content_subtype IN ('ask_brown','ask_purple')" );

        // Check if player input is correct
        $brown = 0;
        $purple = 0;
        foreach ( $alienChoices as $contId ) {
            if ( !array_key_exists( $contId, $contAsk ) )
                $game->throw_bug_report("Wrong content id ($contId), not an alien choice for you.");

            if ( $contAsk[ $contId ][ 'content_subtype' ] == 'ask_brown' )
                $brown++;
            if ( $contAsk[ $contId ][ 'content_subtype' ] == 'ask_purple' )
                $purple++;
        }

        if ( $brown > 1 || $purple > 1 )
            $game->throw_bug_report("You can't have several aliens of the same color.");

        // TODO check if no more than a single alien choice per tile

        // Add humans or aliens in relevant cabins
        $sqlImplode = array();

        $tilesToFill = array();
        $tilesWithAlien = array();
        foreach ( $contAsk as $contId => $content ) {
            $tileId = $content['tile_id'];
            // Fill an array with all the tiles where there is a choice. It will be used
            // to know what content to get from DB at the end of crewPlacementDone(), and
            // to place human crew where no alien is chosen.
            if ( ! array_key_exists( $tileId, $tilesToFill ) )
                $tilesToFill[$tileId] = $content; 

            if ( in_array( $contId, $alienChoices ) ) {
                $tilesWithAlien[] = $tileId;
                // Place an alien of the chosen color
                $color = substr( $content['content_subtype'], 4 ); // remove 'ask_'
                // Warning: $color will be used in notification message
                $sqlImplode[] = GT_DBContent::contentValueSql(
                    $game, $plId, $tileId, 
                    $content['square_x'], $content['square_y'],
                    'crew', $color, 1, 1
                );
            }
        }
        // Place 2 humans in other cabins
        forEach ( $tilesToFill as $tileId => $content ) {
            if ( ! in_array( $tileId, $tilesWithAlien ) ) {
                for ( $i=1;$i<=2;$i++ ) {
                    $sqlImplode[] = GT_DBContent::contentValueSql(
                        $game, $plId, $tileId, 
                        $content['square_x'], $content['square_y'],
                        'crew', 'human', $i, 2
                    );
                }
            }
        }

        // Remove alien choices in "content" DB table
        $game->DbQuery( "DELETE FROM content WHERE player_id=$plId AND ".
                        "content_subtype LIKE 'ask_%'" );
        // Add aliens and humans
        $sql = "INSERT INTO content (player_id, tile_id, square_x, square_y, ".
                                    "content_type, content_subtype, place, capacity) ".
                                    "VALUES ".implode( ',', $sqlImplode );
        $game->DbQuery( $sql );
        // Get new content (with auto-incremented content_id) to notify players
        $shipContentUpdate = $game->getCollectionFromDB( "SELECT * FROM content ".
                "WHERE tile_id IN (".implode( ',', array_keys($tilesToFill) ).")" );

        // This player has chosen his/her aliens:
        $game->DbQuery( "UPDATE player SET alien_choice=0 WHERE player_id=".$plId );

        // Notify all players
        $nbAliens = count( $alienChoices );
        if ( $nbAliens == 0 )
            $notifyText = clienttranslate( '${player_name} has chosen no alien.' );
        elseif ( $nbAliens == 1 )
            $notifyText = clienttranslate( '${player_name} has chosen one alien: ').
                                                $game->translated[$color].'.';
        elseif ( $nbAliens == 2 )
            $notifyText = clienttranslate( '${player_name} has chosen a brown alien '.
                                                'and a purple alien.' );

        $game->notifyAllPlayers( "updateShipContent", $notifyText, array( // on utilise notif_updateShipContent ou pas ?
                        'player_name' => $player_name,
                        'player_id' => $plId,
                        'ship_content_update' => $shipContentUpdate,
                        'gamestate' => 'placeCrew'
        ) );

    }


    /// ############### Helpers #########################
    function resetUndoPossible( $action="" ) {
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
        
        $game = $this->game;
        if ( $action !== 'lastPlaced' ) {
            $lastPlaced = $game->getUniqueValueFromDB( "SELECT undo_possible FROM player ".
                                        "WHERE player_id={$this->plId}" );
            if ( $lastPlaced !== null ) {
                $game->DbQuery( "UPDATE component SET aside_discard=NULL ".
                                "WHERE component_id=$lastPlaced" );
            }
        }
        $game->DbQuery( "UPDATE player SET undo_possible=NULL WHERE player_id={$this->plId}" );
    }

    function checkIfTileInHand( $caller ){
        if ( $this->getTileInHand() !== null )
            $this->game->throw_bug_report("$caller: you have a tile in hand.");
    }

    function getTileInHand() {
        return $this->game->getUniqueValueFromDB( "SELECT * FROM component ".
            "WHERE component_player={$this->plId} AND component_x IS NULL" );
    }

    function placeInRevealed( $tile_id ){
        $game = $this->game;

        // Get the first empty space in the revealed pile
        $firstAvailSp = $game->getUniqueValueFromDB( "SELECT space FROM revealed_pile ".
                                    "WHERE tile_id IS NULL ORDER BY space LIMIT 1" );
        if ( $firstAvailSp === null ) {
            $game->throw_bug_report( "Error: no empty space found in revealed pile ".
                            "(\$firstAvailSp is null). ");
        }

        // Place dropped tile in revealed pile
        $game->DbQuery( "UPDATE component SET component_player=-1, component_orientation=0 ".
                        "WHERE component_id=$tile_id" );
        $game->DbQuery( "UPDATE revealed_pile SET tile_id=$tile_id WHERE space=$firstAvailSp" );

        $game->notifyAllPlayers( "droppedTile", '', array(
                                'tile_id' => $tile_id,
                                'player_id' => $this->plId,
                                'placeInRevealedPile' => $firstAvailSp ) );
    }

}

?>