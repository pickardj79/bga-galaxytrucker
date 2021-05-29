<?php
namespace GT\Cards;

use GT\Models\HazardCard;

class Pirates extends HazardCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_PIRATES;
    $this->name = clienttranslate('Pirates');
  }

  public function getCurrentHazard($progress = null)
  {
    if ($progress === null) {
      return $this->enemy_penalty;
    }
    return $this->enemy_penalty[$progress];
  }

  public function applyPenalty($game, $player)
  {
    return 'cannonBlasts';
  }

  public function giveReward($game, $player)
  {
    parent::giveReward($game, $player);
    $this->flightBoard->addCredits($player['player_id'], $this->reward);
    \GT_DBPlayer::setCardAllDone($game, $player['player_id']);
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