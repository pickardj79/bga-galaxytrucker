<?php
namespace GT\Cards;

use GT\Models\EnemyCard;

class Smugglers extends EnemyCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_SMUGGLERS;
    $this->name = clienttranslate('Smugglers');
  }

  public function applyPenalty($game, $player)
  {
    $penalty = $this->enemy_penalty;
    $plyrContent = $game->newPlayerContent($player['player_id']);
    $goodsIds = $plyrContent->getContentIds('goods');

    if (count($goodsIds) == $penalty) {
      $plyrContent->loseContent($goodsIds, 'goods', null, true);
      return null;
    }

    // lose more valuable goods first. If a tie, let player choose
    if (count($goodsIds) > $penalty) {
      $toloseIds = [];
      $left_to_lose = $penalty;
      foreach (\GT_Constants::$ALLOWABLE_SUBTYPES['goods'] as $idx => $subtype) {
        $cur = $plyrContent->getContentIds('goods', $subtype);
        if (count($cur) <= $left_to_lose) {
          // haven't lost enough yet or lost exact amount. Lose them all
          $toloseIds = array_merge($toloseIds, $cur);
          $left_to_lose = $left_to_lose - count($cur);
          if ($left_to_lose == 0) {
            // lost exact amount, done with player
            $plyrContent->loseContent($toloseIds, 'goods', null, true);
            return null;
          }
        } else {
          // TODO: MUST STORE IN DB COLOR AND NUMBER LEFT TO LOSE
          // lost enough and there are excess
          // player must choose which goods to lose
          $plyrContent->loseContent($toloseIds, 'goods', null, true);
          $game->setGameStateValue('cardArg1', $idx);
          $game->setGameStateValue('cardArg2', $left_to_lose);
          $game->setGameStateValue('cardArg3', \GT_Constants::$CONTENT_TYPE_INT_MAP['goods']);
          return 'loseGoods';
        }
      }
      $game->throw_bug_report_dump("Should not get here. Left: $left_to_lose", $toloseIds);
    }

    // not enough cargo, lose it all then, need to lose batteries
    $plyrContent->loseContent($goodsIds, 'goods', null, true);

    $reqCell = $penalty - count($goodsIds);
    $cellIds = $plyrContent->getContentIds('cell');
    if (count($cellIds) > $reqCell) {
      $game->setGameStateValue('cardArg2', $reqCell);
      $game->setGameStateValue('cardArg3', \GT_Constants::$CONTENT_TYPE_INT_MAP['cell']);
      return 'loseCells';
    } else {
      // lose all cells
      $plyrContent->loseContent($cellIds, 'cell', null, true);
      return null;
    }
  }

  public function giveReward($game, $player)
  {
    parent::giveReward($game, $player);
    $game->notifyAllPlayers('onlyLogMessage', clienttranslate('${player_name} must place new cargo'), [
      'player_name' => $player['player_name'],
    ]);
    return 'placeGoods';
  }

  public function loseContent($game, $playerId, $typeToLose)
  {
    return $typeToLose == 'cell' || $typeToLose == 'goods' ? 'nextPlayerEnemy' : null;
  }

  static $instances = [
    [
      'id' => 1,
      'round' => 1,
      'enemy_strength' => 4,
      'enemy_penalty' => 2,
      'reward' => ['yellow', 'green', 'blue'],
      'days_loss' => 1,
    ],
    ['round' => 2, 'id' => 21],
    ['round' => 3, 'id' => 41],
  ];
}
