<?php
namespace GT\Cards;

use GT\Models\EventCard;

class CombatZone extends EventCard
{
  private $lines;

  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_COMBAT_ZONE;
    $this->name = clienttranslate('Combat Zone');
    $this->lines = $params['lines'];
  }

  public function getCurrentHazard($progress)
  {
    // TODO: Remove hardcoded value. Maybe introduce a parameter isPenaltyShot and rely on it. Anyway hardcode is evil
    $penalty = $this->lines[3]['penalty_value'];
    if ($progress) {
      return $penalty[$progress];
    }
    return $penalty;
  }

  static $instances = [
    [
      'round' => 1,
      'id' => 15,
      'lines' => [
        1 => ['criterion' => 'crew', 'penalty_type' => 'days', 'penalty_value' => 3],
        2 => ['criterion' => 'engines', 'penalty_type' => 'crew', 'penalty_value' => 2],
        3 => ['criterion' => 'cannons', 'penalty_type' => 'shot', 'penalty_value' => ['s180', 'b180']],
      ],
    ],
    [
      'round' => 2,
      'id' => 35,
      'lines' => [
        1 => ['criterion' => 'cannons', 'penalty_type' => 'days', 'penalty_value' => 4],
        2 => ['criterion' => 'engines', 'penalty_type' => 'goods', 'penalty_value' => 3],
        3 => ['criterion' => 'crew', 'penalty_type' => 'shot', 'penalty_value' => ['s90', 's270', 's0', 'b180']],
      ],
    ],
    [
      'round' => 3,
      'id' => 55,
      'lines' => [
        1 => ['criterion' => 'crew', 'penalty_type' => 'goods', 'penalty_value' => 4],
        2 => ['criterion' => 'cannons', 'penalty_type' => 'crew', 'penalty_value' => 4],
        3 => ['criterion' => 'engines', 'penalty_type' => 'shot', 'penalty_value' => ['s90', 's270', 's0', 's0', 'b180', 'b180']],
      ],
    ],
  ];
}
