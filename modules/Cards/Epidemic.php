<?php
namespace GT\Cards;

use GT\Models\EventCard;

class Epidemic extends EventCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_EPIDEMIC;
    $this->name = clienttranslate('Epidemic');
  }

  static $instances = [['round' => 2, 'id' => 24], ['round' => 3, 'id' => 44]];
}
