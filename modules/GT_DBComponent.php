<?php
/* Collection of utilities to interface with component table */

class GT_DBComponent extends APP_GameClass {
    public function __construct() {
    }

    function getActiveComponent($game, $component_id) {
        return $game->getObjectFromDB ( "
            SELECT component_id, component_player, component_x, component_y,
            component_orientation
            FROM component WHERE component_id = $component_id and component_x > 0");
    }


    function newTile($id, $pl=Null, $x=Null, $y=Null, $o=Null) {
        $tile = array('component_id' => $id );
        if (!is_null($pl)) { $tile['component_player'] = $pl; }
        if (!is_null($x)) { $tile['component_x'] = $x; }
        if (!is_null($y)) { $tile['component_y'] = $y; }
        if (!is_null($o)) { $tile['component_o'] = $o; }
        return $tile;
    }

    function updateTilesSql($tiles) {
        // Sql for update database rows to match input $tiles
        // component_id required
        // assume that all records have the same keys, in the same order
        $keys = array_keys($tiles[0]);
        if (!array_key_exists('component_id', $tiles[0])) {
            throw new InvalidArgumentException("Missing component_id in " . var_export($tiles[0]));
        }

        $field_list = implode(',', $keys);
        $sql = "INSERT INTO component ($field_list) VALUES ";

        $values = array();
        foreach ($tiles as $tile) {
            $values[] = "(" . implode(',', array_values($tile)) . ")";
        }
        $sql .= implode(',',$values);

        $updates = array();
        foreach ($keys as $field) {
            $updates[] = "$field=VALUES($field)";
        }
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updates);

        return $sql;
    }
}

function test() {
    $tiles = array(
        GT_DBComponent::newTile(1,111,0,0),
        GT_DBComponent::newTile(2,222,1,1),
        GT_DBComponent::newTile(3,333),
        GT_DBComponent::newTile(4),
        GT_DBComponent::newTile(5,555,2,3,90),
    );
    $sql = GT_DBComponent::updateTilesSql($tiles);
    var_export($sql);
}

?>
