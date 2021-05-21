<?php
/* Collection of utilities to interface with content table */

require_once 'GT_Constants.php';

class GT_DBContent
{
  public function __construct()
  {
  }

  function removeContentByIds($game, $contentIds)
  {
    $sql = 'DELETE FROM content WHERE content_id IN (' . implode(',', $contentIds) . ')';
    $game->DbQuery($sql);
  }

  function removeContentByTileIds($game, $tileIds)
  {
    $sql = 'DELETE FROM content WHERE tile_id IN (' . implode(',', $tileIds) . ')';
    $game->DbQuery($sql);
  }

  function insertContentSql($rows, $update = true)
  {
    // Sql for update database rows to match input $content
    // component_id required
    // assume that all records have the same keys, in the same order
    $fields = array_keys($rows[0]);

    $field_list = implode(',', $fields);
    $sql = "INSERT INTO content ($field_list) VALUES ";

    $values = [];
    foreach ($rows as $row) {
      // quote varchar fields
      if (array_key_exists('content_type', $row)) {
        $row['content_type'] = "'" . $row['content_type'] . "'";
      }
      if (array_key_exists('content_subtype', $row)) {
        $row['content_subtype'] = "'" . $row['content_subtype'] . "'";
      }

      $values[] = '(' . implode(',', array_values($row)) . ')';
    }
    $sql .= implode(',', $values);

    if ($update) {
      $updates = [];
      foreach ($fields as $field) {
        $updates[] = "$field=VALUES($field)";
      }

      $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $updates);
    }

    return $sql;
  }

  function contentValueSql($game, $plId, $tileId, $x, $y, $type, $subtype, $place, $capacity)
  {
    $new_val = '(' . implode(',', [$plId, $tileId, $x, $y, "'$type'", "'$subtype'", $place, $capacity]) . ')';

    if (!array_key_exists($type, GT_Constants::$ALLOWABLE_SUBTYPES)) {
      $game->throw_bug_report("Invalid type for content $new_val");
    }

    if (!in_array($subtype, GT_Constants::$ALLOWABLE_SUBTYPES[$type])) {
      $game->throw_bug_report("Invalid subtype for content $new_val");
    }

    return $new_val;
  }
}

?>
