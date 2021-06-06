<?php

/* Collection of functions to handle resolving hazards (cannon and meteors) */

class GT_Hazards extends APP_GameClass
{
  public function __construct($game, $card = null)
  {
    $this->game = $game;
    $this->card = $card;
  }

  function applyHazards()
  {
    // Runs through all players in flight with card choice APPLY_HAZARD, for all hazards
    // Marks each player done for a hazard as cardDone
    // When first entering this method (run resetCardProgressForNextHazard()):
    //  * appropriate players card choice as APPLY_HAZARD
    //  * card not done (card_line_done) for those players
    $game = $this->game;
    $card = $this->card;

    if (!($card instanceof \GT\Models\HazardCard)) {
      $this->game->throw_bug_report("Unknown card type ({$card->getType()}) for card ({$card->getId()}) in GT_Hazards");
      return;
    }

    $idx = $game->getGameStateValue('currentCardProgress');
    if ($idx < 0) {
      $idx = 0; // start of the card
      $this->nextHazard(0);
    }

    $game->dump_var("Entering applyHazards with current card {$card->getId()}, hazard $idx.", $card);

    while ($cur_hazard = $this->getHazard($idx)) {
      $game->log("Running hazard $idx: $cur_hazard.");

      // Get previous dice roll, if available. If not roll and notif
      $players = GT_DBPlayer::getPlayersForCard($game);
      $hazResults = $this->getHazardRoll($cur_hazard, array_keys($players));

      if ($hazResults['missed']) {
        $game->log("applyHazards missed all players idx $idx");
        $game->notifyAllPlayers('hazardMissed', clienttranslate('${type} missed all ships'), [
          'hazResults' => $hazResults,
          'type' => ucfirst($hazResults['type']),
        ]);
        $this->nextHazard(++$idx);
        continue;
      }

      // Go through players until finding one that has to act
      foreach ($players as $plId => $player) {
        $game->log("applyHazards for player $plId, index $idx.");
        $nextState = $this->applyHazardToShip($hazResults, $player);
        $game->log("applyHazards next state: $nextState");
        if ($nextState) {
          GT_DBPlayer::setCardInProgress($game, $plId);
          $game->gamestate->changeActivePlayer($plId);
          return $nextState;
        }
      }

      $game->log("Finished hazard index $idx");
      // no players left to act for this hazard, go to next hazard
      $this->nextHazard(++$idx);
    }

    // TODO hide dice (see how we're hiding cards)
    $game->log("Finished hazard card {$card->getId()}");
    return 'nextCard';
  }

  function resetCardProgressForNextHazard()
  {
    $game = $this->game;

    $players = array_filter(GT_DBPlayer::getPlayersInFlight($game), function ($p) {
      return $p['card_action_choice'] == CARD_CHOICE_APPLY_HAZARD;
    });
    foreach ($players as $plId => $player) {
      GT_DBPlayer::resetCardProgress($game, $plId);
    }
  }

  function nextHazard($idx)
  {
    $this->resetCardProgressForNextHazard();
    $this->resetHazardProgress();
    $this->game->setGameStateValue('currentCardProgress', $idx);
  }

  function resetHazardProgress()
  {
    $this->game->setGameStateValue('currentCardProgress', -1);
    $this->game->setGameStateValue('currentCardDie1', 0);
    $this->game->setGameStateValue('currentCardDie2', 0);
    $this->game->setGameStateValue('currentHazardPlayerTile', -1);
  }

  function getHazard($idx = null)
  {
    // loads roll from gamestate or simulates a new roll
    // retuns hazard roll "object" used throughout code
    $idx = is_null($idx) ? $this->game->getGameStateValue('currentCardProgress') : $idx;
    if ($idx < 0) {
      return;
    }

    return $this->card->getCurrentHazard($idx);
  }

  function getHazardRoll($cur_hazard = null, $players = null)
  {
    // Get the roll for the current hazard
    // Will get a new roll if needed, otherwise load roll from database
    // If a new roll is indicated, notify front end of the roll.
    //    $players: player list that are active for this hazard
    //    (and thus need the hazard sprite rendered on their ship)

    $game = $this->game;

    if ($cur_hazard === null) {
      $cur_hazard = $this->getHazard();
    }

    if ($cur_hazard === null) {
      return;
    }

    // $cur_hazard is two-character with size and orientation
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
      $game->log("Reusing dice roll for hazard $cur_hazard");
    }

    // build final object and return
    $shipClassInt = $game->getGameStateValue('shipClass');
    $missed = in_array($die1 + $die2, GT_Constants::$SHIP_CLASS_MISSES[$shipClassInt . '_' . $row_col]);

    $hazResults = [
      'die1' => $die1,
      'die2' => $die2,
      'row_col' => $row_col,
      'type' => $this->card->getType() == CARD_METEORIC_SWARM ? 'meteor' : 'cannon',
      'size' => $size,
      'orient' => $orient,
      'missed' => $missed,
    ];

    if ($new_roll) {
      // Notify all players of the roll
      // Place hazard only on affected ships (indicated by giving player_ids to front end)
      $params = [
        'roll' => $die1 + $die2,
        'row_col' => $row_col,
        'sizeName' => GT_Constants::$SIZE_NAMES[$size],
        'direction' => GT_Constants::$DIRECTION_NAMES[$orient],
        'type' => $hazResults['type'],
        'hazResults' => $hazResults,
        'player_ids' => $players,
      ];

      $game->notifyAllPlayers(
        'hazardDiceRoll',
        clienttranslate('${sizeName} ${type} incoming from the ${direction}' . ', ${row_col} ${roll}'),
        $params
      );
    }
    return $hazResults;
  }

  function applyHazardToShip($hazResults, $player)
  {
    // apply a hazard (from _applyRollToHazard) to a player's ship
    // returns the next game state or null for moving to the next player (no player action required)
    $game = $this->game;

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
        $this->_hazardHarmless($player, $msg, $firstTileId, $hazResults);
        return;
      }

      return $this->checkShields($player, $brd, $firstTileId, $hazResults);

      // // If cannot power shields then take damage
      // $plyrContent = $game->newPlayerContent($player['player_id']);
      // if (!$brd->checkIfPowerableShield($plyrContent, $hazResults['orient'])) {
      //     $actionNeeded = $this->_hazardDamage(
      //         $player, $brd, $firstTileId, $hazResults);

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
        $this->_hazardHarmless($player, $msg, $firstTileId, $hazResults);
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
      $actionNeeded = $this->_hazardDamage($player, $brd, $firstTileId, $hazResults);

      return $actionNeeded ? 'shipDamage' : null;
    }

    // Small cannon
    if ($hazResults['type'] == 'cannon' && $hazResults['size'] == 's') {
      return $this->checkShields($player, $brd, $firstTileId, $hazResults);
    }

    // Big cannon
    if ($hazResults['type'] == 'cannon' && $hazResults['size'] == 'b') {
      return $this->_hazardDamage($player, $brd, $firstTileId, $hazResults);
    }

    $game->throw_bug_report_dump('_applyHazardToShip should not get here', $hazResults);
  }

  function hazardHarmless($player, $msg)
  {
    // Wrapper for _hazardHarmless to collect hazard-related DB information
    // $msg must already be clienttranslate'd, can have '$player_name' in it
    $tileId = $this->game->getGameStateValue('currentHazardPlayerTile');
    $hazResults = $this->getHazardRoll();
    return $this->_hazardHarmless($player, $msg, $tileId, $hazResults);
  }

  function _hazardHarmless($player, $msg, $tileId, $hazResults)
  {
    // $msg must already be clienttranslate'd, can have '$player_name' in it
    $this->game->notifyAllPlayers('hazardHarmless', $msg, [
      'player_name' => $player['player_name'],
      'player_id' => $player['player_id'],
      'tileId' => $tileId,
      'hazResults' => $hazResults,
    ]);
  }

  function checkShields($player, $brd, $firstTileId, $hazResults)
  {
    $game = $this->game;

    // If cannot power shields then take damage
    $plyrContent = $game->newPlayerContent($player['player_id']);
    if (!$brd->checkIfPowerableShield($plyrContent, $hazResults['orient'])) {
      $actionNeeded = $this->_hazardDamage($player, $brd, $firstTileId, $hazResults);

      return $actionNeeded ? 'shipDamage' : null;
    }
    $game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} must decide to activate a shield'), [
      'player_name' => $player['player_name'],
    ]);

    return 'powerShields';
  }

  function hazardDamage($plId)
  {
    // Wrapper for _hazardDamage to collect hazard-related DB information
    $game = $this->game;

    $game->log("hazardDamage for player $plId card {$this->card->getId()}");
    $player = GT_DBPlayer::getPlayer($game, $plId);
    $brd = $game->newPlayerBoard($player['player_id']);
    $hazResults = $this->getHazardRoll();
    $tileId = $game->getGameStateValue('currentHazardPlayerTile');
    return $this->_hazardDamage($player, $brd, $tileId, $hazResults);
  }

  function _hazardDamage($player, $brd, $tileId, $hazResults)
  {
    $game = $this->game;

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
