<?php
namespace GT\Models;

/*
 * EventCard: base class to describe event cards drawn during flight phase
 */
class EventCard implements \JsonSerializable
{
  /*
  * Attributes
  */
  protected $id;
  protected $round;
  protected $type;
  protected $name;
  static $instances = [];

  public function __construct($params) {
    $this->id = $params['id'];
    $this->round = $params['round'];
  }

  public function jsonSerialize()
  {
    return [
      'id' => $this->id,
    ];
  }

  /*
  * Getters
  */
  public function getId() {
    return $this->id;
  }

  public function getRound()
  {
    return $this->round;
  }

  public function getType()
  {
    return $this->type;
  }

  public function getName() {
    return $this->name;
  }

  public function getCurrentHazard($progress) {}
  public function loseContent($game, $playerId, $typeToLose) {}
}
