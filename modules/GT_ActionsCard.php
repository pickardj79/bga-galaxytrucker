<?php

/* Collection of function to handle player actions in response to cards */

require_once('GT_DBPlayer.php');
require_once('GT_DBCard.php');

class GT_ActionsCard extends APP_GameClass {
    public function __construct() {
    }

    function exploreChoice($game, $plId, $cardId, $choice) {
        $player = GT_DBPlayer::getPlayer($game, $plId);

        // Sanity checks TODO: do we need to check something else?
        if ( $player['card_line_done'] != '1' )
            $game->throw_bug_report("Explore choice: wrong value for card done (".
                    var_export($player['card_line_done'], true)."). ");

        if ( !in_array( $choice, array(0,1) ) )
            $game->throw_bug_report("Explore choice: wrong value for choice (".
                    var_export($choice, true)."). ");

        if ( $choice == 0 ) {
            $game->noExplore( $plId, $player['player_name'] );
            return 'nextPlayer';
        }
        elseif ( $game->card[$cardId]['type'] == 'abship' ) {
            if ( $player['nb_crew'] > $game->card[$cardId]['crew'] ) {
                // This player has to choose which crew members to lose
                // TODO: Is a quiet notif needed?
                return 'chooseCrew';
            }
            elseif ( $player['nb_crew'] == $game->card[$cardId]['crew'] ) {
                // This player sends ALL their remaining crew members
                // Remove all crew members:
                $plyrContent = $game->newPlayerContent($plId);
                $plyrContent->loseContent($plyrContent->getContent('crew'), 'crew', true);

                $game->notifyAllPlayers( "onlyLogMessage", clienttranslate( '${player_name} '.
                    'sends their whole crew to the abandoned ship and will have to give up'),
                    array ( 'player_name' => $player['player_name'] ) );
                return 'nextCard';
            }
            else {
                $game->throw_bug_report("Explore choice: not enough crew members (".
                    var_export($player['nb_crew'], true)."). ");
            }
        }
        elseif ( $game->card[$cardId]['type'] == 'abstation' ) {
            // Check nb of crew members or not? Should have been checked in stAbandoned
            // If we need informations from the player table, we can get also nb_crew
            // Should we check if this player has NO CARGO AT ALL? (no placeGoods state)
            // Is a quiet notif needed?

            // TODO: TEMP, end of card, since placeGoods is not implemented yet:
            $nbDays = -($game->card[$cardId]['days_loss']);
            $game->moveShip( $plId, $nbDays );
            return 'placeGoods';
        }
        else
            $game->throw_bug_report("Explore choice: wrong value for card type (".
                    var_export($game->card[$cardId]['type'], true)."). ");
    }

    function crewChoice($game, $plId, $cardId, $crewChoices) {
        $plyrContent = $game->newPlayerContent( $plId );
        // Hey, wait. We need orientation for batteries, but not for crew members, right?
        // Since they're in a non-rotated overlay tile, they'll always be slided correctly.
        //$orientNeeded = false; // Will be set to true only for Slavers (sure?) and Combat Zone
        $bToCard = true; // Will be set to false only for Combat Zone
        //$tileOrient = ( ! $orientNeeded ) ? null
        //                                : self::getCollectionFromDB( "SELECT component_id, ".
        //                "orientation FROM component WHERE component_player=$plId", true );
        // TODO see if it's possible to have a common function with battChoice() and slavers and combat zones (maybe only one or two things will differ: number of batteries consistent with number of cannons, moveShip (forward) vs gainCredits and moveShip backwards, ...)


        $plyrContent->loseContent($crewChoices, 'crew', $bToCard);
        // TODO credits

        $nbDays = -($game->card[$cardId]['days_loss']);
        $game->moveShip( $plId, $nbDays );
    }


    function battChoice($game, $plId, $battChoices ) {
        $brd = $game->newPlayerBoard($plId);
        $plyrContent = $game->newPlayerContent($plId);
        $nbDoubleEngines = $brd->countDoubleEngines();
        $nbSimpleEngines = $brd->countSingleEngines();
        $nbBatt = count($battChoices);

        // Checks
        if ( count( array_unique($battChoices) ) != $nbBatt )
            $game->throw_bug_report( "Several batteries with the same id. " .var_export( $battChoices, true));

        foreach ( $battChoices as $battId ) {
            $plyrContent->checkContentById($battId, 'cell');
        }

        if ( $nbBatt > $nbDoubleEngines )
            $game->throw_bug_report("Error: too many batteries selected (more than double engines). ");


        // Calculate how far to move
        $nbDays = $nbSimpleEngines + 2*$nbBatt;
        if ( $nbDays > 0 && $plyrContent->checkIfAlien( 'brown' ) )
            $nbDays += 2;

        // else TODO if $nbDays == 0 (exception or allow them to
        // give up before the end of the card? ask vlaada / cge)

        if ( $nbBatt > 0 )
            $plyrContent->loseContent($battChoices, 'cell', false);

        $game->moveShip( $plId, $nbDays );
    }

    function planetChoice($game, $plId, $cardId, $choice) {
        $player = GT_DBPlayer::getPlayer($game, $plId);
        if (!$choice) {
            GT_DBPlayer::setCardDone($game, $plId);
            $game->notifyAllPlayers( "onlyLogMessage", clienttranslate( '${player_name} '.
                'doesn\'t stop'), array ( 'player_name' => $player['player_name'] ) );
            return 'nextPlayer'; 
        }

        // Do some checks
        if (!is_numeric($choice))
            $game->throw_bug_report_dump("Planet choice is not an int:", $choice);
        
        $choice = (int)$choice;

        $allIdx = array_keys($game->card[$cardId]['planets']);
        $chosenIdx = GT_DBCard::getActionChoices($game);
       
        if (!in_array($choice, $allIdx))
            $game->throw_bug_report("Planet choice ($choice) not possible for this planet ($cardId)");
        
        if (in_array($choice, $chosenIdx))
            $game->throw_bug_report("Planet choice ($choice) already chosen", $chosenIdx);

        // Update DB, front-end, move state to placeGoods
        GT_DBCard::setActionChoice($game, $plId, $choice);
        $game->notifyAllPlayers( "planetChoice",
            clienttranslate( '${player_name} choses planet number ${planetId}'),
            array ( 'player_name' => $player['player_name'],
                'plId' => $plId,
                'plColor' => $player['player_color'],
                'planetId' => $choice
            ) );

        return 'placeGoods';
    }

    function chooseCargo( $game, $plId, $cardId, $goodsOnTile ) {
        $player = GT_DBPlayer::getPlayer($game, $plId);
        $plyrContent = $game->newPlayerContent($plId);

        // note original content ids to remove those not chosen
        $origContentIds = array_map(function($i) { return $i['content_id']; },
            $plyrContent->getContent('goods'));

        // $goodsOnTile is all goods on the ship. Clear places in preparation
        // This allows us to not care about order of moving stuff around
        $plyrContent->clearAllPlaces('goods');

        // Split goods for each tile into new goods (from the card) or moved goods (already in DB) 
        // Rely on transactions to roll back database changes if any validation fails
        $seenPlanetIdx = array();
        $allMovedGoodsIds = array();
        $movedTileContent = array();
        $newTileContent = array();
        foreach ( $goodsOnTile as $tile => $goods) {
            $newGoods = array(); // array of goods subtypes
            $movedGoodsIds = array();
            foreach ( $goods as $goodId ) {
                if (strpos($goodId,"planetcargo")) {
                    // goodId on card: cargo_planetcargo_X_Y: X is planet #, Y is goods #
                    list($t1, $t2, $planet, $idx) = explode("_", $goodId);
                    if ($planet != $player['card_action_choice'])
                        $game->throw_bug_report("Cargo ($goodId) has wrong planet, should be ({$player['card_action_choice']})", $goodsOnTile);
                    if (in_array($idx, $seenPlanetIdx))
                        $game->throw_bug_report("Cargo idx ($idx) appears twice", $goodsOnTile);
                    $seenPlanetIdx[] = $idx;
                    $newGoods[] = $game->card[$cardId]['planets'][$planet][$idx - 1];
                }
                else {
                    $id = explode("_", $goodId)[1];
                    $movedGoodsIds[] = $id;
                    unset($unseenContent[$id]);
                }
            }
            $tileId = explode("_", $tile)[1];
            if ($movedGoodsIds)
                $plyrContent->moveContent($tileId, 'goods', $movedGoodsIds);
            if ($newGoods)
                $newGoodsIds = $plyrContent->newContent($tileId, 'goods', null, $newGoods);
            
            $allMovedGoodsIds = array_merge($allMovedGoodsIds, $movedGoodsIds);

            // prep args to send back to front-end
            $movedTileContent[$tile] = $movedGoodsIds;
            $newTileContent[$tile] = array_map(
                function($id) { return 'content_' . $id; },
                $newGoodsIds
            );
        }

        // remove existing goods not "moved". They went to the trash
        $toDel = array_diff($origContentIds, $allMovedGoodsIds);
        if ($toDel) {
            $sql = "DELETE FROM content WHERE content_id IN (" . implode(",", $toDel) . ")";
            $game->log("Deleting with $sql");
            $game->DbQuery($sql);
        }

        // Do a final consistency check/validation on the database 
        $game->newPlayerContent($plId)->checkAll($game->newPlayerBoard($plId));

        // notifyAllPlayers
        // send goodsOnTile ($tileId -> $goodId), $toDel(array($goodId)) 
        $game->notifyAllPlayers('chooseCargo',
            clienttranslate( '${player_name} places cargo from planet number ${planet_number}'),
            array( 'player_name' => $player['player_name'],
                'planet_number' => $player['card_action_choice'],
                'movedTileContent' => $movedTileContent,
                'newTileContent' => $newTileContent,
                'deleteContent' => $toDel
            )
        );
    }

}

?>