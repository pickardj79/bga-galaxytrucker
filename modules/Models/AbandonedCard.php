<?php
namespace GT\Models;

/*
 * HazardCard: base class to describe event cards containing hazards
 */
class AbandonedCard extends EventCard
{
  protected $crew;
  protected $reward;
  protected $days_loss;

  public function __construct($params) {
    parent::__construct($params);
    $this->reward = $params['reward'];
    $this->crew = $params['crew'];
    $this->days_loss = $params['days_loss'];
  }

  public function getCrew() {
    return $this->crew;
  }

  public function getReward() {
    return $this->reward;
  }

  public function exploreChoice($game, $playerId) {}
}