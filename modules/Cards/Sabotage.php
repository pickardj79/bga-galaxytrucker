<?php
namespace GT\Cards;

use GT\Models\EventCard;

class Sabotage extends EventCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_SABOTAGE;
    $this->name = clienttranslate('Sabotage');
  }

  static $instances = [['round' => 3, 'id' => 43]];
}
