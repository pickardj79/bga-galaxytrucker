<?php

/* Collection of function to handle player actions in response to cards */

require_once('GT_DBPlayer.php');

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
                $plyrContent->loseContent($plyrContent->getCrew(), 'crew', true);

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
        $plyrContent = $game->getPlayerContent( $plId );
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
        $plyrContent = $game->newPlayerContent($plId, $plContent);
        $nbDoubleEngines = $brd->countDoubleEngines();
        $nbSimpleEngines = $brd->countSingleEngines();

        // Checks
        if ( count( array_unique($battChoices) ) != $nbBatt )
            $game->throw_bug_report( "Several batteries with the same id. " .var_export( $battChoices, true));

        foreach ( $battChoices as $battId ) {
            $plyrContent->checkContent($battId, 'cell');
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

}

?>