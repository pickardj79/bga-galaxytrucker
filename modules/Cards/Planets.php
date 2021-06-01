<?php
namespace GT\Cards;

use GT\Models\EventCard;

class Planets extends EventCard
{
  private $planets;
  private $days_loss;

  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_PLANETS;
    $this->name = clienttranslate('Planets');
    $this->planets = $params['planets'];
    $this->days_loss = $params['days_loss'];
  }

  public function getPlanets()
  {
    return $this->planets;
  }

  public function getDaysLoss() {
    return $this->days_loss;
  }

  public function jsonSerialize()
  {
    $params = parent::jsonSerialize();
    $params['planets'] = $this->planets;
    return $params;
  }

  static $instances = [
    [
      'round' => 1,
      'id' => 11,
      'planets' => [
        1 => ['red', 'green', 'blue', 'blue', 'blue'],
        2 => ['red', 'yellow', 'blue'],
        3 => ['red', 'blue', 'blue', 'blue'],
        4 => ['red', 'green'],
      ],
      'days_loss' => 3,
    ],
    [
      'round' => 1,
      'id' => 12,
      'planets' => [
        1 => ['red', 'red'],
        2 => ['red', 'blue', 'blue'],
        3 => ['yellow'],
      ],
      'days_loss' => 2,
    ],
    [
      'round' => 1,
      'id' => 13,
      'planets' => [
        1 => ['yellow', 'green', 'blue', 'blue'],
        2 => ['yellow', 'yellow'],
      ],
      'days_loss' => 3,
    ],
    [
      'round' => 1,
      'id' => 14,
      'planets' => [
        1 => ['green', 'green'],
        2 => ['yellow'],
        3 => ['blue', 'blue', 'blue'],
      ],
      'days_loss' => 2,
    ],
    [
      'round' => 2,
      'id' => 31,
      'planets' => [
        1 => ['red', 'red', 'red', 'yellow'],
        2 => ['red', 'red', 'green', 'green'],
        3 => ['red', 'blue', 'blue', 'blue', 'blue'],
      ],
      'days_loss' => 4,
    ],
    [
      'round' => 2,
      'id' => 32,
      'planets' => [
        1 => ['red', 'red'],
        2 => ['green', 'green', 'green', 'green'],
      ],
      'days_loss' => 3,
    ],
    [
      'round' => 2,
      'id' => 33,
      'planets' => [
        1 => ['red', 'yellow'],
        2 => ['yellow', 'green', 'blue'],
        3 => ['green', 'green'],
        4 => ['yellow'],
      ],
      'days_loss' => 2,
    ],
    [
      'round' => 2,
      'id' => 34,
      'planets' => [
        1 => ['green', 'green', 'green', 'green'],
        2 => ['yellow', 'yellow'],
        3 => ['blue', 'blue'],
      ],
      'days_loss' => 3,
    ],
    [
      'round' => 3,
      'id' => 51,
      'planets' => [
        1 => ['yellow', 'yellow', 'yellow', 'yellow', 'yellow'],
        2 => ['red', 'yellow', 'yellow'],
        3 => ['red', 'red'],
      ],
      'days_loss' => 5,
    ],
    [
      'round' => 3,
      'id' => 52,
      'planets' => [
        1 => ['green', 'blue', 'blue', 'blue', 'blue'],
        2 => ['yellow', 'blue'],
      ],
      'days_loss' => 1,
    ],
    [
      'round' => 3,
      'id' => 53,
      'planets' => [
        1 => ['red', 'yellow', 'blue'],
        2 => ['red', 'green', 'blue', 'blue'],
        3 => ['red', 'blue', 'blue', 'blue', 'blue'],
      ],
      'days_loss' => 2,
    ],
    [
      'round' => 3,
      'id' => 54,
      'planets' => [
        1 => ['red', 'red', 'red'],
        2 => ['yellow', 'yellow', 'yellow'],
        3 => ['green', 'green', 'green'],
        4 => ['blue', 'blue', 'blue'],
      ],
      'days_loss' => 3,
    ],
  ];
}
