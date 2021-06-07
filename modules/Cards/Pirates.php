<?php
namespace GT\Cards;

use GT\Models\EnemyCard;
use GT\Models\HazardCard;

class Pirates extends EnemyCard implements HazardCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_PIRATES;
    $this->name = clienttranslate('Pirates');
  }

  public function getCurrentHazard($idx = null)
  {
    if ($idx === null) {
      return $this->enemy_penalty;
    }

    return array_key_exists($idx, $this->enemy_penalty) ? $this->enemy_penalty[$idx] : null;
  }

  public function applyPenalty($game, $player)
  {
    \GT_DBPlayer::setCardChoice($game, $player['player_id'], CARD_CHOICE_APPLY_HAZARD);
    return;
  }

  public function giveReward($game, $player)
  {
    parent::giveReward($game, $player);
    $this->flightBoard->addCredits($player['player_id'], $this->reward);
    \GT_DBPlayer::setCardAllDone($game, $player['player_id']);
  }

  public function finishCard($game)
  {
    return 'cannonBlasts';
  }

  static $instances = [
    [
      'id' => 2,
      'round' => 1,
      'enemy_strength' => 5,
      'enemy_penalty' => ['s0', 'b0', 's0'],
      'reward' => 4,
      'days_loss' => 1,
    ],
    ['id' => 22, 'round' => 2],
    ['id' => 42, 'round' => 3],
  ];
}
