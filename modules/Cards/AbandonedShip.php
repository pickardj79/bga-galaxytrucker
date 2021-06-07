<?php
namespace GT\Cards;

use GT\Models\AbandonedCard;

class AbandonedShip extends AbandonedCard
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->type = CARD_ABANDONED_SHIP;
    $this->name = clienttranslate('Abandoned Ship');
  }

  public function exploreChoice($game, $playerId)
  {
    $player = \GT_DBPlayer::getPlayer($game, $playerId);
    if ($player['nb_crew'] > $this->crew) {
      $game->setGameStateValue('cardArg2', $this->crew);
      $game->setGameStateValue('cardArg3', \GT_Constants::$CONTENT_TYPE_INT_MAP['crew']);
      return 'chooseCrew';
    } elseif ($player['nb_crew'] == $this->crew) {
      // This player sends ALL their remaining crew members
      // Remove all crew members:
      $plyrContent = $game->newPlayerContent($playerId);
      $crewIds = $plyrContent->getContentIds('crew');
      $plyrContent->loseContent($crewIds, 'crew', null, true);

      $flBrd = $game->newFlightBoard();
      $flBrd->addCredits($playerId, $this->reward);
      $flBrd->giveUp($playerId, 'sent whole crew to the abandoned ship');

      return 'nextCard';
    } else {
      $game->throw_bug_report_dump('Explore choice: not enough crew members', $player);
    }
  }

  public function loseContent($game, $playerId, $typeToLose)
  {
    if ($typeToLose == 'crew') {
      $flBrd = $game->newFlightBoard();
      $flBrd->addCredits($playerId, $this->reward);
      $nbDays = -$this->days_loss;
      $flBrd->moveShip($playerId, $nbDays);
      return 'nextCard';
    } else {
      return null;
    }
  }

  static $instances = [
    ['round' => 1, 'id' => 16, 'crew' => 3, 'reward' => 4, 'days_loss' => 1],
    ['round' => 1, 'id' => 17, 'crew' => 2, 'reward' => 3, 'days_loss' => 1],
    ['round' => 2, 'id' => 36, 'crew' => 5, 'reward' => 8, 'days_loss' => 2],
    ['round' => 2, 'id' => 37, 'crew' => 4, 'reward' => 6, 'days_loss' => 1],
    ['round' => 3, 'id' => 56, 'crew' => 7, 'reward' => 11, 'days_loss' => 2],
    ['round' => 3, 'id' => 57, 'crew' => 6, 'reward' => 10, 'days_loss' => 2],
  ];
}
