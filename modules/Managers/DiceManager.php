<?php
namespace GT\Managers;

class DiceManager {
  public static function throwNewDice($game, $new_roll) {
    $die1 = $game->getGameStateValue('currentCardDie1');
    $die2 = $game->getGameStateValue('currentCardDie2');
    if ($new_roll) {
      $die1 = bga_rand(1, 6);
      $die2 = bga_rand(1, 6);
      $game->setGameStateValue('currentCardDie1', $die1);
      $game->setGameStateValue('currentCardDie2', $die2);
      $game->log("New dice roll $die1 and $die2.");
    }
    return [$die1, $die2];
  }
}
