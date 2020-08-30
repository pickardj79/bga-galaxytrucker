<?php

/* Collection of functions to handle states associated with cards */

require_once('GT_DBPlayer.php');

class GT_StatesCard extends APP_GameClass {
    public function __construct() {
    }

    function stDrawCard($game) {

        $cardOrderInFlight = $game->getGameStateValue( 'cardOrderInFlight' );
        $cardOrderInFlight++;
        $game->setGameStateValue( 'cardOrderInFlight', $cardOrderInFlight );
        $currentCard = $game->getUniqueValueFromDB ( "SELECT card_id id FROM card ".
                                        "WHERE card_order=$cardOrderInFlight" );
        $game->setGameStateValue( 'currentCard', $currentCard );

        if ( is_null($currentCard) ) { 
            // no more cards, this flight is done
            $nextState = 'cardsDone' ;
        }
        else {
            // temp, so that there is an active player when going to notImpl state
            if ( $game->getUniqueValueFromDB('SELECT global_value FROM global WHERE global_id=2') == 0 )// temp
                $game->myActiveNextPlayer();// temp
            
            $cardType = $game->card[ $currentCard ][ 'type' ];

            if ( in_array( $cardType, array( "slavers", "smugglers", "pirates" ) ) )
                $nextState = 'enemies';
            else if ( in_array( $cardType, array( "abship", "abstation" ) ) )
                $nextState = 'abandoned';
            else
                $nextState = $cardType;

            $game->notifyAllPlayers( "cardDrawn",
                        clienttranslate( 'New card drawn: ${cardTypeStr}'), array(
                            'i18n' => array ( 'cardTypeStr' ),
                            'cardTypeStr' => $game->cardNames[$cardType],
                            'cardRound' => $game->card[ $currentCard ]['round'],
                            'cardId' => $currentCard,
                            ) );
        }

        return $nextState;
    }

    function stStardust($game) {
        $players = GT_DBPlayer::getPlayersInFlight($game, '', $order='ASC');
        
        foreach ( $players as $plId => $player ) {
            $newPlPos = $game->moveShip( $plId, -($player['exp_conn']), $players );
            if ( $newPlPos !== null ) {
                //update this player's position so that it is taken into account if other
                // ships move in the same action
                $players[$plId]['player_position'] = $newPlPos;
            }
        }
        return 'nextCard';
    }

    function stOpenspace($game) {
        $nextState = "nextCard"; // Will be changed to powerEngines if someone
                                    // needs to choose if they use batteries
        $players = GT_DBPlayer::getPlayersForCard($game);

        foreach ( $players as $plId => $player ) {
            if ( $player['max_eng'] == 0 ) {
                // TODO ouch! This player has to give up
                $game->notifyAllPlayers( "onlyLogMessage", clienttranslate( '${player_name} '.
                        'has no activable engine, but is lucky because giving up is not '.
                        'implemented yet'),
                        array ( 'player_name' => $player['player_name'] ) );
                GT_DBPlayer::setCardDone($game, $plId);
            }
            elseif ( $player['min_eng'] == $player['max_eng'] ) {
                // No choice to do for this player, so we move it now and notify players.
                // Do not pass $players to moveShip. We need to consider all players still in flight,
                //    $players here is only those who still have yet to act 
                $newPlPos = $game->moveShip( $plId, (int)$player['min_eng'] );
                if ( $newPlPos !== null ) {
                    //update this player's position so that it is taken into account if other
                    // ships move in the same action
                    $players[$plId]['player_position'] = $newPlPos;
                }
                GT_DBPlayer::setCardDone($game, $plId);
            }
            else {
                // min and max different means that this player can activate a double
                // engine or more, so we need an activeplayer state to ask them 
                // Infos for player: here or in args? In args.
                GT_DBPlayer::setCardInProgress($game, $plId);
                $game->gamestate->changeActivePlayer( $plId );
                $nextState = "powerEngines";
                break; // End of this foreach loop because we need to ask this 
                        // player before processing the following players.
            }
        } // end of foreach players

        return $nextState;
    }

    function stAbandoned() {
        $nextState = "nextCard"; // Will be changed to exploreAbandoned  if someone
                                  // has a big enough crew
        $cardId = $game->getGameStateValue( 'currentCard' );
        $players = GT_DBPlayer::getPlayersForCard($game);
        
        foreach ( $players as $plId => $player ) {
            if ( $player['nb_crew'] < $game->card[$cardId]['crew'] ) {
                $game->notifyAllPlayers( "onlyLogMessage", clienttranslate( '${player_name} '.
                        'doesn\'t have a big enough crew to benefit from this card'),
                        array ( 'player_name' => $player['player_name'] ) );
                GT_DBPlayer::setCardDone($game, $plId);
            }
            else {
                // This player has enough crew members to use the card, so we need
                // to ask them if they want to.
                GT_DBPlayer::setCardInProgress($game, $plId);
                $game->gamestate->changeActivePlayer( $plId );
                $nextState = "exploreAbandoned";
                break; // End of this foreach loop because we need to ask this 
                        // player before processing the following players.
            }
        }
    
        return $nextState;
    }

    function stPlanets($game) {
        // Setup active player to choose a planet
        $nextState = "nextCard";
  
        $cardId = $game->getGameStateValue( 'currentCard' );
        $players = GT_DBPlayer::getPlayersForCard($game);
  
        foreach ( $players as $plId => $player ) {
            GT_DBPlayer::setCardInProgress($game, $plId);
            $game->gamestate->changeActivePlayer( $plId );
            $nextState = "choosePlanet";
            break; 
        }
  
        return $nextState;
    }

}

?>
