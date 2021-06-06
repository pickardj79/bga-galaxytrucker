<?php

class GT_PlayerBoard extends APP_GameClass
{
  public function __construct($game, $plTiles, $player_id)
  {
    $this->game = $game;

    // tiles on the player board
    $this->plTiles = $plTiles;
    $this->player_id = $player_id;
  }

  function log($msg)
  {
    self::trace("##### $msg ##### ::GT_PlayerBoard::");
  }

  function dump_var($msg, $var)
  {
    self::trace("##### $msg ##### ::GT_PlayerBoard::" . var_export($var, true));
  }

  // ###################################################################
  // ############ TILE-LEVEL HELPER FUNCTIONS ############
  function removeTiles($tiles)
  {
    // Remove an array of tiles from plTiles
    foreach ($tiles as $tile) {
      unset($this->plTiles[$tile['x']][$tile['y']]);
    }
  }

  function removeTilesById($tileIds)
  {
    // Remove an array of tileIds from plTiles
    $idSet = array_flip($tileIds);

    $tiles = [];
    foreach ($this->plTiles as $plBoard_x) {
      foreach ($plBoard_x as $tile) {
        if (array_key_exists($tile['id'], $idSet)) {
          $tiles[] = $tile;
        }
      }
    }
    $this->removeTiles($tiles);
  }

  function getTileById($tileId)
  {
    foreach ($this->plTiles as $plBoard_x)
      foreach ($plBoard_x as $tile)
        if ($tile['id'] == $tileId) return $tile;
  }

  function getAdjacentTile($tile, $side)
  {
    $x = (int) $tile['x'];
    $y = (int) $tile['y'];
    switch ($side) {
      case '0':
        $y -= 1;
        break;
      case '90':
        $x += 1;
        break;
      case '180':
        $y += 1;
        break;
      case '270':
        $x -= 1;
        break;
    }
    if (isset($this->plTiles[$x][$y])) {
      $ret = $this->plTiles[$x][$y];
    } else {
      $ret = false;
    }
    return $ret;
  }

  function checkIfTileConnected($tileToCheck)
  {
    // used only during ship building (why?), not when checking ship at the end of building or when a component is destroyed
    for ($side = 0; $side <= 270; $side += 90) {
      $tileConn = $this->tileConnectionOnThisSide($tileToCheck, $side);
      if ($tileConn !== 0 && $tileConn !== -1) {
        return true;
      }
    }
    return false;
  }

  function tileConnectionOnThisSide($tileToCheck, $side, $adjTile = null)
  {
    // Is there an adjacent tile on this side ?
    if ($adjTile || ($adjTile = $this->getAdjacentTile($tileToCheck, $side))) {
      // There is one, so let's check how plTiles are connected
      $conn1 = $this->getConnectorType($tileToCheck, $side);
      $conn2 = $this->getConnectorType($adjTile, ($side + 180) % 360);
      if ($conn1 === $conn2) {
        if ($conn1 === 0) {
          return 0;
        }
        // Both are smooth sides, so not connected but no error
        else {
          return 2;
        } // Identical connectors, so connected and no error AND no
        // problem with Defective Connectors (Rough Road card)
      } elseif ($conn1 == 0 || $conn2 == 0) {
        return -1;
      }
      // smooth side vs connector: error, but might or
      // might not be prevented during building, we'll see
      elseif ($conn1 === 3 || $conn2 === 3) {
        return 1;
      }
      // Correctly connected, but different connectors (might be needed)
      else {
        return -2;
      } // simple vs double connector: error
    }
    return 0; // No adjacent tile on this side
  }

  function getConnectedTiles($tile)
  {
    // Return array of tileIds that have a valid connection to $tileId
    $connected = [];
    for ($side = 0; $side <= 270; $side += 90) {
      if ($adjTile = $this->getAdjacentTile($tile, $side)) {
        if ($this->tileConnectionOnThisSide($tile, $side, $adjTile)) {
          $connected[] = $adjTile;
        }
      }
    }
    return $connected;
  }

  function getLine($rowOrCol, $side)
  {
    // This function returns an array of the tiles on a column or a row of a ship (or an empty
    // array when no tile on this line), that can be used to check various things (exposed connectors,
    // cannons, ...) or to know which tile(s) to destroy.
    // This array is sorted (in the second switch block) so that reset($tilesOnLine) (or $tilesOnLine[0]
    // if we decide to use sort instead of asort) is the tile exposed to meteors / cannon fires

    $tilesOnLine = [];
    switch ($side) {
      case 0:
      case 180:
        if (isset($this->plTiles[$rowOrCol])) {
          $tilesOnLine = $this->plTiles[$rowOrCol];
        } // $tilesOnLine is indexed on y position
        break;

      case 90:
      case 270:
        // $tilesOnLine = array_column( $plBoard, $rowOrCol );
        // $tilesOnLine = array_column( $tilesOnLine, NULL, 'x' ); // this re-indexes
        // $tilesOnLine on x position
        // array_column is undefined on BGA, must be PHP < 5.5, so the code below
        // is used instead of the commented code above

        foreach ($this->plTiles as $x => $plBoard_x) {
          if (isset($plBoard_x[$rowOrCol])) {
            $tilesOnLine[$x] = $plBoard_x[$rowOrCol];
          }
        }
        break;
    }

    switch ($side) {
      case 0:
      case 270:
        asort($tilesOnLine);
        break;
      case 90:
      case 180:
        // we want $tilesOnLine array to be sorted from right to left (if $side==90) or from bottom
        // to up (if $side==180), so arsort is used
        arsort($tilesOnLine);
        break;
    }
    return $tilesOnLine;
  }

  function getConnectorType($tile, $side)
  {
    return $this->game->getConnectorType($tile, $side);
  }

  function getTileType($tileid)
  {
    return $this->game->getTileType($tileid);
  }

  function getTileHold($tileid)
  {
    return $this->game->getTileHold($tileid);
  }

  // ###################################################################
  // ############ FUNCTIONS TO CHECK HOW SHIP FITS TOGETHER ############
  function badEngines()
  {
    // identify engines that are pointed the wrong way
    $engToRemove = [];

    foreach ($this->plTiles as $plTiles_x) {
      foreach ($plTiles_x as $tile) {
        if ($this->game->tiles[$tile['id']]['type'] == 'engine' && $tile['o'] != '0') {
          $engToRemove[] = $tile;
        }
      }
    }
    return $engToRemove;
  }

  function checkShipIntegrity()
  {
    // We scan each tile in this ship to see if it is connected to adjacent plTiles
    // (only right and bottom, because connections to the top and left have already
    // been checked). If they're connected, we gather them tile by tile into ship
    // parts, that are merged when we see that they are connected. Eventually, we'll
    // see if all plTiles are in a single ship part, or in different parts, that will be
    // sent (if more than one is valid) to the unfortunate player who will have to
    // choose which part to keep
    $shipParts = []; // key=>ship part id beginning with 1, because we'll use
    // it in UI, value=> array of plTiles in this part (whole plTiles indexed
    // with ids, not only ids, because we'll use x and y in stRepairShips
    // to remove plTiles $this->plTiles if needed)
    $tilesPart = []; // key=>tile id, value=> ship part this tile is belonging to
    foreach ($this->plTiles as $plBoard_x) {
      foreach ($plBoard_x as $tile) {
        // 1. Check if this tile is already in a ship part. It's the case if it was
        // attached to a ship part in a previous tile scan because it was found
        // (see 2. below) to be connected to this previous tile.
        if (!isset($tilesPart[$tile['id']])) {
          // This tile's not in a ship part yet, so we create a new one, and include
          // the tile (by updating $shipParts and $tilesPart).
          if ($shipParts) {
            $partNumber = max(array_keys($shipParts)) + 1;
          } else {
            $partNumber = 0;
          }
          // self::trace( "###### checkShipIntegrity: creating ship part $partNumber for tile ".
          //        $tile['id'] ); // temp test
          $shipParts[$partNumber] = []; // new ship part array to fill
          $tilesPart[$tile['id']] = $partNumber;
          $shipParts[$partNumber][$tile['id']] = $tile;
        }
        $thisPart = $tilesPart[$tile['id']];
        self::log("checkShipIntegrity: tile {$tile['id']} is in ship part $thisPart");

        // 2. Check adjacent plTiles (right and bottom) to see if we must include them in
        // this ship part, or merge the ship part they belong to to this ship part
        foreach ([90, 180] as $side) {
          $adjTile = $this->getAdjacentTile($tile, $side);
          if ($adjTile && $this->tileConnectionOnThisSide($tile, $side, $adjTile) > 0) {
            // The tile on this side is connected, so we check if it's already
            // included in a ship part
            if (isset($tilesPart[$adjTile['id']])) {
              //self::trace( "###### checkShipIntegrity: tile on side $side ".$adjTile['id'].
              //    " already in ship part ".$tilesPart[ $adjTile['id'] ] ); // temp test
              $adjPart = $tilesPart[$adjTile['id']];
              if ($adjPart != $thisPart) {
                // We merge the two ship parts into a single one. We don't use
                // array_merge() because we have to run through $shipParts[$adjPart]
                // anyway, to modify corresponding $tilesPart values (ship part id)
                // self::trace( "###### checkShipIntegrity: merging part $adjPart ".
                //  "into part $thisPart " ); // temp test
                foreach ($shipParts[$adjPart] as $ttmId => $tileToMerge) {
                  $tilesPart[$ttmId] = $thisPart;
                  $shipParts[$thisPart][$ttmId] = $tileToMerge;
                }
                // Then we destroy the ship part that has been merged
                unset($shipParts[$adjPart]);
              }
            } else {
              // include it now in the ship part
              $tilesPart[$adjTile['id']] = $thisPart;
              $shipParts[$thisPart][$adjTile['id']] = $adjTile;
              //self::trace( "###### checkShipIntegrity: including adjacent tile ".
              //    $adjTile['id']." to ship part ".$thisPart." " ); // temp test
            }
          }
        }
      }
    }
    return $shipParts;
  }

  function removeInvalidParts($shipParts, $player_name)
  {
    // Intended for use after checkShipIntegrity
    // Does automatic removal of components when it can
    // Returns index of ship parts to keep, indexing into $shipParts
    // If more than one $partsToKeep returned then the player must make a choice
    //  as to which part to keep

    $game = $this->game;

    if (count($shipParts) <= 1) {
      return $shipParts;
    }

    $partsToKeep = [];
    foreach ($shipParts as $partNumber => $part) {
      $hasCrew = false;
      foreach ($part as $tileId => $tile) {
        if ($game->getTileType($tileId) == 'crew') {
          $hasCrew = true;
        }
      }

      if ($hasCrew) {
        $partsToKeep[] = $part;
        continue;
      }

      // no cabin was found in this part, so it has to be removed from the ship
      $this->removeTiles(array_values($part));
      $compToRemove = array_keys($part);
      GT_DBContent::removeContentByTileIds($game, $compToRemove);
      GT_DBComponent::removeComponents($game, $this->player_id, $compToRemove);

      $numbComp = count($compToRemove);
      if ($numbComp == 1) {
        $notifyText = clienttranslate('${player_name} loses a component not ' . 'connected to the ship');
      } else {
        $notifyText = clienttranslate(
          '${player_name}\'s ship doesn\'t hold together.' .
            ' A part with ${numbComp} components (without cabin) is removed.'
        );
      }

      $game->notifyAllPlayers('loseComponent', $notifyText, [
        'plId' => $this->player_id,
        'player_name' => $player_name,
        'numbComp' => $numbComp,
        'tileIds' => $compToRemove,
      ]);
    }

    return $partsToKeep;
  }

  function checkTilesBuild()
  {
    $all_errors = [];
    foreach ($this->plTiles as $plBoard_x) {
      foreach ($plBoard_x as $tile) {
        $tileErrors = $this->checkTileBuild($tile);
        $all_errors = array_merge($all_errors, $tileErrors);
      }
    }
    return $all_errors;
  }

  // checkTileBuild is used when checking ships at the end of building
  // looks for bad connections and tiles in front of cannons or behind engines
  function checkTileBuild($tileToCheck)
  {
    $errors = [];
    $tileId = $tileToCheck['id'];
    $tileToCheckType = $this->getTileType($tileId);

    // For two sides (9O=right, 180=bottom) of this tile, we want to check if rules are
    // respected (cannon, engine and connectors restrictions).
    // Top and left sides have already been checked when checking top and left adjacent plTiles
    foreach ([90, 180] as $side) {
      // Is there an adjacent tile on this side ?
      if ($adjTile = $this->getAdjacentTile($tileToCheck, $side)) {
        // There is one, so let's check a few things
        $adjTileType = $this->getTileType($adjTile['id']);

        // check engine placement restrictions
        // Note: not enough for Somersault Rough Road card
        if ($side == 180 && $tileToCheckType == 'engine') {
          // Wrong tile placement: no component can sit on the square behind an engine
          //$errors[] = 'engine_adjtile_180';
          $errors[] = [
            'tileId' => $tileId,
            'side' => '180',
            'errType' => 'engine',
            'plId' => $this->player_id,
            'x' => $tileToCheck['x'],
            'y' => $tileToCheck['y'],
          ];
        }
        // If this tile is a cannon that points to an adjacent tile, or if adjacent tile
        // is a cannon that points to this tile, it's a rule error so we record it
        if (
          ($tileToCheckType == 'cannon' && $tileToCheck['o'] == $side) ||
          ($adjTileType == 'cannon' && $side == ($adjTile['o'] + 180) % 360)
        ) {
          $errors[] = [
            'tileId' => $tileId,
            'side' => $side,
            'errType' => 'cannon',
            'plId' => $this->player_id,
            'x' => $tileToCheck['x'],
            'y' => $tileToCheck['y'],
          ];
        }
        // Connectors restrictions
        $ret = $this->tileConnectionOnThisSide($tileToCheck, $side, $adjTile);
        if ($ret < 0) {
          //$errors[] = 'connector_adjtile_'.$side;
          $errors[] = [
            'tileId' => $tileId,
            'side' => $side,
            'errType' => 'connection',
            'plId' => $this->player_id,
            'x' => $tileToCheck['x'],
            'y' => $tileToCheck['y'],
          ];
        }
        // Expansions: if implementing Rough Roads, if $ret==1 $defConnMalus++
        // here? (to store in player table)
      }
    }
    return $errors;
  }

  // ###################################################################
  // ############ FUNCTIONS TO SUMMARIZE SHIP ############
  function getTilesOfType($type, $hold = null, $orientation = null)
  {
    $tiles = [];
    foreach ($this->plTiles as $plBoard_x) {
      foreach ($plBoard_x as $tile) {
        if ($this->getTileType($tile['id']) == $type) {
          if (is_null($hold) or $this->getTileHold($tile['id']) == $hold) {
            if (is_null($orientation) or $tile['o'] == $orientation) {
              $tiles[] = $tile;
            }
          }
        }
      }
    }
    return $tiles;
  }

  function countTileType($type, $hold = null, $orientation = null)
  {
    $cnt = 0;
    return count($this->getTilesOfType($type, $hold, $orientation));
  }

  function nbOfExposedConnectors()
  {
    // Est-ce qu'il faut améliorer cette fonction pour renvoyer les id et/ou coord des
    // tuiles avec le(s) côté(s) où il y a des connecteurs exposés ? (par
    // exemple pour que le client puisse les mettre en évidence)
    $nbExp = 0;
    foreach ($this->plTiles as $plBoard_x) {
      foreach ($plBoard_x as $tile) {
        // for each tile, we check if it has exposed connectors
        for ($side = 0; $side <= 270; $side += 90) {
          // Is there an adjacent tile on this side ?
          if (!$this->getAdjacentTile($tile, $side)) {
            // There isn't, so let's check if there's a connector on this side
            if ($this->getConnectorType($tile, $side) != 0) {
              $nbExp++;
            }
          }
        }
      }
    }
    return $nbExp;
  }

  function checkIfExposedConnector($rowOrCol, $side)
  {
    $tilesOnLine = $this->getLine($rowOrCol, $side);
    if (count($tilesOnLine) > 0 && $this->getConnectorType(reset($tilesOnLine), $side) != 0) {
      return true;
    }
    return false;
  }

  function countSingleEngines()
  {
    return $this->countTileType('engine', 1);
  }

  function countDoubleEngines()
  {
    return $this->countTileType('engine', 2);
  }

  function countSingleCannons()
  {
    return $this->countTileType('cannon', 1);
  }

  function countDoubleCannons()
  {
    return $this->countTileType('cannon', 2);
  }

  function getMinMaxStrengthX2($plyrContent, $type)
  {
    // $type can be 'cannon' or 'engine'
    // Strength is multiplied by 2 throughout the process, till it is compared to ennemy
    // or foe strength, to keep it an integer so that we avoid float imprecision
    // (useful only for cannons, but we'd better not use different
    $strengthX2 = 0;
    $nbActivableFor2 = 0;
    $nbActivableFor1 = 0; // for cannons not pointing to the front
    $minStrengthX2 = 0;
    $maxStrengthX2 = 0;
    $alien = false;
    if ($type == 'cannon') {
      $contentTypeColor = 'purple';
    } elseif ($type == 'engine') {
      $contentTypeColor = 'brown';
    } else {
      throw new BgaVisibleSystemException('GetMinMaxStrengthX2: type is ' . $type . ' ' . $this->plReportBug);
    }

    $tiles = $this->getTilesOfType($type);
    foreach ($tiles as $tile) {
      if ($this->getTileHold($tile['id']) == 1) {
        // simple engine or cannon
        if ($type == 'cannon' && $tile['o'] != 0) {
          $strengthX2 += 1;
        }
        // engines have to be oriented backwards (o = 180)
        else {
          $strengthX2 += 2;
        }
      } else {
        // double engine or cannon ('hold' should be 2, is it better to check
        // if it really is? Expansions: what about bi-directional cannons?)
        if ($type == 'cannon' && $tile['o'] != 0) {
          $nbActivableFor1 += 1;
        } else {
          $nbActivableFor2 += 1;
        }
      }
    }

    $minStrengthX2 = $maxStrengthX2 = $strengthX2;

    // check for number of cells left, to compute max strength
    // TODO only if needed
    $nbOfCells = $plyrContent->checkIfCellLeft();
    while ($nbActivableFor2 != 0 && $nbOfCells != 0) {
      $nbActivableFor2 -= 1;
      $nbOfCells -= 1;
      $maxStrengthX2 += 4;
    }
    while ($nbActivableFor1 != 0 && $nbOfCells != 0) {
      $nbActivableFor1 -= 1;
      $nbOfCells -= 1;
      $maxStrengthX2 += 2;
    }

    // truckers don't get alien bonus if their cannon / engine strength without alien is 0
    // if max strength is 0, no engine or cannon at all so don't bother looking for an alien
    if ($plyrContent->checkIfAlien($contentTypeColor)) {
      if ($maxStrengthX2 > 0) {
        $maxStrengthX2 += 4;
      }
      if ($minStrengthX2 > 0) {
        $minStrengthX2 += 4;
      }
    }
    return ['min' => $minStrengthX2, 'max' => $maxStrengthX2];
  }

  function checkIfPowerableShield($plyrContent, $sideToProtect)
  {
    if ($plyrContent->checkIfCellLeft() <= 0) {
      return false;
    }

    foreach ($this->plTiles as $plBoard_x) {
      foreach ($plBoard_x as $tile) {
        // for each tile, we check if it is a shield that protects the side that was hit
        if (
          $this->getTileType($tile['id']) == 'shield' &&
          ($tile['o'] == $sideToProtect || $tile['o'] == ($sideToProtect + 360 - 90) % 360)
        ) {
          return true;
        }
      }
    }
    return false;
  }

  function checkSingleCannonOnLine($rowOrCol, $side, $tilesOnLine = null)
  {
    if (is_null($tilesOnLine)) {
      $tilesOnLine = $this->getLine($rowOrCol, $side);
    }

    foreach ($tilesOnLine as $tile) {
      // Is this a cannon pointing in the correct direction ?
      if ($this->getTileType($tile['id']) == 'cannon' && $tile['o'] == $side && $this->getTileHold($tile['id']) == 1) {
        return true;
      }
    }
    return false;
  }

  function checkDoubleCannonOnLine($rowOrCol, $side, $plyrContent, $tilesOnLine = null)
  {
    if (is_null($tilesOnLine)) {
      $tilesOnLine = $this->getLine($rowOrCol, $side);
    }

    if (!$plyrContent->checkIfCellLeft()) {
      return false;
    }

    foreach ($tilesOnLine as $tile) {
      // Is this a cannon pointing in the correct direction ?
      if ($this->getTileType($tile['id']) == 'cannon' && $tile['o'] == $side && $this->getTileHold($tile['id']) == 2) {
        return true;
      }
    }
    return false;
  }
}
