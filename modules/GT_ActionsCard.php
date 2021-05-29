<?php

/* Collection of function to handle player actions in response to cards */
use GT\Managers\CardsManager;
use GT\Models\AbandonedCard;

class GT_ActionsCard extends APP_GameClass
{
  public function __construct()
  {
  }

  function exploreChoice($game, $plId, $cardId, $choice)
  {
    $player = GT_DBPlayer::getPlayer($game, $plId);

    // Sanity checks TODO: do we need to check something else?
    if ($player['card_line_done'] != '1') {
      $game->throw_bug_report("Explore choice: wrong value for card done ({$player['card_line_done']})");
    }

    if (!in_array($choice, [0, 1])) {
      $game->throw_bug_report("Explore choice: wrong value for choice ($choice)");
    }
    $card = CardsManager::get($cardId);

    if ($choice == 0) {
      GT_DBPlayer::setCardDone($game, $plId);
      self::noStopMsg($game);
      return 'nextPlayer';
    } elseif ($card instanceof AbandonedCard) {
      return $card->exploreChoice($game, $plId);
    } else {
      $game->throw_bug_report("Explore choice: wrong value for card type ({$card->getType()}).");
      return null;
    }
  }

  function loseContentChoice($game, $playerId, $card, $typeToLose, $ids)
  {
    # Validate $ids is correct size, get $subtype for validation
    # $type is also validated against $card (pulled from DB) below

    # Required count is stored in cardArg2
    $req_cnt = $game->getGameStateValue('cardArg2');
    if (count($ids) != $req_cnt) {
      $game->throw_bug_report_dump("loseContentChoice: wrong number of ids expected $req_cnt", $ids);
    }

    # Required content type is stored in cardArg3. If -1 or NULL, assume cell
    $expTypeInt = $game->getGameStateValue('cardArg3');
    if (is_null($expTypeInt) or $expTypeInt < 0) {
      $expType = 'cell';
    } else {
      $expType = GT_Constants::$CONTENT_INT_TYPE_MAP[$expTypeInt];
    }

    if ($expType != $typeToLose) {
      $game->throw_bug_report("loseContentChoice: type ($typeToLose) does not match expected type ($expType)");
    }

    # Required subtype, if applicable, is stored in cardArg1
    $idx = $game->getGameStateValue('cardArg1');
    $subtype = $typeToLose == 'goods' ? GT_Constants::$ALLOWABLE_SUBTYPES['goods'][$idx] : null;

    $plyrContent = $game->newPlayerContent($playerId);
    $bToCard = true; // Will be set to false only for Combat Zone
    $plyrContent->loseContent($ids, $typeToLose, $subtype, $bToCard);

    $nextState = $card->loseContent($game, $playerId, $typeToLose);
    if ($nextState == null) {
      $game->throw_bug_report_dump("crewChoice wrong card type for cardId {$card->getId()}, type to lose - {$typeToLose}", $card);
    }

    GT_DBPlayer::setCardDone($game, $playerId);
    return $nextState;
  }

  function crewChoice($game, $playerId, $cardId, $crewChoices)
  {
    $plyrContent = $game->newPlayerContent($playerId);
    // Hey, wait. We need orientation for batteries, but not for crew members, right?
    // Since they're in a non-rotated overlay tile, they'll always be slided correctly.
    //$orientNeeded = false; // Will be set to true only for Slavers (sure?) and Combat Zone
    $bToCard = true; // Will be set to false only for Combat Zone
    //$tileOrient = ( ! $orientNeeded ) ? null
    //                                : self::getCollectionFromDB( "SELECT component_id, ".
    //                "orientation FROM component WHERE component_player=$plId", true );

    // TODO see if it's possible to have a common function with battChoice()
    //  and slavers and combat zones (maybe only one or two things will differ:
    //  number of batteries consistent with number of cannons, moveShip (forward)
    //  vs gainCredits and moveShip backwards, ...)

    $plyrContent->loseContent($crewChoices, 'crew', null, $bToCard);

    $flBrd = $game->newFlightBoard();
    $card = CardsManager::get($cardId);

    $nextState = $card->crewChoice($game, $playerId);
    if ($nextState == null) {
      $game->throw_bug_report_dump("crewChoice wrong card type for cardId $cardId", $card);
    }

    // if no humans remain, giveUp
    if (!$plyrContent->getContent('crew', 'human')) {
      $flBrd->giveUp($playerId, 'lost all humans');
    }

    return $nextState;
  }

  function powerEngines($game, $plId, $battChoices)
  {
    $brd = $game->newPlayerBoard($plId);
    $plyrContent = $game->newPlayerContent($plId);
    $nbDoubleEngines = $brd->countDoubleEngines();
    $nbSimpleEngines = $brd->countSingleEngines();
    $nbBatt = count($battChoices);

    // Checks
    $plyrContent->checkBattChoices($battChoices, $nbDoubleEngines);

    // Calculate how far to move
    $nbDays = $nbSimpleEngines + 2 * $nbBatt;
    if ($nbDays > 0 && $plyrContent->checkIfAlien('brown')) {
      $nbDays += 2;
    }

    if ($nbDays == 0) {
      $game->newFlightBoard()->giveUp($plId, 'did not have any engine power for Open Space');
    } else {
      if ($nbBatt > 0) {
        $plyrContent->loseContent($battChoices, 'cell');
      }

      $game->newFlightBoard()->moveShip($plId, $nbDays);
    }
  }

  function powerDefense($game, $plId, $card, $battChoices, $defenseType)
  {
    // Powering shields or cannons against incoming cannon or meteors
    if (count($battChoices) == 0) {
      GT_Hazards::hazardDamage($game, $plId, $card);
    } else {
      $player = GT_DBPlayer::getPlayer($game, $plId);
      if ($defenseType == 'shields') {
        if ($card->getType() == CARD_METEORIC_SWARM) {
          $msg = clienttranslate('Meteor deflected by ${player_name}\'s shield');
        } else {
          $msg = clienttranslate('Cannon blast deflected by ${player_name}\'s shield');
        }
      } elseif ($defenseType == 'cannons') {
        $msg = clienttranslate('Meteor blasted by ${player_name}\'s cannon');
      } else {
        $game->throw_bug_report("Invalid defenseType ($defenseType) for powerDefense");
      }

      $plyrContent = $game->newPlayerContent($plId);
      $plyrContent->checkBattChoices($battChoices, 1);
      $plyrContent->loseContent($battChoices, 'cell');

      GT_Hazards::hazardHarmless($game, $player, $msg, $card);
    }
  }

  function powerCannonsEnemy($game, $plId, $card, $battChoices)
  {
    // powering cannons against an enemy

    $player = GT_DBPlayer::getPlayer($game, $plId);
    $brd = $game->newPlayerBoard($plId);
    $plyrContent = $game->newPlayerContent($plId);
    $nbBatt = count($battChoices);
    $nbDblCannons = $brd->countDoubleCannons();
    $nbFwdDblCannons = $brd->countTileType('cannon', 2, 0);

    // Checks
    if ($nbBatt > $nbDblCannons) {
      $game->throw_bug_report_dump('Too many batteries selected for fightPlayer', $battChoices);
    }

    $plyrContent->checkBattChoices($battChoices, $nbDblCannons);
    $plyrContent->loseContent($battChoices, 'cell');

    // Assume forward-facing double-cannons are activated first
    $str = $player['min_cann_x2'] / 2;
    if ($nbBatt > $nbFwdDblCannons) {
      $str += $nbFwdDblCannons * 2;
      $nbBatt -= $nbFwdDblCannons;
      $str += $nbBatt; // remaining batteries power side-facing cannons, which give 1 each
    } else {
      // all batteries power forward-facing double cannons
      $str += $nbBatt * 2;
    }

    $game->setGameStateValue('cardArg1', $str);
    return 'enemyResults';
  }

  function planetChoice($game, $plId, $cardId, $choice)
  {
    if (!$choice) {
      GT_DBPlayer::setCardDone($game, $plId);
      self::noStopMsg($game);
      return 'nextPlayer';
    }

    // Do some checks
    if (!is_numeric($choice)) {
      $game->throw_bug_report_dump('Planet choice is not an int:', $choice);
    }

    $choice = (int) $choice;

    $allIdx = array_keys(CardsManager::get($cardId)->getPlanets());
    $chosenIdx = array_filter(
      array_map(function ($row) {
        return $row['card_action_choice'];
      }, GT_DBPlayer::getCardChoices($game))
    );

    if (!in_array($choice, $allIdx)) {
      $game->throw_bug_report("Planet choice ($choice) not possible for this planet ($cardId)");
    }

    if (in_array($choice, $chosenIdx)) {
      $game->throw_bug_report("Planet choice ($choice) already chosen", $chosenIdx);
    }

    // Update DB, front-end, move state to placeGoods
    GT_DBPlayer::setCardChoice($game, $plId, $choice);
    $game->notifyAllPlayers('planetChoice', clienttranslate('${player_name} choses planet number ${planetId}'), [
      'player_name' => $game->getActivePlayerName(),
      'plId' => $plId,
      'planetId' => $choice,
    ]);

    return 'placeGoods';
  }

  function cargoChoice($game, $plId, $cardId, $goodsOnTile)
  {
    $game->dump_var('cargoChoice', $goodsOnTile);
    $player = GT_DBPlayer::getPlayer($game, $plId);
    $plyrContent = $game->newPlayerContent($plId);

    // note original content ids to remove those not chosen
    $origContentIds = $plyrContent->getContentIds('goods');

    // $goodsOnTile is all goods on the ship. Clear places in preparation
    // This allows us to not care about order of moving stuff around
    $plyrContent->clearAllPlaces('goods');

    // Split goods for each tile into new goods (from the card) or moved goods (already in DB)
    // Rely on transactions to roll back database changes if any validation fails
    $seenGoodsIdx = [];
    $allMovedGoodsIds = [];
    $movedTileContent = [];
    $newTileContent = [];
    foreach ($goodsOnTile as $tile => $goods) {
      $newGoods = []; // array of goods subtypes
      $movedGoodsIds = [];
      foreach ($goods as $goodId) {
        if (strpos($goodId, 'cardgoods')) {
          $idx = null;
          if (CardsManager::get($cardId)->getType() == CARD_PLANETS) {
            // goodId on card: cargo_planetcargo_X_Y: X is planet #, Y is goods #
            list($t1, $t2, $planet, $idx) = explode('_', $goodId);
            if ($planet != $player['card_action_choice']) {
              $game->throw_bug_report(
                "Cargo ($goodId) has wrong planet, should be ({$player['card_action_choice']})",
                $goodsOnTile
              );
            }
            $newGoods[] = CardsManager::get($cardId)->getPlanets()[$planet][$idx - 1];
          } else {
            $idx = explode('_', $goodId)[2];
            $newGoods[] = CardsManager::get($cardId)->getReward()[$idx - 1];
          }

          if (in_array($idx, $seenGoodsIdx)) {
            $game->throw_bug_report("Cargo idx ($idx) appears twice", $goodsOnTile);
          }
          $seenGoodsIdx[] = $idx;
        } else {
          $id = explode('_', $goodId)[1];
          $movedGoodsIds[] = $id;
        }
      }
      $tileId = explode('_', $tile)[1];

      $movedGoods = $plyrContent->moveContent($tileId, 'goods', $movedGoodsIds);
      $newGoods = $plyrContent->newContent($tileId, 'goods', null, $newGoods);

      $allMovedGoodsIds = array_merge($allMovedGoodsIds, $movedGoodsIds);

      // prep args to send back to front-end
      $movedTileContent[$tileId] = $movedGoods;
      $newTileContent[$tileId] = $newGoods;
    }

    // remove existing goods not "moved". They went to the trash
    $toDel = array_diff($origContentIds, $allMovedGoodsIds);
    $plyrContent->loseContent($toDel, 'goods');

    // Do a final consistency check/validation on the database
    $plyrContent_refresh = $game->newPlayerContent($plId);
    $plyrContent_refresh->checkAll($game->newPlayerBoard($plId));

    GT_DBPlayer::setCardDone($game, $plId);

    // notifyAllPlayers
    switch (CardsManager::get($cardId)->getType()) {
      case CARD_PLANETS:
        $desc = "planet number {$player['card_action_choice']}";
        break;
      case CARD_ABANDONED_STATION:
        $desc = 'abandoned station';
        break;
      case CARD_SMUGGLERS:
        $desc = 'defeated smugglers';
        break;
      default:
        $game->throw_bug_report("Unknown cardtype in cargoChoice for card id $cardId");
    }

    $plyrContent->newContentNotif($newTileContent, $player['player_name']);

    $game->notifyAllPlayers('cargoChoice', clienttranslate('${player_name} places cargo from ${desc}'), [
      'player_name' => $player['player_name'],
      'desc' => $desc,
      'movedTileContent' => $movedTileContent,
      'newTileContent' => $newTileContent,
      'deleteContent' => array_values($toDel),
    ]);
  }

  ################# HELPERS #####################
  function noStopMsg($game)
  {
    $player_name = $game->getActivePlayerName();
    $game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} ' . 'doesn\'t stop'), [
      'player_name' => $player_name,
    ]);
  }
}

?>
