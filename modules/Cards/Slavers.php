<?php

namespace GT\Cards;
use GT\Models\HazardCard;

class Slavers extends HazardCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_SLAVERS;
    $this->name = clienttranslate('Slavers');
  }

  public function applyPenalty($game, $player)
  {
    if ($player['nb_crew'] <= $this->enemy_penalty) {
      $game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} loses all crew to ${type}'), [
        'player_name' => $player['player_name'],
        'type' => $this->type,
      ]);
      $plyrContent = $game->newPlayerContent($player['player_id']);
      $allCrewIds = $plyrContent->getContentIds('crew');

      // loseContent handles players giving up
      $plyrContent->loseContent($allCrewIds, 'crew', null, true);
      return null;
    } else {
      $game->setGameStateValue('cardArg2', $this->enemy_penalty);
      $game->setGameStateValue('cardArg3', GT_Constants::$CONTENT_TYPE_INT_MAP['crew']);
      $game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} must choose crew to lose to ${type}'), [
        'player_name' => $player['player_name'],
        'type' => $this->type,
      ]);
      return 'chooseCrew';
    }
  }

  public function giveReward($game, $player)
  {
    parent::giveReward($game, $player);
    $this->flightBoard->addCredits($player['player_id'], $this->reward);
    \GT_DBPlayer::setCardAllDone($game, $player['player_id']);
  }

  public function loseContent($game, $playerId, $typeToLose)
  {
    return $typeToLose == 'crew' ? 'nextPlayerEnemy' : null;
  }

  public function crewChoice($game, $playerId)
  {
    \GT_DBPlayer::setCardDone($game, $playerId);
    return 'nextPlayerEnemy';
  }

  static $instances = [
    [
      'id' => 0,
      'round' => 1,
      'enemy_strength' => 6,
      'enemy_penalty' => 3,
      'reward' => 5,
      'days_loss' => 1,
    ],
    ['id' => 20, 'round' => 2],
    ['id' => 40, 'round' => 3],
  ];
}
