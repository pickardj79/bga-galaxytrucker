<?php

use GT\Models\EnemyCard;

class GT_Enemy extends APP_GameClass
{
  public function __construct($game, $card, $player)
  {
    $this->game = $game;
    $this->card = $card;
    $this->player = $player;
  }

  function playerCannonValue()
  {
    // If we have a definitive cannon value relative to the current card, return it
    // Null otherwise
    $pl = $this->player;
    if ($pl['min_cann_x2'] == $pl['max_cann_x2']) {
      return $pl['min_cann_x2'] / 2;
    }

    $enemy_str = $this->card->getEnemyStrength();

    if ($enemy_str < $pl['min_cann_x2'] / 2) {
      return $pl['min_cann_x2'] / 2;
    } elseif ($enemy_str > $pl['max_cann_x2'] / 2) {
      return $pl['max_cann_x2'] / 2;
    }

    return;
  }

  function fightPlayer($playerCannon)
  {
    if ($playerCannon < 0) {
      $this->game->throw_bug_report_dump("playerCannon less than zero: $playerCannon");
    }

    $enemy_str = $this->card->getEnemyStrength();
    if ($enemy_str < $playerCannon) {
      return $this->giveReward();
    } elseif ($enemy_str > $playerCannon) {
      return $this->applyPenalty();
    } else {
      return $this->fightIsTie();
    }
  }

  function fightIsTie()
  {
    $this->game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} fights ${name} to a draw'), [
      'player_name' => $this->player['player_name'],
      'name' => $this->card->getName(),
    ]);
    return null;
  }

  function giveReward()
  {
    // based on type of card give the correct reward and return the needed state
    $this->game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} defeats ${name} in battle'), [
      'player_name' => $this->player['player_name'],
      'name' => $this->card->getName(),
    ]);

    $nextState = null;
    if ($this->card instanceof EnemyCard) {
      $nextState = $this->card->giveReward($this->game, $this->player);
    } else {
      $this->game->throw_bug_report("Unknown card type ({$this->card->getType()}) in GT_Enemy::giveReward");
    }
    return $nextState;
  }

  function applyPenalty()
  {
    // based on type of card, apply the penalty and return needed state
    $this->game->notifyAllPlayers(
      'onlyLogMessage',
      clienttranslate('${player_name} is defeated by ${name} in battle'),
      ['player_name' => $this->player['player_name'], 'name' => $this->card->getName()]
    );

    if ($this->card instanceof EnemyCard) {
      return $this->card->applyPenalty($this->game, $this->player);
    } else {
      $this->game->throw_bug_report("Unknown card type ({$this->card->getType()}) in GT_Enemy::applyPenalty");
    }
  }
}

?>
