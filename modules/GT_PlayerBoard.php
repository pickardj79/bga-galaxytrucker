<?php

class GT_PlayerBoard extends APP_GameClass {

    public function __construct($game, $plTiles, $player_id) {
        $this->game = $game;

        // tiles on the player board
        $this->plTiles = $plTiles;
        $this->player_id = $player_id;
    }

    function log( $msg ) {
        self::trace("##### $msg ##### ::GT_PlayerBoard::");
    }

    function dump_var($msg, $var) {
        self::trace("##### $msg ##### ::GT_PlayerBoard::" . var_export($var, TRUE));
    }

    // ###################################################################
    // ############ TILE-LEVEL HELPER FUNCTIONS ############    
    function removeTiles($tiles) {
        // Remove an array of tiles from plTiles
        foreach ( $tiles as $tile ) {
            unset ( $this->plTiles[ $tile['x'] ][ $tile['y'] ] );
            // foreach ( $plTiles_x as $tile ) {
            // }
        }
    }


    function getAdjacentTile( $tile, $side ) {
        $x = (int)$tile['x'];
        $y = (int)$tile['y'];
        switch ( $side ) {
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
        if ( isset ($this->plTiles[$x][$y]) ) {
            $ret = $this->plTiles[$x][$y];
        }
        else {
            $ret = false;
        }
        return $ret;
    }

    // used only during ship building, not when checking ship at the end of building or when a component is destroyed
    function checkIfTileConnected ( $tileToCheck ) {
        for ( $side=0 ; $side<=270 ; $side+=90 ) {
            $tileConn = $this->tileConnectionOnThisSide( $tileToCheck, $side );
            if ( $tileConn !== 0 && $tileConn !== -1 )
                return true;
        }
        return false;
    }

    function tileConnectionOnThisSide ( $tileToCheck, $side, $adjTile=null ) {
        // Is there an adjacent tile on this side ?
        if ( $adjTile // in this case $adjTile has been passed by checkTile() so no need to get it
              // Otherwise we try to get it and if it exists, execute the block
                || $adjTile = $this->getAdjacentTile ($tileToCheck, $side) ) {
            // There is one, so let's check how plTiles are connected
            $conn1 = $this->game->getConnectorType( $tileToCheck, $side );
            $conn2 = $this->game->getConnectorType( $adjTile, ($side+180)%360 );
            if ( $conn1 === $conn2 ) {
                if ( $conn1 === 0 )
                    { return 0; } // Both are smooth sides, so not connected but no error
                else
                    { return 2; } // Identical connectors, so connected and no error AND no
                              // problem with Defective Connectors (Rough Road card)
            }
            elseif ( $conn1 == 0 || $conn2 == 0 )
                { return -1; } // smooth side vs connector: error, but might or
                            // might not be prevented during building, we'll see
            elseif ( $conn1 === 3 || $conn2 === 3 )
                { return 1; }// Correctly connected, but different connectors (might be needed)
            else
                { return -2; } // simple vs double connector: error
        }
        return 0; // No adjacent tile on this side
    }

    // ###################################################################
    // ############ FUNCTIONS TO CHECK HOW SHIP FITS TOGETHER ############
    function badEngines() {
        // identify engines that are pointed the wrong way
        $engToRemove = array();

        foreach ( $this->plTiles as $plTiles_x ) {
            foreach ( $plTiles_x as $tile ) {
                if ( $this->game->tiles[ $tile['id'] ][ 'type' ] == 'engine' && $tile['o'] != '0' )
                    $engToRemove[] = $tile;
            }
        }
        return $engToRemove;
    }


    function checkShipIntegrity( ) {
        // We scan each tile in this ship to see if it is connected to adjacent plTiles
        // (only right and bottom, because connections to the top and left have already
        // been checked). If they're connected, we gather them tile by tile into ship
        // parts, that are merged when we see that they are connected. Eventually, we'll
        // see if all plTiles are in a single ship part, or in different parts, that will be
        // sent (if more than one is valid) to the unfortunate player who will have to
        // choose which part to keep
        $shipParts = array(); // key=>ship part id beginning with 1, because we'll use
                // it in UI, value=> array of plTiles in this part (whole plTiles indexed
                // with ids, not only ids, because we'll use x and y in stRepairShips
                // to remove plTiles $this->plTiles if needed)
        $tilesPart = array(); // key=>tile id, value=> ship part this tile is belonging to
        foreach ( $this->plTiles as $plBoard_x ) {
            foreach ( $plBoard_x as $tile ) {
                // 1. Check if this tile is already in a ship part. It's the case if it was
                // attached to a ship part in a previous tile scan because it was found
                // (see 2. below) to be connected to this previous tile.
                if ( !isset( $tilesPart[ $tile['id'] ] ) ) {
                    // This tile's not in a ship part yet, so we create a new one, and include
                    // the tile (by updating $shipParts and $tilesPart).
                    if ( $shipParts ) {
                        $partNumber = max(array_keys($shipParts))+1;
                    }
                    else {
                        $partNumber = 0;
                    }
                    // self::trace( "###### checkShipIntegrity: creating ship part $partNumber for tile ".
                    //        $tile['id'] ); // temp test
                    $shipParts[$partNumber] = array(); // new ship part array to fill
                    $tilesPart[ $tile['id'] ] = $partNumber;
                    $shipParts[$partNumber][$tile['id']] = $tile;
                }
                $thisPart = $tilesPart[ $tile['id'] ];
                self::log( "checkShipIntegrity: tile {$tile['id']} is in ship part $thisPart" );

                // 2. Check adjacent plTiles (right and bottom) to see if we must include them in
                // this ship part, or merge the ship part they belong to to this ship part
                foreach ( array(90,180) as $side ) {
                    $adjTile = $this->getAdjacentTile( $tile, $side );
                    if ( $adjTile &&
                            $this->tileConnectionOnThisSide( $tile, $side, $adjTile ) > 0 )
                    {
                        // The tile on this side is connected, so we check if it's already
                        // included in a ship part
                        if ( isset( $tilesPart[ $adjTile['id'] ] ) ) {
                            //self::trace( "###### checkShipIntegrity: tile on side $side ".$adjTile['id'].
                            //    " already in ship part ".$tilesPart[ $adjTile['id'] ] ); // temp test
                            $adjPart = $tilesPart[ $adjTile['id'] ];
                            if ( $adjPart != $thisPart ) {
                                // We merge the two ship parts into a single one. We don't use
                                // array_merge() because we have to run through $shipParts[$adjPart]
                                // anyway, to modify corresponding $tilesPart values (ship part id)
                                // self::trace( "###### checkShipIntegrity: merging part $adjPart ".
                                //  "into part $thisPart " ); // temp test
                                foreach ( $shipParts[$adjPart] as $ttmId => $tileToMerge ) {
                                    $tilesPart[$ttmId] = $thisPart;
                                    $shipParts[$thisPart][$ttmId] = $tileToMerge;
                                }
                                // Then we destroy the ship part that has been merged
                                unset( $shipParts[$adjPart] );
                            }
                        }
                        else {
                            // include it now in the ship part
                            $tilesPart[ $adjTile['id'] ] = $thisPart;
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

    function checkTiles() {
        $all_errors = array();
        foreach ( $this->plTiles as $plBoard_x ) {
            foreach ( $plBoard_x as $tile ) {
                $tileErrors = $this->checkTile($tile);
            }
        }
        return $all_errors; 
    }


    // checkTile is used when checking ships at the end of building
    function checkTile($tileToCheck) {
        $errors = array();
        $tileId = $tileToCheck['id'];
        $tileToCheckType = $this->game->tiles[ $tileId ][ 'type' ];

        // For two sides (9O=right, 180=bottom) of this tile, we want to check if rules are
        // respected (cannon, engine and connectors restrictions).
        // Top and left sides have already been checked when checking top and left adjacent plTiles
        foreach ( array(90,180) as $side ) {
        // Is there an adjacent tile on this side ?
            if ( $adjTile = $this->getAdjacentTile ($tileToCheck, $side) ) {
                // There is one, so let's check a few things
                $adjTileType = $this->game->tiles[ $adjTile['id'] ][ 'type' ];
                // check engine placement restrictions
                // Note: not enough for Somersault Rough Road card
                if ( $side == 180 && $tileToCheckType == 'engine' ) {
                    // Wrong tile placement: no component can sit on the square behind an engine
                    //$errors[] = 'engine_adjtile_180';
                    $errors[] = array( 'tileId' => $tileId, 'side' => '180',
                        'errType' => 'engine', 'plId' => $this->player_id,
                        'x' => $tileToCheck['x'], 'y' => $tileToCheck['y'] );
                }
                // If this tile is a cannon that points to an adjacent tile, or if adjacent tile
                // is a cannon that points to this tile, it's a rule error so we record it
                if ( ($tileToCheckType == 'cannon' && $tileToCheck['o'] == $side) 
                        || ($adjTileType == 'cannon' && $side == ($adjTile['o']+180)%360) ) {
                    //$errors[] = 'cannon_adjtile_'.$side;
                    $errors[] = array( 'tileId' => $tileId, 'side' => $side,
                        'errType' => 'cannon', 'plId' => $this->player_id,
                        'x' => $tileToCheck['x'], 'y' => $tileToCheck['y'] );
                }
                // Connectors restrictions
                $ret = $this->tileConnectionOnThisSide( $tileToCheck, $side, $adjTile );
                if ( $ret < 0 ) {
                    //$errors[] = 'connector_adjtile_'.$side;
                    $errors[] = array( 'tileId' => $tileId, 'side' => $side,
                        'errType' => 'connection', 'plId' => $this->player_id,
                        'x' => $tileToCheck['x'], 'y' => $tileToCheck['y'] );
                }
                // Expansions: if implementing Rough Roads, if $ret==1 $defConnMalus++
                // here? (to store in player table)
            }
        }
        return $errors;
    }


    // ###################################################################
    // ############ FUNCTIONS TO SUMMARIZE SHIP ############
}
