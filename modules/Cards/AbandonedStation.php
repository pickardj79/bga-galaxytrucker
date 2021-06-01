<?php
namespace GT\Cards;

use GT\Models\AbandonedCard;

class AbandonedStation extends AbandonedCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_ABANDONED_STATION;
    $this->name = clienttranslate('Abandoned Station');
  }

  public function exploreChoice($game, $playerId)
  {
    $player = \GT_DBPlayer::getPlayer($game, $playerId);
    if ($player['nb_crew'] < $this->crew) {
      $game->throw_bug_report_dump('Explore choice: not enough crew members', $player);
    }

    $nbDays = -$this->days_loss;
    $game->newFlightBoard()->moveShip($playerId, $nbDays);
    return 'placeGoods';
  }

  static $instances = [
    [
      'round' => 1,
      'id' => 18,
      'crew' => 5,
      'reward' => ['yellow', 'green'],
      'days_loss' => 1,
    ],
    ['round' => 1, 'id' => 19, 'crew' => 6, 'reward' => ['red', 'red'], 'days_loss' => 1],
    [
      'round' => 2,
      'id' => 38,
      'crew' => 8,
      'reward' => ['yellow', 'yellow', 'green'],
      'days_loss' => 2,
    ],
    ['round' => 2, 'id' => 39, 'crew' => 7, 'reward' => ['red', 'yellow'], 'days_loss' => 1],
    [
      'round' => 3,
      'id' => 58,
      'crew' => 9,
      'reward' => ['red', 'yellow', 'green', 'blue'],
      'days_loss' => 2,
    ],
    [
      'round' => 3,
      'id' => 59,
      'crew' => 10,
      'reward' => ['yellow', 'yellow', 'green', 'green'],
      'days_loss' => 2,
    ],
  ];
}
