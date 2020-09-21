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


        return array(
            "id" => $cardId,
            "type" => $card['type'],
            "curHazard" => self::_getHazardRoll($game, $card),
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
            $game->setGameStateValue( 'currentCardProgress', -1);
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
        if ($idx < 0) $idx = 0; // start of the card

        $game->dump_var("Entering meteor with current card $cardId Meteor $idx.", $card);
        while ($idx < count($card['meteors'])) {
            $game->log("Running meteor $idx.");

            // Get previous dice roll, if available. If not roll and notif
            $hazResults = self::_getHazardRoll($game, $card, $idx);

            if ($hazResults['missed']) {
                $game->notifyAllPlayers( "hazardMissed", 
                            clienttranslate( 'Meteor missed all ships'), 
                            [ 'hazResults' => $hazResults ] );
                $game->setGameStateValue( 'currentCardProgress', ++$idx);
                $game->setGameStateValue( 'currentCardDie1', 0);
                $game->setGameStateValue( 'currentCardDie2', 0);
                continue;
            }

            // Go through players until finding one that has to act
            $players = GT_DBPlayer::getPlayersForCard($game);
            foreach ( $players as $plId => $player ) {
                $game->log("Working on player $plId, index $idx.");
                $nextState = self::_applyHazardToShip($game, $hazResults, $player);
                $game->log("Got $nextState");
                if ($nextState) {
                    GT_DBPlayer::setCardInProgress($game, $plId);
                    $game->gamestate->changeActivePlayer( $plId );
                    // TODO - within nextState - mark player done for card
                    return $nextState;
                } 
            }

            $game->log("Finished index $idx");
            // no players need to act for this hazard, go to next hazard 
            $game->setGameStateValue( 'currentCardProgress', ++$idx);
            $game->setGameStateValue( 'currentCardDie1', 0);
            $game->setGameStateValue( 'currentCardDie2', 0);
            GT_DBPlayer::clearCardProgress($game);
        }

        // TODO hide dice (see how we're hiding cards)
        return 'nextCard';
    }

    // ###################### HELPER ##########################
    function _getHazardRoll($game, $card, $progress=NULL) {
        // loads roll from gamestate or simulates a new roll
        // retuns hazard roll "object" used throughout code
        $progress = is_null($progress) ? $game->getGameStateValue( 'currentCardProgress') : $progress;
        if ($progress < 0)
            return;

        // get the type of hazard and split to size / orientation
        if ($card['type'] == 'pirates')
            $cur_hazard = $card['enemy_penalty'][$progress];
        elseif ($card['type'] == 'meteoric')
            $cur_hazard = $card['meteors'][$progress];
        elseif ($card['type'] == 'combatzone')
            $cur_hazard = $card['lines'][3]['penalty_value'][$progress];
        else
            $cur_hazard = NULL;
        
        if (!$cur_hazard)
            return;

        $size = substr($cur_hazard,0,1);
        $orient = (int)substr($cur_hazard,1);
        $row_col = $orient == 0 || $orient == 180 ? 'column' : 'row';

        // get the dice roll
        $die1 = $game->getGameStateValue( 'currentCardDie1');
        $die2 = $game->getGameStateValue( 'currentCardDie2');
        $new_roll = $die1 ? FALSE : TRUE;
        if ($new_roll) {
            $die1= bga_rand(1,6);
            $die2= bga_rand(1,6);
            $game->setGameStateValue( 'currentCardDie1', $die1);
            $game->setGameStateValue( 'currentCardDie2', $die2);
            $game->log("New dice roll $die1 and $die2.");

        }
        else {
            $game->log("Reusing dice roll for card item $progress");
        }

        // build final object and return
        $shipClassInt = $game->getGameStateValue('shipClass');
        $missed = in_array($die1 + $die2, GT_Constants::$SHIP_CLASS_MISSES[$shipClassInt . '_' . $row_col]);

        $hazResults = [ 
            'die1' => $die1, 
            'die2' => $die2,
            'row_col' => $row_col,
            'type' => $card['type'] == 'meteoric' ? 'meteor' : 'cannon',
            'size' => $size,
            'orient' => $orient,
            'missed' => $missed,
        ];

        if ($new_roll) {
            $game->notifyAllPlayers( "hazardDiceRoll", 
                    clienttranslate( '${sizeName} meteor incoming from the ${direction}'
                        . ', ${row_col} ${roll}' ),
                    [   'roll' => $die1 + $die2,
                        'row_col' => $row_col,
                        'sizeName' => GT_Constants::$SIZE_NAMES[$size],
                        'direction' => GT_Constants::$DIRECTION_NAMES[$orient],
                        'hazResults' => $hazResults
                    ]
            );
        }
        return $hazResults;
    }

    function _applyHazardToShip($game, $hazResults, $player) {
        // apply a hazard (from _applyRollToHazard) to a player's ship
        // returns the next game state or null for moving to the next player (no player action required)
        if ($hazResults['missed'])
            $game->throw_bug_report_dump("_applyHazardToShip should not 'see' missed hazards", $hazResults);

        $brd = $game->newPlayerBoard($player['player_id']);
        $roll = $hazResults['die1'] + $hazResults['die2']; 

        $tilesInLine = $brd->getLine($roll, $hazResults['orient']);

        $game->dump_var("looking along line $roll and orient {$hazResults['orient']}", $tilesInLine);

        // if no tiles, then the row/col is empty
        if (!$tilesInLine) {
            $game->notifyAllPlayers( "hazardMissed", 
                    clienttranslate( 'Meteor missed ${player_name}\'s ship'), 
                    [ 'player_name' => $player['player_name'], 
                      'player_id' => $player['player_id'],
                      'hazResults' => $hazResults ] );
            return;
        }
        
        if ($hazResults['type'] == 'meteor' && $hazResults['size'] == 's') {
            // not an exposed connector, no player action needed
            if (!$brd->checkIfExposedConnector($roll, $hazResults['orient'])) {
                $game->notifyAllPlayers( "hazardHarmless", 
                    clienttranslate( 'Meteor bounces off ${player_name}\'s ship'),
                    [   'player_name' => $player['player_name'],
                        'player_id' => $player['player_id'],
                        'tile' => reset($tilesInLine),
                        'hazResults' => $hazResults
                    ]);
                return;
            }
            $plyrContent = $game->newPlayerContent($player['player_id']);
            if (!$brd->checkIfPowerableShield($plyrContent, $hazResults['orient'])) {
                // TODO - switch to state shipDamage, which will then come back to this state
                // TODO - for now call this hazard "Harmless" but go to shipDamage state
                $game->notifyAllPlayers( "hazardHarmless", 
                    clienttranslate( 'Meteor FAKE - TESTING bounces off ${player_name}\'s ship'),
                    [   'player_name' => $player['player_name'],
                        'player_id' => $player['player_id'],
                        'tile' => reset($tilesInLine),
                        'hazResults' => $hazResults
                    ]);
                return 'shipDamage';
            }
            // TODO - notif about meteor striking exposed connector and player having to decide
            return 'powerShields';
        }

        if ($hazResults['type'] == 'meteor' && $hazResults['size'] == 'b') {
            $game->notifyAllPlayers( "hazardMissed", 
                    clienttranslate( 'Meteor missed ${player_name}\'s ship'), 
                    [ 'player_name' => $player['player_name'], 
                      'player_id' => $player['player_id'],
                      'hazResults' => $hazResults ] );
            // TODO - temporary
            return;
        }
    }

}

?>
