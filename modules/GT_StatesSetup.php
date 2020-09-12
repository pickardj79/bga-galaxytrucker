<?php

/* Collection of functions to handle states associated with setting up the game /round */

require_once('GT_DBCard.php');
require_once('GT_DBContent.php');

class GT_StatesSetup extends APP_GameClass {
    public function __construct() {
    }


    // ########################################################
    // ################### stPrepareRound ##################

    function stPrepareRound($game, $players) {
        // Are globals 'flight' and 'round' updated here or in stJourneysEnd()?
        // flight in stJourneysEnd(), but 'round' must be set here, since for the
        // first round it can't be set in setupNewGame()
        $game->log("Starting stPrepareRound");
        $flight = $game->getGameStateValue( 'flight' );
        $flightVariant = $game->getGameStateValue( 'flight_variants' );
        $round = $game->flightVariant[$flightVariant][$flight]['round'];
        $game->setGameStateValue( 'round', $round );
        $game->setGameStateValue( 'timerPlace', $round ); // So that the timer can be displayed
                                    // by getAllDatas before it is started (in stBuildPhase)
        $game->setGameStateValue( 'cardOrderInFlight', 0 );
        $game->setGameStateValue( 'currentCard', -1 );
        // We need the ship class at the end of stPrepareRound to notify players
        $shipClass = $game->flightVariant[$flightVariant][$flight]['shipClass'];

        // Reset some values and clean content table
        //    still_flying may need to be set to 0 in
        //    the highly improbable case of a player having built a ship without
        //    humans (with expansions' ship classes without starting component)
        $game->DbQuery( "UPDATE player SET still_flying=1, turn_order=NULL, player_position=NULL, nb_crew=NULL, ".
            "exp_conn=NULL, min_eng=NULL, max_eng=NULL, min_cann_x2=NULL, max_cann_x2=NULL" );
        $game->DbQuery( "DELETE FROM content" ); 
        $game->setGameStateValue( 'overlayTilesPlaced', 0 );

        // Prepare cards
        // Used cards are not used in next rounds (rules)
        $game->DbQuery( "UPDATE card SET used=1 WHERE card_order IS NOT NULL" );
        $game->DbQuery( "UPDATE card SET card_order=NULL" );

        switch ( $round ) {
        case 1:
            self::cardsIntoPile($game, 1, 2 );
            break;

        case 2:
            self::cardsIntoPile($game, 1, 1 );
            self::cardsIntoPile($game, 2, 2 );
            break;

        case 3:
            self::cardsIntoPile($game, 1, 1 );
            self::cardsIntoPile($game, 2, 1 );
            self::cardsIntoPile($game, 3, 2 );
            break;
        
        default:
            throw new BgaVisibleSystemException("Invalid round `$round` in stPrepareRound");
        }

        // Prepare ships
        $game->DbQuery( "UPDATE component SET component_player=NULL, component_x=NULL, ".
                        "component_y=NULL, component_orientation=0, aside_discard=NULL" );
        $game->DbQuery( "UPDATE revealed_pile SET tile_id=NULL" );

        // Starting crew components
        $startingTiles = array();
        foreach( $players as $player_id => $player ) {
            $id = array_search( $player['player_color'], $game->start_tiles );
            $game->DbQuery( "UPDATE component SET component_x=7, component_y=7, ".
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
        $game->DbQuery( "UPDATE component SET component_player=0 WHERE component_player IS NULL ".
                        "AND component_id>=31 AND component_id<=34" );

        ////// Prepare notif
        // Get cards that can be looked at by players
        $cardsInPiles = array();
        for( $i=1 ; $i<=3 ; $i++ )
            $cardsInPiles[$i] = GT_DBCard::getAdvDeckPile($game, $i);

        // Get number of tiles in face down pile
        $tilesLeft = $game->getUniqueValueFromDB( "SELECT COUNT(component_id) ".
                            "FROM component WHERE component_player IS NULL" );
        // Start timer
        $game->setGameStateValue( 'timerPlace', $round );
    //    $startTime = time();
        // Globals are stored as signed INT, so this will be problematic around year 2038 :)
        // except if we substract a constant number of seconds each time we call time().
    //    $game->setGameStateValue( 'timerStartTime', $startTime ); // Pourquoi c'est là ET ds stBuildPhase ? oubli ?
    //    $game->setGameStateValue( 'buildingStartTime', $startTime );

        ////// Notify all
        $game->notifyAllPlayers( "newRound", "", array(
                            'shipClass' => $shipClass,
                            'tilesLeft' => $tilesLeft,
                            'startingTiles' => $startingTiles,
                            'nbPlayers' => count($players),
                            'flight' => $flight,
                            'round' => $round,
                            ) );
    }
    // ########################################################
    // ################### stPrepareRound ##################

    function stPrepareFlight($game, $players) {
        // This query's ordered by turn_order so that we can know which player to activate
        // first for alien placement (if he/she has a choice to do)
        $round = $game->getGameStateValue( 'round' );
        $nextState = 'crewsDone'; // will be changed to 'nextCrew' in the loop below only if
                                // we need to ask at least one player for alien choice
        $game->setGameStateValue( 'overlayTilesPlaced', 1 );

        // Shuffle cards in pile to create the adventure deck
        $cardsInAdvDeck = GT_DBCard::getAdvDeckForPrep($game);

        if (!$cardsInAdvDeck)
            $game->throw_bug_report("No cards in adventure deck");

        do {
            shuffle ($cardsInAdvDeck);
        } while ( $cardsInAdvDeck[0]['round'] != $round ); // rules : keep shuffling until
                                            // the top card matches the number of the round.
        GT_DBCard::updateAdvDeck($game, $cardsInAdvDeck);

        foreach( $players as $plId => $player ) {
            // In this foreach loop: 1. POSITION 2. CONTENT (including overlay tiles
            // and choice for aliens)
            // 1. POSITION
            // Update player position in DB and notify, a ship marker will be placed
            $orderTile = $player['turn_order'];
            $playerPos = 0 - ( ($orderTile-1) * ($round+1) );
            $game->DbQuery( "UPDATE player SET player_position=$playerPos ".
                                        "WHERE player_id=$plId" );
            $game->notifyAllPlayers( "placeShipMarker", "", array(
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
        $plBoard = $game->getPlayerBoard( $plId );
        $brd = $game->newPlayerBoard($plId);
        $tilesWithOverlay = array();
        $alienChoices = false;
        //sql request: INSERT INTO content (player_id, tile_id, square_x, square_y,
        //                    content_type, content_subtype, place, capacity) VALUES
        $sqlImplode = array();
        // TODO: refactor all this to be methods of GT_PlayerBoard and GT_ActionsBuild.crewPlacementDone
        foreach ( $plBoard as $plBoard_x ) {
            foreach ( $plBoard_x as $tile ) {
                $tileType = $game->tiles[ $tile['id'] ][ 'type' ];

                switch ($tileType) {
                case 'battery':
                    //get tile's capacity, then load it
                    $capacity = $game->tiles[ $tile['id'] ][ 'hold' ];
                    for ( $place=1; $place<=$capacity; $place++ ) {
                        $sqlImplode[] = GT_DBContent::contentValueSql(
                            $game, $plId, $tile['id'], $tile['x'], $tile['y'], 
                            'cell', 'cell', $place, $capacity
                        );
                        
                    }
                    break;
                case 'crew': // Expansions: and case 'luxury':
                    // Cabin tiles need an overlay tile (to place content (crew) that mustn't
                    // rotate with the tile), so we fill an array that will be sent to client
                    $tilesWithOverlay[] = array( 'id' => $tile['id'],
                                        'x' => $tile['x'], 'y' => $tile['y'] );
                    
                    $humans = false;
                    if ( array_key_exists($tile['id'], $game->start_tiles) ) {
                        // Aliens can't go in the pilot cabin, so we place 2 humans here.
                        $humans = true;
                    }
                    else { // Expansions: if luxury
                        // Not a starting component, so we check if this cabin is connected
                        // to a life support
                        $brownPresent = false;
                        $purplePresent = false;
                        $nbAlienChoices = 0;

                        foreach ($brd->getConnectedTiles($tile) as $adjTile ) {
                            switch($game->getTileType($adjTile['id'])) {
                                case 'brown':
                                    if ( ! $brownPresent ) // Because we don't want to count twice
                                            // the same color, in case more than one life support
                                            // is connected to this cabin
                                        $nbAlienChoices++;
                                        $brownPresent = true;
                                    break;
                                case 'purple':
                                    if ( ! $purplePresent ) $nbAlienChoices++;
                                    $purplePresent = true;
                                    break;
                            }
                        }

                        if ( $nbAlienChoices ) {
                            // There's at least one life support connected, so we place content
                            // units representing possible choices
                            $alienChoices = true;
                            $curPlace = 1;
                            $capacity = $nbAlienChoices+1; // include human choice
                            if ( $brownPresent ) {
                                $sqlImplode[] = GT_DBContent::contentValueSql(
                                    $game, $plId, $tile['id'], $tile['x'], $tile['y'], 
                                    'crew', 'ask_brown', $curPlace++, $capacity
                                );
                            }
                            if ( $purplePresent ) {
                                $sqlImplode[] = GT_DBContent::contentValueSql(
                                    $game, $plId, $tile['id'], $tile['x'], $tile['y'], 
                                    'crew', 'ask_purple', $curPlace++, $capacity 
                                );

                            }
                            $sqlImplode[] = GT_DBContent::contentValueSql(
                                $game, $plId, $tile['id'], $tile['x'], $tile['y'], 
                                'crew', 'ask_human', $curPlace++, $capacity 
                            );
                        }
                        else
                            $humans = true;
                    }

                    // Now that we have checked for life supports, we can embark
                    // humans in this cabin if there's no other choice
                    if ( $humans ) {
                        for ( $i=1;$i<=2;$i++ ) {
                            $sqlImplode[] = GT_DBContent::contentValueSql(
                                $game, $plId, $tile['id'], $tile['x'], $tile['y'], 
                                'crew', 'human', $i, 2 
                            );
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
            $game->gamestate->changeActivePlayer( $plId );
        }

        // Database update:
        $sql = "INSERT INTO content (player_id, tile_id, square_x, square_y, content_type, ".
                    "content_subtype, place, capacity) VALUES ".implode(',',$sqlImplode);
        $game->DbQuery( $sql );
        // What if this player has built a ship without any content? Possible only
        // with some expansions' ship classes, and this needs to be handled before, I
        // think (give up before start)

        // Get content to notify players (with auto-incremented content_id)
        $plContent = $game->getPlContent( $plId );

        if ( $alienChoices ) {
            // We could still display something in this player's side player board, even
            // if their choice isn't made yet, to help other players choose
            $game->DbQuery( "UPDATE player SET alien_choice=1 WHERE player_id=$plId" );
        }
        else {
            // This player can't place aliens, so we can calculate their strength and
            // number of crew members right now (this can help other players to choose)
            $game->updNotifPlInfos( $plId, $plBoard, $plContent, true );
        }

        $game->notifyAllPlayers( "updateShipContent", "", array(
                        'player_id' => $plId,
                        'tiles_with_overlay' => $tilesWithOverlay,
                        'ship_content_update' => $plContent,
                        'gamestate' => 'prepareFlight'
                        ) );
        } // end of foreach players

        // What if all players have built a ship without any content? Possible only with some
        // expansions' ship classes, and this needs to be handled before (give up before start)
        return $nextState;
    }

    // ########################################################
    // ################### HELPER FUNCTIONS ##################

    function cardsIntoPile( $game, $cdRound, $nbCards ) {
        for ( $i=1; $i<=4; $i++ ) {
            // TODO replace RAND
            $game->DbQuery( "UPDATE card SET card_pile=$i WHERE card_round=$cdRound ".
                        "AND card_pile IS NULL AND used=0 ORDER BY RAND() LIMIT $nbCards" );
        }
    }

}

?>