<?php

/* Collection of functions to handle resolving hazards (laser and meteors) */

require_once('GT_DBComponent.php');
require_once('GT_DBContent.php');

class GT_Hazards extends APP_GameClass  {

    function nextHazard($game, $idx) {
        $game->setGameStateValue( 'currentCardProgress', $idx);
        $game->setGameStateValue( 'currentCardDie1', 0);
        $game->setGameStateValue( 'currentCardDie2', 0);
    }

    function resetHazardProgress($game) {
        $game->setGameStateValue( 'currentCardProgress', -1);
        $game->setGameStateValue( 'currentCardDie1', 0);
        $game->setGameStateValue( 'currentCardDie2', 0);
    }

    function getHazardRoll($game, $card, $progress=NULL) {
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
            $die1= 4; //bga_rand(1,6);
            $die2= 2; //bga_rand(1,6);
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

    function applyHazardToShip($game, $hazResults, $player) {
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
        
        // Small meteors
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

            // If cannot power shields then take damage
            $plyrContent = $game->newPlayerContent($player['player_id']);
            if (!$brd->checkIfPowerableShield($plyrContent, $hazResults['orient'])) {
                $actionNeeded = self::hazardDamage(
                    $game, $player, $brd, $plyrContent, reset($tilesInLine), 
                    'meteor strike.', $hazResults);

                return $actionNeeded ? 'shipDamage' : NULL;
            }
            // TODO - notif about meteor striking exposed connector and player having to decide
            return 'powerShields';
        }

        // Big meteors
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

    function hazardDamage($game, $player, $brd, $plContent=Null, $tile, $msg, $hazResults) {
        // see notifyAllPlayers for how $msg fits
        GT_DBComponent::removeComponents($game, $player['player_id'], [$tile['id']]);
        GT_DBContent::removeContentByTileIds($game, [$tile['id']]);
        $brd->removeTiles([$tile]);
        $game->notifyAllPlayers( "loseComponent", 
            clienttranslate( '${player_name} loses ${typename} tile from ${msg}'),
            [   'player_name' => $player['player_name'],
                'msg' => $msg,
                'typename' => $game->getTileTypeName($tile['id']),
                'plId' => $player['player_id'],
                'numbComp' => 1,
                'tileIds' => [ $tile['id'] ],
                'hazResults' => $hazResults
            ]);

        $shipParts = $brd->checkShipIntegrity();
        $partsToKeep = $brd->removeInvalidParts($shipParts, $player['player_name']);

        $game->updNotifPlInfosObj($player['player_id'], $brd, $plContent);

        if (count($partsToKeep) > 1) {
            // TODO: notify with $partsToKeep
            return TRUE;
        }
        else
            return FALSE;
    }
}
?>