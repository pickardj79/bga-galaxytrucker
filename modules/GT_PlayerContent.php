<?php

class GT_PlayerContent extends APP_GameClass
{
  public function __construct($game, $plContent, $player_id)
  {
    $this->game = $game;

    $this->plContent = $plContent;
    $this->player_id = $player_id;
  }

  ################### HELPERS #########################

  ################### CHECK HELPERS #########################

  // check battery choices from front end
  function checkBattChoices($battChoices, $maxCnt)
  {
    if (count(array_unique($battChoices)) != count($battChoices)) {
      $this->game->throw_bug_report('Several batteries with the same id. ' . var_export($battChoices, true));
    }

    foreach ($battChoices as $battId) {
      $this->checkContentById($battId, 'cell');
    }

    if (count($battChoices) > $maxCnt) {
      $this->game->throw_bug_report('Error: too many batteries selected (more than double engines). ');
    }
  }

  // checkAll - validates all content against tiles from db (GT_PlayerBoard)
  function checkAll($brd)
  {
    $tilePlaces = [];
    foreach ($this->plContent as $cont) {
      $x = $cont['square_x'];
      $y = $cont['square_y'];
      if (!array_key_exists($x, $brd->plTiles) || !array_key_exists($y, $brd->plTiles[$x])) {
        $this->game->throw_bug_report_dump("No tile at content location ($x,$y)", $cont);
      }

      $tile = $brd->plTiles[$x][$y];
      $this->checkContentTile($cont, $tile['id']);

      // check tile_id/place is unique
      $tile_place = $cont['tile_id'] . '_' . $cont['place'];
      if (array_key_exists($tile_place, $tilePlaces)) {
        $this->game->throw_bug_report("Place used multiple times on tile (tile_place $tile_place)");
      } else {
        $tilePlaces[$tile_place] = 1;
      }
    }
  }

  // Check that content id $id is valid relative to expected type $type and this player's content
  function checkContentById($id, $type = null, $subtype = null)
  {
    if (!$type && $subtype) {
      $this->game->throw_bug_report("Cannot for check subtype ($subtype) and not type for id $id.");
    }

    if (!array_key_exists($id, $this->plContent)) {
      $this->game->throw_bug_report("Wrong id $id: no content with this id.");
    }

    if ($type) {
      $this->checkContent($this->plContent[$id], $type, $subtype);
    }
  }

  function checkContent($content, $type, $subtype = null)
  {
    if ($content['content_type'] != $type) {
      $this->game->throw_bug_report_dump("Wrong content: not a $type.", $content);
    }

    if ($subtype && $content['content_subtype'] != $subtype) {
      $this->game->throw_bug_report_dump("Wrong content: not subtype $subtype.", $content);
    }

    if (!in_array($content['content_subtype'], GT_Constants::$ALLOWABLE_SUBTYPES[$type])) {
      $this->game->throw_bug_report_dump(
        "Wrong content subtype: {$content['content_subtype']} not allowed with type $type.",
        $content
      );
    }

    if ($content['player_id'] != $this->player_id) {
      $this->game->throw_bug_report('Wrong content: not in your ship.', $content);
    }
  }

  // Check that content id $id is valid for tile $tileId
  function checkContentTileById($id, $tileId, $checkHold = true)
  {
    $this->checkContentById($id);
    $this->checkContentTile($this->plContent[$id], $tileId, $checkHold);
  }

  // Check that the given $content can go on tile with $tileId
  function checkContentTile($content, $tileId, $checkHold = true)
  {
    $tileType = $this->game->getTileType($tileId);
    $tileHold = $this->game->getTileHold($tileId);
    $tileHoldType = $this->game->tileHoldTypes[$tileType];
    $this->checkContent($content, $tileHoldType);

    if ($content['content_type'] == 'goods' && $content['content_subtype'] == 'red' && $tileType != 'hazard') {
      $this->game->throw_bug_report_dump("Red goods must go in hazards tiles (id $tileId)", $content);
    }

    if ($checkHold && $content['place'] > $tileHold) {
      $this->game->throw_bug_report_dump("Too many {$content['content_type']} on tile (id $tileId)", $content);
    }

    if ($content['capacity'] != $tileHold) {
      $this->game->throw_bug_report_dump("Content capacity does not match tile hold (id $tileId)", $content);
    }
  }

  function checkIfCellLeft()
  {
    $nbOfCells = 0;
    foreach ($this->plContent as $content) {
      if ($content['content_type'] == 'cell') {
        $nbOfCells++;
      }
    }
    return $nbOfCells;
  }

  function checkIfAlien($alColor)
  {
    if ($alColor != 'brown' and $alColor != 'purple') {
      $this->game->throw_bug_report("Invalid alien type $alColor");
    }

    foreach ($this->plContent as $content) {
      if ($content['content_subtype'] === $alColor) {
        return true;
      }
    } // No need to continue, there can't be more than 1 alien of each color
    return false;
  }

  function getContent($type, $subtype = null)
  {
    $conts = array_filter($this->plContent, function ($c) use ($type) {
      return $c['content_type'] == $type;
    });
    if ($subtype) {
      $conts = array_filter($conts, function ($c) use ($subtype) {
        return $c['content_subtype'] == $subtype;
      });
    }
    return $conts;
  }

  function getContentIds($type, $subtype = null)
  {
    $conts = $this->getContent($type, $subtype);
    return array_map(function ($x) {
      return $x['content_id'];
    }, $conts);
  }

  function getIds($conts)
  {
    return array_map(function ($x) {
      return $x['content_id'];
    }, $conts);
  }

  function nbOfCrewMembers()
  {
    return count($this->getContent('crew'));
  }

  function nextPlace($tileId)
  {
    // does not obey tile hold limits
    $place = 1;
    foreach ($this->plContent as $cont) {
      if ($cont['tile_id'] == $tileId && $cont['place'] == $place) {
        $place++;
      }
    }
    return $place;
  }

  ################### CONTENT MANIPULATION #########################

  function clearAllPlaces($type)
  {
    // clear all places in preparation for a full reload
    foreach ($this->plContent as &$cont) {
      if ($cont['content_type'] != $type) {
        continue;
      }
      $cont['place'] = null;
    }
  }

  function moveContent($tileId, $type, $goodsIds)
  {
    // Moves content ids $goodsIds to tile $tileId (both are numbers)
    $tile = GT_DBComponent::getActiveComponent($this->game, $tileId);

    if ($tile['component_player'] != $this->player_id) {
      $this->game->throw_bug_report_dump('Wrong player for tile', $tile);
    }

    $rows = [];
    foreach ($goodsIds as $id) {
      $this->checkContentTileById($id, $tileId, $checkHold = false);
      $capacity = $this->game->getTileHold($tileId);
      $place = $this->nextPlace($tileId);
      $rows[] = [
        'content_id' => $id,
        'tile_id' => $tileId,
        'square_x' => $tile['component_x'],
        'square_y' => $tile['component_y'],
        'place' => $place,
        'capacity' => $capacity,
      ];
      $this->plContent[$id]['tile_id'] = $tileId;
      $this->plContent[$id]['square_x'] = $tile['component_x'];
      $this->plContent[$id]['square_y'] = $tile['component_y'];
      $this->plContent[$id]['place'] = $place;
      $this->plContent[$id]['capacity'] = $capacity;
    }
    if ($rows) {
      $sql = GT_DBContent::insertContentSql($rows);
      $this->game->log("moving content with $sql");
      $this->game->DbQuery($sql);
    }
    return $rows;
  }

  function newContent($tileId, $type, $cnt, $subtypes)
  {
    // Creates newContent on $tileId (a number)
    // Specify either $cnt (e.g. for cells) or array of $subtypes (e.g. for cargo)
    $game = $this->game;
    if ($cnt) {
      $game->throw_bug_report('newContent by cnt not implemented');
    }

    if (!$subtypes) {
      return [];
    }

    $tile = GT_DBComponent::getActiveComponent($game, $tileId);

    $newContent = [];
    foreach ($subtypes as $subtype) {
      $place = $this->nextPlace($tileId);
      $content = [
        'player_id' => $this->player_id,
        'content_type' => $type,
        'content_subtype' => $subtype,
        'tile_id' => $tileId,
        'square_x' => $tile['component_x'],
        'square_y' => $tile['component_y'],
        'place' => $place,
        'capacity' => $game->getTileHold($tileId),
      ];
      $this->checkContentTile($content, $tileId, $checkHold = false);

      $sql = GT_DBContent::insertContentSql([$content], $update = false);
      $game->log("adding content with $sql");
      $game->DbQuery($sql);
      $id = $game->DbGetLastId();
      $content['content_id'] = $id;
      $this->plContent[$id] = $content;

      $newContent[] = $content;
    }

    return $newContent;
  }

  function newContentNotif($tileContent, $pName = null)
  {
    $this->game->dump_var('newContentNotif', $tileContent);
    // $tileContent is map of tileId => array($contents)

    $contentHtml = '';
    foreach ($tileContent as $tileId => $contents) {
      foreach ($contents as $cont) {
        // Could also look up in $this->plContent by id
        $type = $cont['content_type'];
        $subtype = $cont['content_subtype'];
        $contentHtml .= "<img class='content $type $subtype'></img> ";
      }
    }

    if (!$pName) {
      $player = GT_DBPlayer::getPlayer($this->game, $this->player_id);
      $pName = $player['player_name'];
    }

    $this->game->notifyAllPlayers('gainContent', clienttranslate('${player_name} gains ${content_icons}'), [
      'player_name' => $pName,
      'content_icons' => $contentHtml,
    ]);
  }

  function loseContent($ids, $expType, $expSubType = null, $toCard = false)
  {
    // $ids: array of ids (ints) to remove
    if (!$ids) {
      return;
    }

    $contentLost = [];
    $contentHtml = '';
    $tileOrient = $this->game->getCollectionFromDB(
      'SELECT component_id, component_orientation ' . "FROM component WHERE component_player={$this->player_id}",
      true
    );

    $this->game->dump_var('ids', $ids);
    foreach ($ids as $id) {
      $this->checkContentById($id, $expType, $expSubType);
      $curCont = $this->plContent[$id];
      $this->game->dump_var('curCont', $curCont);

      unset($this->plContent[$id]);
      $tileId = $curCont['tile_id'];
      $contentLost[] = ['orient' => $tileOrient[$tileId], 'id' => $id, 'tile_id' => $tileId, 'toCard' => $toCard];
      $type = $curCont['content_type'] . ' ' . $curCont['content_subtype'];
      $contentHtml .= "<img class='content $type'></img> ";
    }
    GT_DBContent::removeContentByIds($this->game, $ids);

    $player = GT_DBPlayer::getPlayer($this->game, $this->player_id);
    $this->game->notifyAllPlayers('loseContent', clienttranslate('${player_name} loses ${content_icons}'), [
      'player_name' => $player['player_name'],
      'player_id' => $this->player_id,
      'content' => $contentLost,
      'content_icons' => $contentHtml,
    ]);
    $this->game->updNotifPlInfosObj($this->player_id, null, $this);
  }
}
