<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * GalaxyTrucker implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * material.inc.php
 *
 * GalaxyTrucker game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */


/*

Example:

$this->card_types = array(
    1 => array( "card_name" => ...,
                ...
              )
);

*/
$this->plReportBug = "This shouldn't happen. please report this bug with the full error message.";

$this->translated = array (
  'purple' => clienttranslate( 'purple' ),
  'brown' => clienttranslate( 'brown' ),
);

// Maybe we won't need names
$this->flightVariant = array (
  1 => array ( 'name' => 'standard',
               'nbOfFlights' => 3,
               1 => array ( 'round' => 1, 'shipClass' => 'I' ),
               2 => array ( 'round' => 2, 'shipClass' => 'II' ),
               3 => array ( 'round' => 3, 'shipClass' => 'III' ) ),
  2 => array ( 'name' => 'short initiation',
               'nbOfFlights' => 2,
               1 => array ( 'round' => 1, 'shipClass' => 'I' ),
               2 => array ( 'round' => 2, 'shipClass' => 'II' ) ),
  3 => array ( 'name' => 'with IIIa',
               'nbOfFlights' => 3,
               1 => array ( 'round' => 1, 'shipClass' => 'I' ),
               2 => array ( 'round' => 2, 'shipClass' => 'II' ),
               3 => array ( 'round' => 3, 'shipClass' => 'IIIa' ) ),
  4 => array ( 'name' => 'short',
               'nbOfFlights' => 2,
               1 => array ( 'round' => 2, 'shipClass' => 'II' ),
               2 => array ( 'round' => 3, 'shipClass' => 'III' ) ),
  5 => array ( 'name' => 'short with IIIa',
               'nbOfFlights' => 2,
               1 => array ( 'round' => 2, 'shipClass' => 'II' ),
               2 => array ( 'round' => 3, 'shipClass' => 'IIIa' ) ),
);

$this->orient = array( 0 => 'n', 90 => 'e', 180 => 's', 270 => 'w');
$this->start_tiles = array( 31 => "0000ff", 32 => "008000", 33 => "ffff00", 34 => "ff0000" );

// Map tile types to the types of content they can hold
$this->tileHoldTypes = array(
    'battery' => 'cell',
    'cargo' => 'goods',
    'hazard' => 'goods',
    'crew' => 'crew',
    'brown' => 'crew',
    'purple' => 'crew'
);

// For tiles without an explict hold, define how much they hold
$this->tileHoldCnt = array(
    'crew' => 2,
    'brown' => 1,
    'purple' => 1,
    'structure' => 0,
    'shield' => 0,
);

$this->tiles = array();
for( $i=1 ; $i<=144 ; $i++)
  {
    $this->tiles[$i] = array('id' => $i);
    /* $this->tiles[$i]['e'] = 0; */
    /* $this->tiles[$i]['w'] = 0; */
    /* $this->tiles[$i]['s'] = 0; */
    /* $this->tiles[$i]['n'] = 0; */
  }


for( $i=1 ; $i<=9 ; $i++)
  {
    $this->tiles[$i]['type'] = 'battery';
    $this->tiles[$i]['hold'] = 2;
  }
for( $i=10 ; $i<=15 ; $i++)
  {
    $this->tiles[$i]['type'] = 'battery';
    $this->tiles[$i]['hold'] = 3;
  }
for( $i=16 ; $i<=24 ; $i++)
  {
    $this->tiles[$i]['type'] = 'cargo';
    $this->tiles[$i]['hold'] = 2;
  }
for( $i=25 ; $i<=30 ; $i++)
  {
    $this->tiles[$i]['type'] = 'cargo';
    $this->tiles[$i]['hold'] = 3;
  }
for( $i=31 ; $i<=48 ; $i++)
    $this->tiles[$i]['type'] = 'crew';

for( $i=49 ; $i<=54 ; $i++)
    $this->tiles[$i]['type'] = 'structure';

$this->tiles[55]['type'] = 'crew';

for( $i=56 ; $i<=60 ; $i++)
  {
    $this->tiles[$i]['type'] = 'hazard';
    $this->tiles[$i]['hold'] = 1;
  }
for( $i=61 ; $i<=63 ; $i++)
  {
    $this->tiles[$i]['type'] = 'hazard';
    $this->tiles[$i]['hold'] = 2;
  }

for( $i=64 ; $i<=80 ; $i++)
  {
    $this->tiles[$i]['type'] = 'engine';
    $this->tiles[$i]['hold'] = 1;
  }
for( $i=81 ; $i<=87 ; $i++)
  {
    $this->tiles[$i]['type'] = 'engine';
    $this->tiles[$i]['hold'] = 2;
  }

for( $i=88 ; $i<=108 ; $i++)
  {
    $this->tiles[$i]['type'] = 'cannon';
    $this->tiles[$i]['hold'] = 1;
  }

$this->tiles[109]['type'] = 'crew';
$this->tiles[110]['type'] = 'structure';
$this->tiles[111]['type'] = 'structure';
$this->tiles[112]['type'] = 'hazard';
$this->tiles[112]['hold'] = 1;
$this->tiles[113]['type'] = 'engine';
$this->tiles[113]['hold'] = 1;
$this->tiles[114]['type'] = 'purple';

for( $i=115 ; $i<=117 ; $i++)
    $this->tiles[$i]['type'] = 'shield';

$this->tiles[118]['type'] = 'engine';
$this->tiles[118]['hold'] = 1;
$this->tiles[119]['type'] = 'brown';
$this->tiles[120]['type'] = 'shield';

for( $i=121 ; $i<=122 ; $i++)
  {
    $this->tiles[$i]['type'] = 'cannon';
    $this->tiles[$i]['hold'] = 1;
  }
for( $i=123 ; $i<=130 ; $i++)
  {
    $this->tiles[$i]['type'] = 'cannon';
    $this->tiles[$i]['hold'] = 2;
  }

for( $i=131 ; $i<=135 ; $i++)
    $this->tiles[$i]['type'] = 'brown';
for( $i=136 ; $i<=140 ; $i++)
    $this->tiles[$i]['type'] = 'purple';
for( $i=141 ; $i<=144 ; $i++)
    $this->tiles[$i]['type'] = 'shield';

foreach( $this->tiles as &$tile ) {
    if (!array_key_exists('hold', $tile))
        $tile['hold'] = $this->tileHoldCnt[$tile['type']];
}

// 0 for no connector, 1 for simple, 2 for double, 3 for universal
$tileConnectors = array (
  1 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  2 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  3 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 1 ),
  4 => array ( 'w' => 3, 'n' => 0, 'e' => 3, 's' => 0 ),
  5 => array ( 'w' => 3, 'n' => 1, 'e' => 1, 's' => 1 ),
  6 => array ( 'w' => 3, 'n' => 1, 'e' => 2, 's' => 0 ),
  7 => array ( 'w' => 3, 'n' => 2, 'e' => 0, 's' => 0 ),
  8 => array ( 'w' => 3, 'n' => 2, 'e' => 1, 's' => 0 ),
  9 => array ( 'w' => 3, 'n' => 2, 'e' => 2, 's' => 2 ),
  10 => array ( 'w' => 1, 'n' => 0, 'e' => 2, 's' => 0 ),
  11 => array ( 'w' => 1, 'n' => 1, 'e' => 0, 's' => 0 ),
  12 => array ( 'w' => 1, 'n' => 2, 'e' => 0, 's' => 0 ),
  13 => array ( 'w' => 2, 'n' => 0, 'e' => 0, 's' => 0 ),
  14 => array ( 'w' => 2, 'n' => 1, 'e' => 0, 's' => 0 ),
  15 => array ( 'w' => 2, 'n' => 2, 'e' => 1, 's' => 0 ),
  16 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  17 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  18 => array ( 'w' => 3, 'n' => 0, 'e' => 1, 's' => 0 ),
  19 => array ( 'w' => 3, 'n' => 0, 'e' => 1, 's' => 2 ),
  20 => array ( 'w' => 3, 'n' => 0, 'e' => 2, 's' => 0 ),
  21 => array ( 'w' => 3, 'n' => 0, 'e' => 2, 's' => 1 ),
  22 => array ( 'w' => 3, 'n' => 1, 'e' => 2, 's' => 1 ),
  23 => array ( 'w' => 3, 'n' => 2, 'e' => 1, 's' => 2 ),
  24 => array ( 'w' => 3, 'n' => 3, 'e' => 0, 's' => 0 ),
  25 => array ( 'w' => 1, 'n' => 0, 'e' => 0, 's' => 0 ),
  26 => array ( 'w' => 1, 'n' => 0, 'e' => 1, 's' => 0 ),
  27 => array ( 'w' => 2, 'n' => 0, 'e' => 0, 's' => 0 ),
  28 => array ( 'w' => 2, 'n' => 0, 'e' => 1, 's' => 0 ),
  29 => array ( 'w' => 2, 'n' => 0, 'e' => 1, 's' => 0 ),
  30 => array ( 'w' => 2, 'n' => 0, 'e' => 2, 's' => 0 ),
  31 => array ( 'w' => 3, 'n' => 3, 'e' => 3, 's' => 3 ),
  32 => array ( 'w' => 3, 'n' => 3, 'e' => 3, 's' => 3 ),
  33 => array ( 'w' => 3, 'n' => 3, 'e' => 3, 's' => 3 ),
  34 => array ( 'w' => 3, 'n' => 3, 'e' => 3, 's' => 3 ),
  35 => array ( 'w' => 1, 'n' => 2, 'e' => 1, 's' => 2 ),
  36 => array ( 'w' => 1, 'n' => 2, 'e' => 1, 's' => 2 ),
  37 => array ( 'w' => 2, 'n' => 1, 'e' => 2, 's' => 0 ),
  38 => array ( 'w' => 2, 'n' => 2, 'e' => 1, 's' => 2 ),
  39 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 1 ),
  40 => array ( 'w' => 3, 'n' => 0, 'e' => 1, 's' => 0 ),
  41 => array ( 'w' => 3, 'n' => 0, 'e' => 1, 's' => 1 ),
  42 => array ( 'w' => 3, 'n' => 0, 'e' => 1, 's' => 2 ),
  43 => array ( 'w' => 3, 'n' => 1, 'e' => 0, 's' => 1 ),
  44 => array ( 'w' => 3, 'n' => 1, 'e' => 0, 's' => 2 ),
  45 => array ( 'w' => 3, 'n' => 2, 'e' => 0, 's' => 0 ),
  46 => array ( 'w' => 3, 'n' => 2, 'e' => 0, 's' => 2 ),
  47 => array ( 'w' => 3, 'n' => 2, 'e' => 2, 's' => 0 ),
  48 => array ( 'w' => 1, 'n' => 2, 'e' => 1, 's' => 0 ),
  49 => array ( 'w' => 3, 'n' => 1, 'e' => 3, 's' => 0 ),
  50 => array ( 'w' => 3, 'n' => 1, 'e' => 3, 's' => 1 ),
  51 => array ( 'w' => 3, 'n' => 1, 'e' => 3, 's' => 2 ),
  52 => array ( 'w' => 3, 'n' => 2, 'e' => 3, 's' => 0 ),
  53 => array ( 'w' => 3, 'n' => 3, 'e' => 1, 's' => 2 ),
  54 => array ( 'w' => 3, 'n' => 3, 'e' => 2, 's' => 0 ),
  55 => array ( 'w' => 1, 'n' => 1, 'e' => 2, 's' => 1 ),
  56 => array ( 'w' => 3, 'n' => 0, 'e' => 2, 's' => 1 ),
  57 => array ( 'w' => 3, 'n' => 0, 'e' => 3, 's' => 0 ),
  58 => array ( 'w' => 3, 'n' => 1, 'e' => 1, 's' => 1 ),
  59 => array ( 'w' => 3, 'n' => 2, 'e' => 1, 's' => 0 ),
  60 => array ( 'w' => 3, 'n' => 2, 'e' => 2, 's' => 2 ),
  61 => array ( 'w' => 1, 'n' => 0, 'e' => 0, 's' => 0 ),
  62 => array ( 'w' => 1, 'n' => 0, 'e' => 2, 's' => 0 ),
  63 => array ( 'w' => 2, 'n' => 0, 'e' => 0, 's' => 0 ),
  64 => array ( 'w' => 0, 'n' => 0, 'e' => 3, 's' => 0 ),
  65 => array ( 'w' => 0, 'n' => 0, 'e' => 3, 's' => 0 ),
  66 => array ( 'w' => 0, 'n' => 1, 'e' => 0, 's' => 0 ),
  67 => array ( 'w' => 0, 'n' => 1, 'e' => 1, 's' => 0 ),
  68 => array ( 'w' => 0, 'n' => 2, 'e' => 0, 's' => 0 ),
  69 => array ( 'w' => 0, 'n' => 2, 'e' => 0, 's' => 0 ),
  70 => array ( 'w' => 0, 'n' => 2, 'e' => 3, 's' => 0 ),
  71 => array ( 'w' => 0, 'n' => 3, 'e' => 2, 's' => 0 ),
  72 => array ( 'w' => 1, 'n' => 0, 'e' => 3, 's' => 0 ),
  73 => array ( 'w' => 1, 'n' => 3, 'e' => 0, 's' => 0 ),
  74 => array ( 'w' => 2, 'n' => 1, 'e' => 2, 's' => 0 ),
  75 => array ( 'w' => 2, 'n' => 2, 'e' => 0, 's' => 0 ),
  76 => array ( 'w' => 2, 'n' => 3, 'e' => 1, 's' => 0 ),
  77 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  78 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  79 => array ( 'w' => 3, 'n' => 0, 'e' => 2, 's' => 0 ),
  80 => array ( 'w' => 3, 'n' => 1, 'e' => 0, 's' => 0 ),
  81 => array ( 'w' => 0, 'n' => 1, 'e' => 0, 's' => 0 ),
  82 => array ( 'w' => 0, 'n' => 1, 'e' => 3, 's' => 0 ),
  83 => array ( 'w' => 0, 'n' => 2, 'e' => 0, 's' => 0 ),
  84 => array ( 'w' => 1, 'n' => 1, 'e' => 1, 's' => 0 ),
  85 => array ( 'w' => 2, 'n' => 2, 'e' => 2, 's' => 0 ),
  86 => array ( 'w' => 3, 'n' => 0, 'e' => 3, 's' => 0 ),
  87 => array ( 'w' => 3, 'n' => 2, 'e' => 0, 's' => 0 ),
  88 => array ( 'w' => 0, 'n' => 0, 'e' => 0, 's' => 1 ),
  89 => array ( 'w' => 0, 'n' => 0, 'e' => 0, 's' => 1 ),
  90 => array ( 'w' => 0, 'n' => 0, 'e' => 0, 's' => 2 ),
  91 => array ( 'w' => 0, 'n' => 0, 'e' => 0, 's' => 2 ),
  92 => array ( 'w' => 0, 'n' => 0, 'e' => 1, 's' => 2 ),
  93 => array ( 'w' => 0, 'n' => 0, 'e' => 2, 's' => 1 ),
  94 => array ( 'w' => 0, 'n' => 0, 'e' => 2, 's' => 3 ),
  95 => array ( 'w' => 0, 'n' => 0, 'e' => 3, 's' => 0 ),
  96 => array ( 'w' => 0, 'n' => 0, 'e' => 3, 's' => 0 ),
  97 => array ( 'w' => 0, 'n' => 0, 'e' => 3, 's' => 1 ),
  98 => array ( 'w' => 1, 'n' => 0, 'e' => 0, 's' => 2 ),
  99 => array ( 'w' => 1, 'n' => 0, 'e' => 0, 's' => 3 ),
  100 => array ( 'w' => 1, 'n' => 0, 'e' => 1, 's' => 1 ),
  101 => array ( 'w' => 1, 'n' => 0, 'e' => 2, 's' => 0 ),
  102 => array ( 'w' => 1, 'n' => 0, 'e' => 2, 's' => 3 ),
  103 => array ( 'w' => 2, 'n' => 0, 'e' => 0, 's' => 1 ),
  104 => array ( 'w' => 2, 'n' => 0, 'e' => 1, 's' => 3 ),
  105 => array ( 'w' => 2, 'n' => 0, 'e' => 2, 's' => 2 ),
  106 => array ( 'w' => 2, 'n' => 0, 'e' => 3, 's' => 0 ),
  107 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  108 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  109 => array ( 'w' => 3, 'n' => 0, 'e' => 2, 's' => 0 ),
  110 => array ( 'w' => 1, 'n' => 3, 'e' => 3, 's' => 0 ),
  111 => array ( 'w' => 3, 'n' => 3, 'e' => 2, 's' => 2 ),
  112 => array ( 'w' => 3, 'n' => 3, 'e' => 0, 's' => 0 ),
  113 => array ( 'w' => 1, 'n' => 2, 'e' => 1, 's' => 0 ),
  114 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 2 ),
  115 => array ( 'w' => 2, 'n' => 1, 'e' => 2, 's' => 1 ),
  116 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 1 ),
  117 => array ( 'w' => 3, 'n' => 0, 'e' => 2, 's' => 2 ),
  118 => array ( 'w' => 0, 'n' => 1, 'e' => 0, 's' => 0 ),
  119 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  120 => array ( 'w' => 2, 'n' => 0, 'e' => 2, 's' => 2 ),
  121 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 2 ),
  122 => array ( 'w' => 3, 'n' => 0, 'e' => 1, 's' => 0 ),
  123 => array ( 'w' => 0, 'n' => 0, 'e' => 0, 's' => 1 ),
  124 => array ( 'w' => 0, 'n' => 0, 'e' => 0, 's' => 2 ),
  125 => array ( 'w' => 0, 'n' => 0, 'e' => 1, 's' => 3 ),
  126 => array ( 'w' => 0, 'n' => 0, 'e' => 3, 's' => 2 ),
  127 => array ( 'w' => 1, 'n' => 0, 'e' => 1, 's' => 2 ),
  128 => array ( 'w' => 2, 'n' => 0, 'e' => 0, 's' => 3 ),
  129 => array ( 'w' => 2, 'n' => 0, 'e' => 2, 's' => 1 ),
  130 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 1 ),
  131 => array ( 'w' => 1, 'n' => 1, 'e' => 1, 's' => 0 ),
  132 => array ( 'w' => 1, 'n' => 2, 'e' => 1, 's' => 0 ),
  133 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 1 ),
  134 => array ( 'w' => 3, 'n' => 0, 'e' => 2, 's' => 0 ),
  135 => array ( 'w' => 3, 'n' => 1, 'e' => 0, 's' => 0 ),
  136 => array ( 'w' => 2, 'n' => 1, 'e' => 2, 's' => 0 ),
  137 => array ( 'w' => 2, 'n' => 2, 'e' => 2, 's' => 0 ),
  138 => array ( 'w' => 3, 'n' => 0, 'e' => 0, 's' => 0 ),
  139 => array ( 'w' => 3, 'n' => 0, 'e' => 1, 's' => 0 ),
  140 => array ( 'w' => 3, 'n' => 2, 'e' => 0, 's' => 0 ),
  141 => array ( 'w' => 1, 'n' => 0, 'e' => 1, 's' => 3 ),
  142 => array ( 'w' => 1, 'n' => 1, 'e' => 0, 's' => 1 ),
  143 => array ( 'w' => 1, 'n' => 2, 'e' => 1, 's' => 2 ),
  144 => array ( 'w' => 2, 'n' => 0, 'e' => 0, 's' => 3 )
  );
foreach ( $tileConnectors as $id => $tile )
  {
    foreach ( $tile as $dir => $conType )
        $this->tiles[$id][$dir] = $conType;
  }

  // combatzone must have laser attacks as 3rd element of 'lines'
$this->cardNames = array (
    'slavers' => clienttranslate('Slavers'),
    'smugglers' => clienttranslate('Smugglers'),
    'pirates' => clienttranslate('Pirates'),
    'stardust' => clienttranslate('Stardust'),
    'openspace' => clienttranslate('Open Space'),
    'meteoric' => clienttranslate('Meteoric Swarm'),
    'planets' => clienttranslate('Planets'),
    'combatzone' => clienttranslate('Combat Zone'),
    'abship' => clienttranslate('Abandoned Ship'),
    'abstation' => clienttranslate('Abandoned Station'),
    'epidemic' => clienttranslate('Epidemic'),
    'sabotage' => clienttranslate('Sabotage'),
    );

$this->card = array (
  0 => array ( 'round' => 1,
               'id' => 0,
               'type' => 'slavers',
               'enemy_strength' => 6,
               'enemy_penalty' => 3,
               'reward' => 5,
               'days_loss' => 1, ),
  1 => array ( 'round' => 1,
               'id' => 1,
               'type' => 'smugglers',
               'enemy_strength' => 4,
               'enemy_penalty' => 2,
               'reward' => array ( 'yellow' => 1,
                                   'green' => 1,
                                   'blue' => 1, ),
               'days_loss' => 1, ),
  2 => array ( 'round' => 1,
               'id' => 2,
               'type' => 'pirates',
               'enemy_strength' => 5,
               'enemy_penalty' => array ( 1 => 's0', // to be confirmed
                                          2 => 'b0', // to be confirmed
                                          3 => 's0', ), // to be confirmed
               'reward' => 4,
               'days_loss' => 1, ),
  3 => array ( 'round' => 1,
               'id' => 3,
               'type' => 'stardust', ),
  4 => array ( 'round' => 1,
               'id' => 4,
               'type' => 'openspace', ),
  5 => array ( 'round' => 1,
               'id' => 5,
               'type' => 'openspace', ),
  6 => array ( 'round' => 1,
               'id' => 6,
               'type' => 'openspace', ),
  7 => array ( 'round' => 1,
               'id' => 7,
               'type' => 'openspace', ),
  8 => array ( 'round' => 1,
               'id' => 8,
               'type' => 'meteoric',
               'meteors' => array ( 'b0', 's270', 's90' ) ),
  9 => array ( 'round' => 1,
               'id' => 9,
               'type' => 'meteoric',
               'meteors' => array ( 's0', 's180', 's270', 's90' ) ),
  10 => array ( 'round' => 1,
                'id' => 10,
                'type' => 'meteoric',
                'meteors' => array ( 1 => 'b0', // to be confirmed
                                    2 => 's0', // to be confirmed
                                    3 => 'b0', // to be confirmed
                                   ) ),
  11 => array ( 'round' => 1,
                'id' => 11,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('red', 'green', 'blue', 'blue', 'blue'),
                       2 => array ('red','yellow','blue'),
                       3 => array ('red','blue','blue','blue'),
                       4 => array ('red','green')
                       ),
                'days_loss' => 3, ),
  12 => array ( 'round' => 1,
                'id' => 12,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('red','red'),
                       2 => array ('red','blue','blue'),
                       3 => array ('yellow'), 
                       ),
                'days_loss' => 2, ),
  13 => array ( 'round' => 1,
                'id' => 13,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('yellow','green','blue','blue'),
                       2 => array ('yellow','yellow')
                       ),
                'days_loss' => 3, ),
  14 => array ( 'round' => 1,
                'id' => 14,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('green','green'),
                       2 => array ('yellow'),
                       3 => array ('blue','blue','blue')
                       ),
                'days_loss' => 2, ),
  15 => array ( 'round' => 1,
                'id' => 15,
                'type' => 'combatzone',
                'lines' => array ( 
                       1 => array ( 'criterion' => 'crew',
                                   'penalty_type' => 'days',
                                   'penalty_value' => 3 ),
                       2 => array ( 'criterion' => 'engines',
                                   'penalty_type' => 'crew',
                                   'penalty_value' => 2 ),
                       3 => array ( 'criterion' => 'cannons',
                                   'penalty_type' => 'shot',
                                   'penalty_value' => array (
                                        1 => 's180',
                                        2 => 'b180' ) ),
                      ) ),
  16 => array ( 'round' => 1,
                'id' => 16,
                'type' => 'abship',
                'crew' => 3,
                'reward' => 4,
                'days_loss' => 1, ),
  17 => array ( 'round' => 1,
                'id' => 17,
                'type' => 'abship',
                'crew' => 2,
                'reward' => 3,
                'days_loss' => 1, ),
  18 => array ( 'round' => 1,
                'id' => 18,
                'type' => 'abstation',
                'crew' => 5,
                'reward' => array ( 'yellow', 'green' ),
                'days_loss' => 1, ),
  19 => array ( 'round' => 1,
                'id' => 19,
                'type' => 'abstation',
                'crew' => 6,
                'reward' => array ( 'red', 'red' ),
                'days_loss' => 1, ),
  20 => array ( 'round' => 2,
                'id' => 20,
                'type' => 'slavers', ),
  21 => array ( 'round' => 2,
                'id' => 21,
                'type' => 'smugglers', ),
  22 => array ( 'round' => 2,
                'id' => 22,
                'type' => 'pirates', ),
  23 => array ( 'round' => 2,
                'id' => 23,
                'type' => 'stardust', ),
  24 => array ( 'round' => 2,
                'id' => 24,
                'type' => 'epidemic', ),
  25 => array ( 'round' => 2,
                'id' => 25,
                'type' => 'openspace', ),
  26 => array ( 'round' => 2,
                'id' => 26,
                'type' => 'openspace', ),
  27 => array ( 'round' => 2,
                'id' => 27,
                'type' => 'openspace', ),
  28 => array ( 'round' => 2,
                'id' => 28,
                'type' => 'meteoric', ),
  29 => array ( 'round' => 2,
                'id' => 29,
                'type' => 'meteoric', ),
  30 => array ( 'round' => 2,
                'id' => 30,
                'type' => 'meteoric', ),
  31 => array ( 'round' => 2,
                'id' => 31,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('red', 'red', 'red', 'yellow'),
                       2 => array ('red', 'red', 'green', 'green'),
                       3 => array ('red', 'blue', 'blue', 'blue','blue'),
                       ),
                'days_loss' => 4, ),
  32 => array ( 'round' => 2,
                'id' => 32,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('red', 'red'),
                       2 => array ('green', 'green', 'green', 'green'),
                       ),
                'days_loss' => 3, ),
  33 => array ( 'round' => 2,
                'id' => 33,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('red', 'yellow'),
                       2 => array ('yellow', 'green', 'blue'),
                       3 => array ('green', 'green'),
                       4 => array ('yellow'),
                       ),
                'days_loss' => 2, ),
  34 => array ( 'round' => 2,
                'id' => 34,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('green', 'green', 'green', 'green'),
                       2 => array ('yellow', 'yellow'),
                       3 => array ('blue', 'blue'),
                       ),
                'days_loss' => 3, ),
  35 => array ( 'round' => 2,
                'id' => 35,
                'type' => 'combatzone', ),
  36 => array ( 'round' => 2,
                'id' => 36,
                'type' => 'abship',
                'crew' => 5,
                'reward' => 8,
                'days_loss' => 2, ),
  37 => array ( 'round' => 2,
                'id' => 37,
                'type' => 'abship',
                'crew' => 4,
                'reward' => 6,
                'days_loss' => 1, ),
  38 => array ( 'round' => 2,
                'id' => 38,
                'type' => 'abstation',
                'crew' => 8,
                'reward' => array ( 'yellow', 'yellow', 'green' ),
                'days_loss' => 2, ),
  39 => array ( 'round' => 2,
                'id' => 39,
                'type' => 'abstation',
                'crew' => 7,
                'reward' => array ( 'red', 'yellow' ),
                'days_loss' => 1, ),
  40 => array ( 'round' => 3,
                'id' => 40,
                'type' => 'slavers', ),
  41 => array ( 'round' => 3,
                'id' => 41,
                'type' => 'smugglers', ),
  42 => array ( 'round' => 3,
                'id' => 42,
                'type' => 'pirates', ),
  43 => array ( 'round' => 3,
                'id' => 43,
                'type' => 'sabotage', ),
  44 => array ( 'round' => 3,
                'id' => 44,
                'type' => 'epidemic', ),
  45 => array ( 'round' => 3,
                'id' => 45,
                'type' => 'openspace', ),
  46 => array ( 'round' => 3,
                'id' => 46,
                'type' => 'openspace', ),
  47 => array ( 'round' => 3,
                'id' => 47,
                'type' => 'openspace', ),
  48 => array ( 'round' => 3,
                'id' => 48,
                'type' => 'meteoric', ),
  49 => array ( 'round' => 3,
                'id' => 49,
                'type' => 'meteoric', ),
  50 => array ( 'round' => 3,
                'id' => 50,
                'type' => 'meteoric', ),
  51 => array ( 'round' => 3,
                'id' => 51,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('yellow', 'yellow','yellow','yellow','yellow'),
                       2 => array ('red', 'yellow','yellow'),
                       3 => array ('red', 'red'),
                       ),
                'days_loss' => 5, ),
  52 => array ( 'round' => 3,
                 'id' => 52,
                 'type' => 'planets',
                'planets' => array ( 
                       1 => array ('green','blue', 'blue', 'blue','blue'),
                       2 => array ('yellow', 'blue'),
                       ),
                'days_loss' => 1, ),
  53 => array ( 'round' => 3,
                'id' => 53,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('red', 'yellow', 'blue'),
                       2 => array ('red', 'green','blue','blue'),
                       3 => array ('red', 'blue','blue','blue','blue'),
                       ),
                'days_loss' => 2, ),
  54 => array ( 'round' => 3,
                'id' => 54,
                'type' => 'planets',
                'planets' => array ( 
                       1 => array ('red', 'red', 'red'),
                       2 => array ('yellow', 'yellow', 'yellow'),
                       3 => array ('green', 'green', 'green'),
                       4 => array ('blue', 'blue','blue'),
                       ),
                'days_loss' => 3, ),
  55 => array ( 'round' => 3,
                'id' => 55,
                'type' => 'combatzone', ),
  56 => array ( 'round' => 3,
                'id' => 56,
                'type' => 'abship',
                'crew' => 7,
                'reward' => 11,
                'days_loss' => 2, ),
  57 => array ( 'round' => 3,
                'id' => 57,
                'type' => 'abship',
                'crew' => 6,
                'reward' => 10,
                'days_loss' => 2, ),
  58 => array ( 'round' => 3,
                'id' => 58,
                'type' => 'abstation',
                'crew' => 9,
                'reward' => array ( 'red', 'yellow', 'green', 'blue' ),
                'days_loss' => 2, ),
  59 => array ( 'round' => 3,
                'id' => 59,
                'type' => 'abstation',
                'crew' => 10,
                'reward' => array ( 'yellow', 'yellow', 'green', 'green'),
                'days_loss' => 2, ),
  );

