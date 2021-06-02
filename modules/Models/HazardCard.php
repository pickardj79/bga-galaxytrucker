<?php
namespace GT\Models;

/*
 * HazardCard: base class to describe event cards containing hazards
 */
class HazardCard extends EventCard
{
  protected $enemy_strength;
  protected $enemy_penalty;
  protected $reward;
  protected $days_loss;

  protected $flightBoard;

  public function __construct($params)
  {
    parent::__construct($params);
    $this->enemy_strength = $params['enemy_strength'];
    $this->enemy_penalty = $params['enemy_penalty'];
    $this->reward = $params['reward'];
    $this->days_loss = $params['days_loss'];
  }

  public function getEnemyStrength()
  {
    return $this->enemy_strength;
  }

  public function getReward()
  {
    return $this->reward;
  }

  public function applyPenalty($game, $player)
  {
  }

  public function giveReward($game, $player)
  {
    $this->flightBoard = $game->newFlightBoard();
    $this->flightBoard->moveShip($player['player_id'], -$this->days_loss);
  }

  public function finishCard($game)
  {
    GB_DBPlayer::setCardAllDone($game);
    return 'nextCard';
  }
}
