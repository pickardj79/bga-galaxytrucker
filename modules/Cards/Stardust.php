<?php
namespace GT\Cards;

use GT\Models\EventCard;

class Stardust extends EventCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_STARDUST;
    $this->name = clienttranslate('Stardust');
  }

  static $instances = [['round' => 1, 'id' => 3], ['round' => 2, 'id' => 23]];
}
