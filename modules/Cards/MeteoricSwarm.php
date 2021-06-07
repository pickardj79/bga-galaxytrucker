<?php
namespace GT\Cards;

use GT\Models\EventCard;
use GT\Models\HazardCard;

class MeteoricSwarm extends EventCard implements HazardCard
{
  private $meteors;

  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_METEORIC_SWARM;
    $this->name = clienttranslate('Meteoric Swarm');
    $this->meteors = $params['meteors'];
  }

  public function getCurrentHazard($idx = null)
  {
    if ($idx === null) {
      return $this->meteors;
    }
    return array_key_exists($idx, $this->meteors) ? $this->meteors[$idx] : null;
  }

  static $instances = [
    ['round' => 1, 'id' => 8, 'meteors' => ['b0', 's270', 's90']],
    ['round' => 1, 'id' => 9, 'meteors' => ['s0', 's180', 's270', 's90']],
    ['round' => 1, 'id' => 10, 'meteors' => ['b0', 's0', 'b0']],
    ['round' => 2, 'id' => 28, 'meteors' => ['s0', 'b0', 's270', 'b270', 's270']],
    ['round' => 2, 'id' => 29, 'meteors' => ['s0', 's0', 's180', 's180', 's270', 's90']],
    ['round' => 2, 'id' => 30, 'meteors' => ['s0', 'b0', 's90', 'b90', 's90']],
    ['round' => 3, 'id' => 48, 'meteors' => ['b90', 's90', 'b90', 's0', 's180']],
    [
      'round' => 3,
      'id' => 49,
      'meteors' => ['b0', 's180', 's180', 's270', 's270', 's90', 's90'],
    ],
    ['round' => 3, 'id' => 50, 'meteors' => ['b270', 's270', 'b270', 's0', 's180']],
  ];
}
