<?php
/* Collection of utilities to interface with component table */

class GT_DBComponent extends APP_GameClass
{
  public function __construct()
  {
  }

  function removeComponents($game, $plId, $tileIds)
  {
    // remove components from a ship, placing them in the appropriate discard pile
    //    and setting other fields as needed (orient, component_x/y, aside_discard)
    if (!$tileIds) {
      return;
    } // nothing to do

    // Determine which piles to put each tile and the aside_discard to assign
    $dbCnt = $game::getObjectFromDB("
            SELECT max(aside_discard) max_aside,
                   sum(if(component_x=-1,1,0)) nbr_neg1,
                   sum(if(component_x=-2,1,0)) nbr_neg2
            FROM component WHERE component_player = $plId AND aside_discard IS NOT NULL");

    $values = [];
    foreach ($tileIds as $id) {
      if ($dbCnt['nbr_neg1'] < $dbCnt['nbr_neg2']) {
        $dbCnt['nbr_neg1']++;
        $x = -1;
      } else {
        $dbCnt['nbr_neg2']++;
        $x = -2;
      }
      $val = self::newTile($id, $plId, $x, null, 0, ++$dbCnt['max_aside']);
      $val['component_y'] = 'Null'; // have to force y to Null
      $values[] = $val;
    }

    $sql = self::updateTilesSql($values);
    $game::DbQuery($sql);
  }

  function getActiveComponent($game, $component_id)
  {
    return $game->getObjectFromDB("
            SELECT component_id, component_player, component_x, component_y,
            component_orientation
            FROM component WHERE component_id = $component_id and component_x > 0");
  }

  function newTile($id, $pl = null, $x = null, $y = null, $o = null, $aside = null)
  {
    $tile = ['component_id' => $id];
    if (!is_null($pl)) {
      $tile['component_player'] = $pl;
    }
    if (!is_null($x)) {
      $tile['component_x'] = $x;
    }
    if (!is_null($y)) {
      $tile['component_y'] = $y;
    }
    if (!is_null($o)) {
      $tile['component_orientation'] = $o;
    }
    if (!is_null($aside)) {
      $tile['aside_discard'] = $aside;
    }
    return $tile;
  }

  function updateTilesSql($tiles)
  {
    // Sql for update database rows to match input $tiles
    // component_id required
    // assume that all records have the same keys, in the same order
    $keys = array_keys($tiles[0]);
    if (!array_key_exists('component_id', $tiles[0])) {
      throw new InvalidArgumentException('Missing component_id in ' . var_export($tiles[0]));
    }

    $field_list = implode(',', $keys);
    $sql = "INSERT INTO component ($field_list) VALUES ";

    $values = [];
    foreach ($tiles as $tile) {
      $values[] = '(' . implode(',', array_values($tile)) . ')';
    }
    $sql .= implode(',', $values);

    $updates = [];
    foreach ($keys as $field) {
      $updates[] = "$field=VALUES($field)";
    }
    $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $updates);

    return $sql;
  }
}

function test()
{
  $tiles = [
    GT_DBComponent::newTile(1, 111, 0, 0),
    GT_DBComponent::newTile(2, 222, 1, 1),
    GT_DBComponent::newTile(3, 333),
    GT_DBComponent::newTile(4),
    GT_DBComponent::newTile(5, 555, 2, 3, 90),
  ];
  $sql = GT_DBComponent::updateTilesSql($tiles);
  var_export($sql);
}

?>
