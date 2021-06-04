<?php

/* Collection of functions to handle states associated with cards */

use GT\Managers\CardsManager;

class GT_StatesCard extends APP_GameClass
{
  public function __construct()
  {
  }

  function currentCardData($game)
  {
    $cardId = $game->getGameStateValue('currentCard');
    if ($cardId < 0) {
      return ['id' => $cardId];
    }

    $card = CardsManager::get($cardId);

    return [
      'id' => $cardId,
      'type' => $card->getType(),
      'curHazard' => GT_Hazards::getHazardRoll($game, $card),
      'card_line_done' => GT_DBPlayer::getCardProgress($game),
    ];
  }

  function stDrawCard($game)
  {
    GT_DBPlayer::clearCardProgress($game);

    $cardOrderInFlight = $game->getGameStateValue('cardOrderInFlight');
    $cardOrderInFlight++;
    $game->setGameStateValue('cardOrderInFlight', $cardOrderInFlight);
    $currentCardId = $game->getUniqueValueFromDB('SELECT card_id id FROM card ' . "WHERE card_order=$cardOrderInFlight");

    $game->setGameStateValue('cardArg1', -1);
    $game->setGameStateValue('cardArg2', -1);
    $game->setGameStateValue('cardArg3', -1);

    if (is_null($currentCardId)) {
      // no more cards, this flight is done
      $cardType = NO_CARD;
      $game->setGameStateValue('currentCard', NO_CARD);
    } else {
      $game->setGameStateValue('currentCard', $currentCardId);
      GT_Hazards::resetHazardProgress($game);

      // temp, so that there is an active player when going to notImpl state
      if ($game->getUniqueValueFromDB('SELECT global_value FROM global WHERE global_id=2') == 0) {
        // temp
        $game->myActiveNextPlayer();
      } // temp

      $card = CardsManager::get($currentCardId);
      $cardType = $card->getType();

      $game->notifyAllPlayers('cardDrawn', clienttranslate('New card drawn: ${cardTypeStr}'), [
        'i18n' => ['cardTypeStr'],
        'cardTypeStr' => $card->getName(),
        'cardRound' => $card->getRound(),
        'cardId' => $currentCardId,
        'cardData' => self::currentCardData($game),
      ]);
    }

    return $cardType;
  }

  function stEpidemic($game)
  {
    $players = GT_DBPlayer::getPlayersInFlight($game, '', $order = 'DESC');

    foreach ($players as $plId => $player) {
      $plyrBoard = $game->newPlayerBoard($plId);
      $plyrContent = $game->newPlayerContent($plId);
      $crewContent = $plyrContent->getContent("crew");
      $tilesWithCrew = array();
      foreach($crewContent as $curCrew){
          $tilesWithCrew[$curCrew['tile_id']] = $curCrew;
      }
      $lostCrewIds = array();
      foreach($tilesWithCrew as $tid => $curCrew){
          $tile = $plyrBoard->getTileById($tid);
          $connected_tiles = $plyrBoard->getConnectedTiles($tile);
          foreach($connected_tiles as $conn_tile){
              if(array_key_exists($conn_tile['id'], $tilesWithCrew)){
                  $lostCrewIds[] = $curCrew['content_id'];
                  break;
              }
          }
      }
      $plyrContent->loseContent($lostCrewIds, 'crew', null, true);
    }

    return 'nextCard';
  }

  function stStardust($game)
  {
    $players = GT_DBPlayer::getPlayersInFlight($game, '', $order = 'ASC');

    $flBrd = $game->newFlightBoard($players);

    foreach ($players as $plId => $player) {
      $newPlPos = $flBrd->moveShip($plId, -$player['exp_conn'], $players);
    }
    return 'nextCard';
  }

  function stOpenspace($game)
  {
    $nextState = 'nextCard'; // Will be changed to powerEngines if someone
    // needs to choose if they use batteries
    $players = GT_DBPlayer::getPlayersForCard($game);

    // Do not pass $players to flightBoard. We need to consider all players still in flight,
    //    $players here is only those who still have yet to act
    $flBrd = $game->newFlightBoard();

    foreach ($players as $plId => $player) {
      if ($player['max_eng'] == 0) {
        $flBrd->giveUp($plId, 'cannot power engines for Open Space');
        GT_DBPlayer::setCardDone($game, $plId);
      } elseif ($player['min_eng'] == $player['max_eng']) {
        // No choice to do for this player, so we move it now and notify players.
        $flBrd->moveShip($plId, (int) $player['min_eng']);
        GT_DBPlayer::setCardDone($game, $plId);
      } else {
        // min and max different means that this player can activate a double
        // engine or more, so we need an activeplayer state to ask them
        // Infos for player: here or in args? In args.
        GT_DBPlayer::setCardInProgress($game, $plId);
        $game->gamestate->changeActivePlayer($plId);
        $nextState = 'powerEngines';
        break; // End of this foreach loop because we need to ask this
        // player before processing the following players.
      }
    } // end of foreach players

    return $nextState;
  }

  function stAbandoned($game)
  {
    $nextState = 'nextCard'; // Will be changed to exploreAbandoned  if someone
    // has a big enough crew
    $cardId = $game->getGameStateValue('currentCard');
    $players = GT_DBPlayer::getPlayersForCard($game);

    foreach ($players as $plId => $player) {
      if ($player['nb_crew'] < CardsManager::get($cardId)->getCrew()) {
        $game->notifyAllPlayers(
          'onlyLogMessage',
          clienttranslate('${player_name} ' . 'doesn\'t have a big enough crew to benefit from this card'),
          ['player_name' => $player['player_name']]
        );
        GT_DBPlayer::setCardDone($game, $plId);
      } else {
        // This player has enough crew members to use the card, so we need
        // to ask them if they want to.
        GT_DBPlayer::setCardInProgress($game, $plId);
        $game->gamestate->changeActivePlayer($plId);
        $nextState = 'exploreAbandoned';
        break; // End of this foreach loop because we need to ask this
        // player before processing the following players.
      }
    }

    return $nextState;
  }

  function stPlanets($game)
  {
    // Setup active player to choose a planet

    $cardId = $game->getGameStateValue('currentCard');
    $players = GT_DBPlayer::getPlayersForCard($game);

    foreach ($players as $plId => $player) {
      GT_DBPlayer::setCardInProgress($game, $plId);
      $game->gamestate->changeActivePlayer($plId);
      return 'choosePlanet';
    }

    // No one else to choose - move all ships based on card, furthest back first
    $players = GT_DBPlayer::getPlayersInFlight($game, '', $order = 'ASC');
    $flBrd = $game->newFlightBoard($players);

    $nbDays = -CardsManager::get($cardId)->getDaysLoss();
    foreach ($players as $plId => $player) {
      if ($player['card_action_choice'] == '0') {
        continue;
      }
      $flBrd->moveShip($plId, $nbDays);
    }

    return 'nextCard';
  }

  function stMeteoric($game)
  {
    $cardId = $game->getGameStateValue('currentCard');
    $card = CardsManager::get($cardId);

    $idx = $game->getGameStateValue('currentCardProgress');
    if ($idx < 0) {
      $idx = 0; // start of the card
      GT_Hazards::nextHazard($game, 0);
    }

    $game->dump_var("Entering meteor with current card $cardId Meteor $idx.", $card);
    while ($idx < count($card->getCurrentHazard())) {
      $game->log("Running meteor $idx.");

      // Get previous dice roll, if available. If not roll and notif
      $hazResults = GT_Hazards::getHazardRoll($game, $card, $idx);

      if ($hazResults['missed']) {
        $game->notifyAllPlayers('hazardMissed', clienttranslate('Meteor missed all ships'), [
          'hazResults' => $hazResults,
        ]);
        GT_Hazards::nextHazard($game, ++$idx);
        continue;
      }

      // Go through players until finding one that has to act
      $players = GT_DBPlayer::getPlayersForCard($game);
      foreach ($players as $plId => $player) {
        $game->log("stMeteoric for player $plId, index $idx.");
        $nextState = GT_Hazards::applyHazardToShip($game, $hazResults, $player);
        $game->log("Got $nextState");
        if ($nextState) {
          GT_DBPlayer::setCardInProgress($game, $plId);
          $game->gamestate->changeActivePlayer($plId);
          return $nextState;
        }
      }

      $game->log("Finished index $idx");
      // no players left to act for this hazard, go to next hazard
      GT_Hazards::nextHazard($game, ++$idx);
      GT_DBPlayer::clearCardProgress($game);
    }

    // TODO hide dice (see how we're hiding cards)
    return 'nextCard';
  }

  function stEnemy($game)
  {
    // Loop through active players for this card
    // If no cannon choice needs to be made, set cannon power and move to enemy_results
    // Otherwise, move to ask player to use power cannons
    $cardId = $game->getGameStateValue('currentCard');
    $card = CardsManager::get($cardId);

    $players = GT_DBPlayer::getPlayersForCard($game);
    foreach ($players as $plId => $player) {
      $game->log("stEnemy for player $plId");
      $enemy = new GT_Enemy($game, $card, $player);
      $nextState = '';
      $cannonPower = $enemy->playerCannonValue();
      if (is_null($cannonPower)) {
        $game->notifyAllPlayers(
          'onlyLogMessage',
          clienttranslate('${player_name} must decide whether to activate a cannon against ${type}'),
          ['player_name' => $player['player_name'], 'type' => $card->getType()]
        );
        $nextState = 'powerCannons';
      } else {
        $game->setGameStateValue('cardArg1', $cannonPower);
        $nextState = 'enemyResults';
      }

      GT_DBPlayer::setCardInProgress($game, $plId);
      $game->gamestate->changeActivePlayer($plId);
      return $nextState;
    }

    GT_DBPlayer::setCardAllDone($game);
    return 'nextCard';
  }

  function stCannonBlasts($game)
  {
    // In process of looping over all cannon blasts from combat or pirates card
    $cardId = $game->getGameStateValue('currentCard');
    $card = CardsManager::get($cardId);

    $player = GT_DBPlayer::getPlayerCardInProgress($game);

    $idx = $game->getGameStateValue('currentCardProgress');
    if ($idx < 0) {
      $idx = 0; // start of the card
      GT_Hazards::nextHazard($game, 0);
    } else {
      // Returning here from another state, move on to next hazard
      $game->log("Finished index $idx");
      GT_Hazards::nextHazard($game, ++$idx);
    }

    $game->dump_var("Entering cannon blasts with current card $cardId blast $idx.", $card);
    $blasts = $card->getCurrentHazard();

    while ($idx < count($blasts)) {
      $game->dump_var("Running cannon blast $idx on player.", $player);
      $nextState = null;

      // Get a dice roll
      $hazResults = GT_Hazards::getHazardRoll($game, $card, $idx);

      if ($hazResults['missed']) {
        $game->notifyAllPlayers('hazardMissed', clienttranslate('Cannon blast missed ${player_name}\'s ship'), [
          'hazResults' => $hazResults,
          'player_id' => $player['player_id'],
          'player_name' => $player['player_name'],
        ]);
      } else {
        $nextState = GT_Hazards::applyHazardToShip($game, $hazResults, $player);
      }

      if ($nextState) {
        return $nextState;
      }

      $game->log("Finished index $idx");
      GT_Hazards::nextHazard($game, ++$idx);
    }

    GT_Hazards::resetHazardProgress($game);
    GT_DBPlayer::setCardDone($game, $player['player_id']);
    return 'nextPlayerEnemy';
  }

  // ###################### HELPERS ##########################
}

?>
