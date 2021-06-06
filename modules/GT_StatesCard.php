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
      'curHazard' => (new GT_Hazards($game, $card))->getHazardRoll(),
      'card_line_done' => GT_DBPlayer::getCardProgress($game),
    ];
  }

  function stDrawCard($game)
  {
    GT_DBPlayer::resetCard($game);

    $cardOrderInFlight = $game->getGameStateValue('cardOrderInFlight');
    $cardOrderInFlight++;
    $game->setGameStateValue('cardOrderInFlight', $cardOrderInFlight);
    $currentCardId = $game->getUniqueValueFromDB(
      'SELECT card_id id FROM card ' . "WHERE card_order=$cardOrderInFlight"
    );

    $game->setGameStateValue('cardArg1', -1);
    $game->setGameStateValue('cardArg2', -1);
    $game->setGameStateValue('cardArg3', -1);

    if (is_null($currentCardId)) {
      // no more cards, this flight is done
      $cardType = NO_CARD;
      $game->setGameStateValue('currentCard', NO_CARD);
    } else {
      $game->setGameStateValue('currentCard', $currentCardId);
      (new GT_Hazards($game))->resetHazardProgress();

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
    $players = GT_DBPlayer::getPlayersInFlight($game);

    foreach ($players as $plId => $player) {
      GT_DBPlayer::setCardChoice($game, $plId, CARD_CHOICE_APPLY_HAZARD);
    }

    return (new GT_Hazards($game, $card))->applyHazards();
  }


  function stEnemy($game)
  {
    // Loop through active players for this card
    // If no cannon choice needs to be made, set cannon power and move to enemy_results
    // Otherwise, move to ask player to use power cannons
    $cardId = $game->getGameStateValue('currentCard');
    $card = CardsManager::get($cardId);

    $players = GT_DBPlayer::getPlayersForCard($game);
    $game->dump_var('players for card', $players);
    foreach ($players as $plId => $player) {
      $game->log("stEnemy for player $plId");
      $enemy = new GT_Enemy($game, $card, $player);
      $nextState = '';
      $cannonPower = $enemy->playerCannonValue();
      if (is_null($cannonPower)) {
        $game->notifyAllPlayers(
          'onlyLogMessage',
          clienttranslate('${player_name} must decide whether to activate a cannon against ${name}'),
          ['player_name' => $player['player_name'], 'name' => $card->getName()]
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

    // Only reached here if no players returned from getPlayersForCard
    $game->dump_var('Finished stEnemy for players', $players);
    return $card->finishCard($game);
  }


}

?>
