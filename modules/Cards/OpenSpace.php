<?php
namespace GT\Cards;

use GT\Models\EventCard;

class OpenSpace extends EventCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_OPEN_SPACE;
    $this->name = clienttranslate('Open Space');
  }

  static $instances = [
    ['round' => 1, 'id' => 4],
    ['round' => 1, 'id' => 5],
    ['round' => 1, 'id' => 6],
    ['round' => 1, 'id' => 7],
    ['round' => 2, 'id' => 25],
    ['round' => 2, 'id' => 26],
    ['round' => 2, 'id' => 27],
    ['round' => 3, 'id' => 45],
    ['round' => 3, 'id' => 46],
    ['round' => 3, 'id' => 47],
  ];
}
