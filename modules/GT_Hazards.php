<?php

/* Collection of functions to handle resolving hazards (cannon and meteors) */

class GT_Hazards extends APP_GameClass
{
  function nextHazard($game, $idx)
  {
    self::resetHazardProgress($game);
    $game->setGameStateValue('currentCardProgress', $idx);
  }

  function resetHazardProgress($game)
  {
    $game->setGameStateValue('currentCardProgress', -1);
    $game->setGameStateValue('currentCardDie1', 0);
    $game->setGameStateValue('currentCardDie2', 0);
    $game->setGameStateValue('currentHazardPlayerTile', -1);
  }

  function getHazardRoll($game, $card, $progress = null)
  {
    // loads roll from gamestate or simulates a new roll
    // retuns hazard roll "object" used throughout code
    $progress = is_null($progress) ? $game->getGameStateValue('currentCardProgress') : $progress;
    if ($progress < 0) {
      return;
    }

    // get the type of hazard and split to size / orientation
    if ($card['type'] == 'pirates') {
      $cur_hazard = $card['enemy_penalty'][$progress];
    } elseif ($card['type'] == 'meteoric') {
      $cur_hazard = $card['meteors'][$progress];
    } elseif ($card['type'] == 'combatzone') {
      $cur_hazard = $card['lines'][3]['penalty_value'][$progress];
    } else {
      $cur_hazard = null;
    }

    if (!$cur_hazard) {
      return;
    }

    $size = substr($cur_hazard, 0, 1);
    $orient = (int) substr($cur_hazard, 1);
    $row_col = $orient == 0 || $orient == 180 ? 'column' : 'row';

    // get the dice roll
    $die1 = $game->getGameStateValue('currentCardDie1');
    $die2 = $game->getGameStateValue('currentCardDie2');
    $new_roll = $die1 ? false : true;
    if ($new_roll) {
      $die1 = bga_rand(1, 6);
      $die2 = bga_rand(1, 6);
      $game->setGameStateValue('currentCardDie1', $die1);
      $game->setGameStateValue('currentCardDie2', $die2);
      $game->log("New dice roll $die1 and $die2.");
    } else {
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
      // Some rolls only affect the active player
      $params = [
        'roll' => $die1 + $die2,
        'row_col' => $row_col,
        'sizeName' => GT_Constants::$SIZE_NAMES[$size],
        'direction' => GT_Constants::$DIRECTION_NAMES[$orient],
        'type' => $hazResults['type'],
        'hazResults' => $hazResults,
      ];
      if ($player = GT_DBPlayer::tryPlayerCardInProgress($game)) {
        $params['player_id'] = $player['player_id'];
      } else {
        $params['player_id'] = null;
      }

      $game->notifyAllPlayers(
        'hazardDiceRoll',
        clienttranslate('${sizeName} ${type} incoming from the ${direction}' . ', ${row_col} ${roll}'),
        $params
      );
    }
    return $hazResults;
  }

  function applyHazardToShip($game, $hazResults, $player)
  {
    // apply a hazard (from _applyRollToHazard) to a player's ship
    // returns the next game state or null for moving to the next player (no player action required)
    if ($hazResults['missed']) {
      $game->throw_bug_report_dump("_applyHazardToShip should not 'see' missed hazards", $hazResults);
    }

    $brd = $game->newPlayerBoard($player['player_id']);
    $roll = $hazResults['die1'] + $hazResults['die2'];

    $tilesInLine = $brd->getLine($roll, $hazResults['orient']);

    $game->dump_var("looking along line $roll and orient {$hazResults['orient']}", $tilesInLine);

    // if no tiles, then the row/col is empty
    if (!$tilesInLine) {
      $game->notifyAllPlayers('hazardMissed', clienttranslate('${type} missed ${player_name}\'s ship'), [
        'player_name' => $player['player_name'],
        'player_id' => $player['player_id'],
        'hazResults' => $hazResults,
        'type' => ucfirst($hazResults['type']),
      ]);
      return;
    }

    // It's a hit!
    $firstTileId = reset($tilesInLine)['id'];
    $game->setGameStateValue('currentHazardPlayerTile', $firstTileId);
    $game->log("hazard will hit tile $firstTileId");

    // Small meteors
    if ($hazResults['type'] == 'meteor' && $hazResults['size'] == 's') {
      // not an exposed connector, no player action needed
      if (!$brd->checkIfExposedConnector($roll, $hazResults['orient'])) {
        $msg = clienttranslate('Meteor bounces off ${player_name}\'s ship');
        self::_hazardHarmless($game, $player, $msg, $firstTileId, $hazResults);
        return;
      }

      return self::checkShields($game, $player, $brd, $firstTileId, $hazResults);

      // // If cannot power shields then take damage
      // $plyrContent = $game->newPlayerContent($player['player_id']);
      // if (!$brd->checkIfPowerableShield($plyrContent, $hazResults['orient'])) {
      //     $actionNeeded = self::_hazardDamage(
      //         $game, $player, $brd, $firstTileId, $hazResults);

      //     return $actionNeeded ? 'shipDamage' : NULL;
      // }
      // $game->notifyAllPlayers("onlyLogMessage",
      //     clienttranslate('${player_name} must decide to activate a shield'),
      //     ['player_name' => $player['player_name']]
      // );

      // return 'powerShields';
    }

    // Big meteors
    if ($hazResults['type'] == 'meteor' && $hazResults['size'] == 'b') {
      // This assumes that big meteors cannot come from the rear - the rules do not handle this case
      $singleCannon = $brd->checkSingleCannonOnLine($roll, $hazResults['orient'], $tilesInLine);

      if ($hazResults['orient'] != 0) {
        $single1 = $brd->checkSingleCannonOnLine($roll - 1, $hazResults['orient']);
        $single2 = $brd->checkSingleCannonOnLine($roll + 1, $hazResults['orient']);

        $singleCannon = $singleCannon || $single1 || $single2;
      }

      if ($singleCannon) {
        $msg = clienttranslate('Meteor shot by ${player_name}\'s cannon');
        self::_hazardHarmless($game, $player, $msg, $firstTileId, $hazResults);
        return;
      }

      // Now check for double cannons
      $plyrContent = $game->newPlayerContent($player['player_id']);

      $doubleCannon = $brd->checkDoubleCannonOnLine($roll, $hazResults['orient'], $plyrContent, $tilesInLine);

      if ($hazResults['orient'] != 0) {
        $double1 = $brd->checkDoubleCannonOnLine($roll - 1, $hazResults['orient'], $plyrContent, $tilesInLine);
        $double2 = $brd->checkDoubleCannonOnLine($roll + 1, $hazResults['orient'], $plyrContent, $tilesInLine);

        $doubleCannon = $doubleCannon || $double1 || $double2;
      }

      if ($doubleCannon) {
        $game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} must decide to activate a cannon'), [
          'player_name' => $player['player_name'],
        ]);

        return 'powerCannons';
      }

      // No cannons available - damage :(
      $actionNeeded = self::_hazardDamage($game, $player, $brd, $firstTileId, $hazResults);

      return $actionNeeded ? 'shipDamage' : null;
    }

    // Small cannon
    if ($hazResults['type'] == 'cannon' && $hazResults['size'] == 's') {
      return self::checkShields($game, $player, $brd, $firstTileId, $hazResults);
    }

    // Big cannon
    if ($hazResults['type'] == 'cannon' && $hazResults['size'] == 'b') {
      return self::_hazardDamage($game, $player, $brd, $firstTileId, $hazResults);
    }

    $game->throw_bug_report_dump('_applyHazardToShip should not get here', $hazResults);
  }

  function hazardHarmless($game, $player, $msg, $card)
  {
    // Wrapper for _hazardHarmless to collect hazard-related DB information
    // $msg must already be clienttranslate'd, can have '$player_name' in it
    $tileId = $game->getGameStateValue('currentHazardPlayerTile');
    $hazResults = self::getHazardRoll($game, $card);
    return self::_hazardHarmless($game, $player, $msg, $tileId, $hazResults);
  }

  function _hazardHarmless($game, $player, $msg, $tileId, $hazResults)
  {
    // $msg must already be clienttranslate'd, can have '$player_name' in it
    $game->notifyAllPlayers('hazardHarmless', $msg, [
      'player_name' => $player['player_name'],
      'player_id' => $player['player_id'],
      'tileId' => $tileId,
      'hazResults' => $hazResults,
    ]);
  }

  function checkShields($game, $player, $brd, $firstTileId, $hazResults)
  {
    // If cannot power shields then take damage
    $plyrContent = $game->newPlayerContent($player['player_id']);
    if (!$brd->checkIfPowerableShield($plyrContent, $hazResults['orient'])) {
      $actionNeeded = self::_hazardDamage($game, $player, $brd, $firstTileId, $hazResults);

      return $actionNeeded ? 'shipDamage' : null;
    }
    $game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} must decide to activate a shield'), [
      'player_name' => $player['player_name'],
    ]);

    return 'powerShields';
  }

  function hazardDamage($game, $plId, $card)
  {
    // Wrapper for _hazardDamage to collect hazard-related DB information
    $game->log("hazardDamage for player $plId card {$card['id']}");
    $player = GT_DBPlayer::getPlayer($game, $plId);
    $brd = $game->newPlayerBoard($player['player_id']);
    $hazResults = self::getHazardRoll($game, $card);
    $tileId = $game->getGameStateValue('currentHazardPlayerTile');
    return self::_hazardDamage($game, $player, $brd, $tileId, $hazResults);
  }

  function _hazardDamage($game, $player, $brd, $tileId, $hazResults)
  {
    $game->dump_var("_hazardDamage to tile $tileId, hazResults", $hazResults);
    GT_DBComponent::removeComponents($game, $player['player_id'], [$tileId]);
    GT_DBContent::removeContentByTileIds($game, [$tileId]);
    $brd->removeTilesById([$tileId]);
    $game->notifyAllPlayers(
      'loseComponent',
      clienttranslate('${player_name} loses ${tiletype} tile from ${haztype} strike'),
      [
        'player_name' => $player['player_name'],
        'haztype' => $hazResults['type'],
        'tiletype' => $game->getTileTypeName($tileId),
        'plId' => $player['player_id'],
        'numbComp' => 1,
        'tileIds' => array_values([$tileId]),
        'hazResults' => $hazResults,
      ]
    );

    $shipParts = $brd->checkShipIntegrity();
    $partsToKeep = $brd->removeInvalidParts($shipParts, $player['player_name']);

    $game->updNotifPlInfosObj($player['player_id'], $brd);

    if (count($partsToKeep) > 1) {
      // TODO: notify with $partsToKeep
      return true;
    } else {
      return false;
    }
  }
}
?>
