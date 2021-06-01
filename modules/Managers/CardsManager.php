<?php
namespace GT\Managers;

use GT\Helpers\Utils;

class CardsManager {
  public static $classes = [
    CARD_SLAVERS => 'Slavers',
    CARD_SMUGGLERS => 'Smugglers',
    CARD_PIRATES => 'Pirates',
    CARD_STARDUST => 'Stardust',
    CARD_EPIDEMIC => 'Epidemic',
    CARD_SABOTAGE => 'Sabotage',
    CARD_OPEN_SPACE => 'OpenSpace',
    CARD_METEORIC_SWARM => 'MeteoricSwarm',
    CARD_PLANETS => 'Planets',
    CARD_COMBAT_ZONE => 'CombatZone',
    CARD_ABANDONED_SHIP => 'AbandonedShip',
    CARD_ABANDONED_STATION => 'AbandonedStation',
    ];

  public static function getTypeById($id) {
    $keyValuePair = array_filter(self::$classes, function ($name) use ($id) {
      $className = 'GT\Cards\\'.$name;
      return self::getInstanceById($className, $id);
    });

    // Sanity checks
    if (count($keyValuePair) > 1) {
      throw new \BgaVisibleSystemException('CardsManager::getTypeById returned more than one type for card id '.$id);
    } elseif (count($keyValuePair) == 0) {
      throw new \BgaVisibleSystemException('CardsManager::getTypeById could not find a card with id '.$id);
    }

    return array_keys($keyValuePair)[0];
  }

  private static function getInstanceById($className, $id) {
    return Utils::array_find($className::$instances, function($instance) use ($id) { return $instance['id'] == $id; });
  }

  public static function get($id) {
    $cardType = self::getTypeById($id);
    $className = 'GT\Cards\\'.self::$classes[$cardType];
    $params = self::getInstanceById($className, $id);
    return new $className($params);
  }

  // Used for TestGameState only at the moment
  public static function getInstanceIdByType($cardType, $index = 0) {
    $className = 'GT\Cards\\'.self::$classes[$cardType];
    return $className::$instances[$index]['id'];
  }
}
