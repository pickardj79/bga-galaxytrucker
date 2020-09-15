<?php

/* Collection of functions to handle states associated with cards */

require_once('GT_DBPlayer.php');
require_once('GT_Constants.php');

class GT_StatesCard extends APP_GameClass {
    public function __construct() {
    }

    function currentCardData($game){
        $cardId = $game->getGameStateValue( 'currentCard'); 
        $card = $game->card[$cardId];
        $progress = $game->getGameStateValue( 'currentCardProgress');


        return array(
            "id" => $cardId,
            "type" => $card['type'],
            "curHazard" => self::_applyRollToHazard($game, $card),
            "card_line_done" => GT_DBPlayer::getCardProgress($game)
        );
    }

    function stDrawCard($game) {

        GT_DBPlayer::clearCardProgress($game);

        $cardOrderInFlight = $game->getGameStateValue( 'cardOrderInFlight' );
        $cardOrderInFlight++;
        $game->setGameStateValue( 'cardOrderInFlight', $cardOrderInFlight );
        $currentCard = $game->getUniqueValueFromDB ( "SELECT card_id id FROM card ".
                                        "WHERE card_order=$cardOrderInFlight" );

        if ( is_null($currentCard) ) { 
            // no more cards, this flight is done
            $nextState = 'cardsDone' ;
            $game->setGameStateValue( 'currentCard', -1 );
        }
        else {
            $game->setGameStateValue( 'currentCard', $currentCard );
            $game->setGameStateValue( 'currentCardProgress', 0);
            $game->setGameStateValue( 'currentCardDie1', 0);
            $game->setGameStateValue( 'currentCardDie2', 0);
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
        
        $flBrd = $game->newFlightBoard($players);

        foreach ( $players as $plId => $player ) {
            $newPlPos = $flBrd->moveShip( $plId, -($player['exp_conn']), $players );
        }
        return 'nextCard';
    }

    function stOpenspace($game) {
        $nextState = "nextCard"; // Will be changed to powerEngines if someone
                                    // needs to choose if they use batteries
        $players = GT_DBPlayer::getPlayersForCard($game);

        // Do not pass $players to flightBoard. We need to consider all players still in flight,
        //    $players here is only those who still have yet to act 
        $flBrd = $game->newFlightBoard();

        foreach ( $players as $plId => $player ) {
            if ( $player['max_eng'] == 0 ) {
                $flBrd->giveUp($plId, 'cannot power engines for Open Space');
                GT_DBPlayer::setCardDone($game, $plId);
            }
            elseif ( $player['min_eng'] == $player['max_eng'] ) {
                // No choice to do for this player, so we move it now and notify players.
                $flBrd->moveShip( $plId, (int)$player['min_eng'] );
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

    function stAbandoned($game) {
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
  
        $cardId = $game->getGameStateValue( 'currentCard' );
        $players = GT_DBPlayer::getPlayersForCard($game);
  
        foreach ( $players as $plId => $player ) {
            GT_DBPlayer::setCardInProgress($game, $plId);
            $game->gamestate->changeActivePlayer( $plId );
            return "choosePlanet";
        }
  
        // No one else to choose - move all ships based on card, furthest back first
        $players = GT_DBPlayer::getPlayersInFlight($game, '', $order='ASC');
        $flBrd = $game->newFlightBoard($players);

        $nbDays = -($game->card[$cardId]['days_loss']);
        foreach ($players as $plId => $player) {
            if ($player['card_action_choice'] == '0')
                continue;
            $flBrd->moveShip($plId, $nbDays);
        }

        return "nextCard";
    }

    function stMeteoric($game) {
        $cardId = $game->getGameStateValue( 'currentCard' );
        $card = $game->card[$cardId];

        $idx = $game->getGameStateValue( 'currentCardProgress');
        $game->dump_var("Entering meteor with current card $cardId Meteor $idx.", $card);
        while ($idx < count($card['meteors'])) {

            // Get previous dice roll, if available. If not roll and notif

            if (!$die1 = $game->getGameStateValue('currentCardDie1')) {
                $die1= bga_rand(1,6);
                $die2= bga_rand(1,6);
                $game->setGameStateValue( 'currentCardDie1', $die1);
                $game->setGameStateValue( 'currentCardDie2', $die2);
                $hazResults = self::_applyRollToHazard($game, $card, $idx, $die1, $die2);

                $game->notifyAllPlayers( "hazardDiceRoll", 
                        clienttranslate( '${sizeName} meteor incoming from the ${direction}'
                            . ', ${row_col} ${roll}${suffix}' ),
                        array_merge($hazResults, array(
                            'roll' => $die1 + $die2,
                            'sizeName' => GT_Constants::$SIZE_NAMES[$hazResults['size']],
                            'direction' => GT_Constants::$DIRECTION_NAMES[$hazResults['orient']],
                            'suffix' => $hazResults['missed'] ? '... missed!' : '!',
                        ))
                );
            }
            else {
                $game->log("Reusing dice roll $die1 and $die2.");
            }

            if ($hazResults['missed']) {
                $game->setGameStateValue( 'currentCardProgress', ++$idx);
                $game->setGameStateValue( 'currentCardDie1', 0);
                $game->setGameStateValue( 'currentCardDie2', 0);
                continue;
            }
            //   for each player for current card
            //      check part of ship that will be hit
            //      see if we can/need to use batteries for shields or cannons -> switch to content choice
            //      lose exposed tile
            //      mark player done for card
            // reset players for current card

            // reset dice roll, move to next hazard
            $game->setGameStateValue( 'currentCardProgress', ++$idx);
            // $game->setGameStateValue( 'currentCardDie1', 0);
            // $game->setGameStateValue( 'currentCardDie2', 0);

            // TEMP SO WE CAN MANULLAY GO THROUGH ALL METEORS
            return 'powerShields';
        }

        // hide dice (see how we're hiding cards)
        return 'nextCard';
    }

    // ###################### HELPER ##########################
    function _applyRollToHazard($game, $card, $progress=NULL, $die1=NULL, $die2=NULL) {

        $die1 = is_null($die1) ? $game->getGameStateValue( 'currentCardDie1') : $die1;
        $die2 = is_null($die2) ? $game->getGameStateValue( 'currentCardDie2') : $die2;

        $progress = is_null($progress) ? $game->getGameStateValue( 'currentCardProgress') : $progress;

        if ($card['type'] == 'pirates')
            $cur_hazard = $card['enemy_penalty'][$progress];
        elseif ($card['type'] == 'meteoric')
            $cur_hazard = $card['meteors'][$progress];
        elseif ($card['type'] == 'combatzone')
            $cur_hazard = $card['lines'][3][$progress];
        else
            $cur_hazard = NULL;

        $size = substr($cur_hazard,0,1);
        $orient = (int)substr($cur_hazard,1);

        $roll = $die1 + $die2;
        $row_col = $orient == 0 || $orient == 180 ? 'column' : 'row';
        $shipClassInt = $game->getGameStateValue('shipClass');
        $missed = in_array($roll, GT_Constants::$SHIP_CLASS_MISSES[$shipClassInt . '_' . $row_col]);
        
        return array ( 
            'die1' => $die1, 
            'die2' => $die2,
            'row_col' => $row_col,
            'type' => $card['type'] == 'meteoric' ? 'meteor' : 'cannon',
            'size' => $size,
            'orient' => $orient,
            'missed' => $missed,
        );

    }

}

?>
