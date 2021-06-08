<?php
namespace GT\Managers;

class PlayersManager {
  // TODO: Reuse for Combat Zone
  public static function getPlayerWithLowestCrew($players) {
    $lowestCrewNumber = min(array_map(function ($player) {
      return (int)$player['nb_crew'];
    }, $players));
    $playersWithLowestCrewNumber = array_filter($players, function ($player) use ($lowestCrewNumber) {
      return $player['nb_crew'] == $lowestCrewNumber;
    });
    return array_shift($playersWithLowestCrewNumber); // DESC player_position order is still kept, isn't it?
  }
}
