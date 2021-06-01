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
require_once 'modules/constants.inc.php';

$this->plReportBug = "This shouldn't happen. please report this bug with the full error message.";

$this->translated = [
  'purple' => clienttranslate('purple'),
  'brown' => clienttranslate('brown'),
];

// Maybe we won't need names
$this->flightVariant = [
  1 => [
    'name' => 'standard',
    'nbOfFlights' => 3,
    1 => ['round' => 1, 'shipClass' => 'I'],
    2 => ['round' => 2, 'shipClass' => 'II'],
    3 => ['round' => 3, 'shipClass' => 'III'],
  ],
  2 => [
    'name' => 'short initiation',
    'nbOfFlights' => 2,
    1 => ['round' => 1, 'shipClass' => 'I'],
    2 => ['round' => 2, 'shipClass' => 'II'],
  ],
  3 => [
    'name' => 'with IIIa',
    'nbOfFlights' => 3,
    1 => ['round' => 1, 'shipClass' => 'I'],
    2 => ['round' => 2, 'shipClass' => 'II'],
    3 => ['round' => 3, 'shipClass' => 'IIIa'],
  ],
  4 => [
    'name' => 'short',
    'nbOfFlights' => 2,
    1 => ['round' => 2, 'shipClass' => 'II'],
    2 => ['round' => 3, 'shipClass' => 'III'],
  ],
  5 => [
    'name' => 'short with IIIa',
    'nbOfFlights' => 2,
    1 => ['round' => 2, 'shipClass' => 'II'],
    2 => ['round' => 3, 'shipClass' => 'IIIa'],
  ],
];

$this->orient = [0 => 'n', 90 => 'e', 180 => 's', 270 => 'w'];
$this->start_tiles = [31 => '0000ff', 32 => '008000', 33 => 'ffff00', 34 => 'ff0000'];

// Map tile types to the types of content they can hold
$this->tileHoldTypes = [
  'battery' => 'cell',
  'cargo' => 'goods',
  'hazard' => 'goods',
  'crew' => 'crew',
  'brown' => 'crew',
  'purple' => 'crew',
];

// For tiles without an explict hold, define how much they hold
$this->tileHoldCnt = [
  'crew' => 2,
  'brown' => 1,
  'purple' => 1,
  'structure' => 0,
  'shield' => 0,
];

// human-readable tile types. To be used in sentence like: player loses $typeName tile
$this->tileNames = [
  'battery' => clienttranslate('Battery'),
  'cargo' => clienttranslate('Cargo Hold'),
  'structure' => clienttranslate('Structure'),
  'hazard' => clienttranslate('Special Cargo Hold'),
  'crew' => clienttranslate('Crew'),
  'engine' => clienttranslate('Engine'),
  'cannon' => clienttranslate('Cannon'),
  'brown' => clienttranslate('Brown Alien Life Support'),
  'purple' => clienttranslate('Purple Alien Life Support'),
  'shield' => clienttranslate('Shield'),
];

$this->tiles = [];
for ($i = 1; $i <= 144; $i++) {
  $this->tiles[$i] = ['id' => $i];
  /* $this->tiles[$i]['e'] = 0; */
  /* $this->tiles[$i]['w'] = 0; */
  /* $this->tiles[$i]['s'] = 0; */
  /* $this->tiles[$i]['n'] = 0; */
}

for ($i = 1; $i <= 9; $i++) {
  $this->tiles[$i]['type'] = 'battery';
  $this->tiles[$i]['hold'] = 2;
}
for ($i = 10; $i <= 15; $i++) {
  $this->tiles[$i]['type'] = 'battery';
  $this->tiles[$i]['hold'] = 3;
}
for ($i = 16; $i <= 24; $i++) {
  $this->tiles[$i]['type'] = 'cargo';
  $this->tiles[$i]['hold'] = 2;
}
for ($i = 25; $i <= 30; $i++) {
  $this->tiles[$i]['type'] = 'cargo';
  $this->tiles[$i]['hold'] = 3;
}
for ($i = 31; $i <= 48; $i++) {
  $this->tiles[$i]['type'] = 'crew';
}

for ($i = 49; $i <= 54; $i++) {
  $this->tiles[$i]['type'] = 'structure';
}

$this->tiles[55]['type'] = 'crew';

for ($i = 56; $i <= 60; $i++) {
  $this->tiles[$i]['type'] = 'hazard';
  $this->tiles[$i]['hold'] = 1;
}
for ($i = 61; $i <= 63; $i++) {
  $this->tiles[$i]['type'] = 'hazard';
  $this->tiles[$i]['hold'] = 2;
}

for ($i = 64; $i <= 80; $i++) {
  $this->tiles[$i]['type'] = 'engine';
  $this->tiles[$i]['hold'] = 1;
}
for ($i = 81; $i <= 87; $i++) {
  $this->tiles[$i]['type'] = 'engine';
  $this->tiles[$i]['hold'] = 2;
}

for ($i = 88; $i <= 108; $i++) {
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

for ($i = 115; $i <= 117; $i++) {
  $this->tiles[$i]['type'] = 'shield';
}

$this->tiles[118]['type'] = 'engine';
$this->tiles[118]['hold'] = 1;
$this->tiles[119]['type'] = 'brown';
$this->tiles[120]['type'] = 'shield';

for ($i = 121; $i <= 122; $i++) {
  $this->tiles[$i]['type'] = 'cannon';
  $this->tiles[$i]['hold'] = 1;
}
for ($i = 123; $i <= 130; $i++) {
  $this->tiles[$i]['type'] = 'cannon';
  $this->tiles[$i]['hold'] = 2;
}

for ($i = 131; $i <= 135; $i++) {
  $this->tiles[$i]['type'] = 'brown';
}
for ($i = 136; $i <= 140; $i++) {
  $this->tiles[$i]['type'] = 'purple';
}
for ($i = 141; $i <= 144; $i++) {
  $this->tiles[$i]['type'] = 'shield';
}

foreach ($this->tiles as &$tile) {
  if (!array_key_exists('hold', $tile)) {
    $tile['hold'] = $this->tileHoldCnt[$tile['type']];
  }
}

// 0 for no connector, 1 for simple, 2 for double, 3 for universal
$tileConnectors = [
  1 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  2 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  3 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 1],
  4 => ['w' => 3, 'n' => 0, 'e' => 3, 's' => 0],
  5 => ['w' => 3, 'n' => 1, 'e' => 1, 's' => 1],
  6 => ['w' => 3, 'n' => 1, 'e' => 2, 's' => 0],
  7 => ['w' => 3, 'n' => 2, 'e' => 0, 's' => 0],
  8 => ['w' => 3, 'n' => 2, 'e' => 1, 's' => 0],
  9 => ['w' => 3, 'n' => 2, 'e' => 2, 's' => 2],
  10 => ['w' => 1, 'n' => 0, 'e' => 2, 's' => 0],
  11 => ['w' => 1, 'n' => 1, 'e' => 0, 's' => 0],
  12 => ['w' => 1, 'n' => 2, 'e' => 0, 's' => 0],
  13 => ['w' => 2, 'n' => 0, 'e' => 0, 's' => 0],
  14 => ['w' => 2, 'n' => 1, 'e' => 0, 's' => 0],
  15 => ['w' => 2, 'n' => 2, 'e' => 1, 's' => 0],
  16 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  17 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  18 => ['w' => 3, 'n' => 0, 'e' => 1, 's' => 0],
  19 => ['w' => 3, 'n' => 0, 'e' => 1, 's' => 2],
  20 => ['w' => 3, 'n' => 0, 'e' => 2, 's' => 0],
  21 => ['w' => 3, 'n' => 0, 'e' => 2, 's' => 1],
  22 => ['w' => 3, 'n' => 1, 'e' => 2, 's' => 1],
  23 => ['w' => 3, 'n' => 2, 'e' => 1, 's' => 2],
  24 => ['w' => 3, 'n' => 3, 'e' => 0, 's' => 0],
  25 => ['w' => 1, 'n' => 0, 'e' => 0, 's' => 0],
  26 => ['w' => 1, 'n' => 0, 'e' => 1, 's' => 0],
  27 => ['w' => 2, 'n' => 0, 'e' => 0, 's' => 0],
  28 => ['w' => 2, 'n' => 0, 'e' => 1, 's' => 0],
  29 => ['w' => 2, 'n' => 0, 'e' => 1, 's' => 0],
  30 => ['w' => 2, 'n' => 0, 'e' => 2, 's' => 0],
  31 => ['w' => 3, 'n' => 3, 'e' => 3, 's' => 3],
  32 => ['w' => 3, 'n' => 3, 'e' => 3, 's' => 3],
  33 => ['w' => 3, 'n' => 3, 'e' => 3, 's' => 3],
  34 => ['w' => 3, 'n' => 3, 'e' => 3, 's' => 3],
  35 => ['w' => 1, 'n' => 2, 'e' => 1, 's' => 2],
  36 => ['w' => 1, 'n' => 2, 'e' => 1, 's' => 2],
  37 => ['w' => 2, 'n' => 1, 'e' => 2, 's' => 0],
  38 => ['w' => 2, 'n' => 2, 'e' => 1, 's' => 2],
  39 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 1],
  40 => ['w' => 3, 'n' => 0, 'e' => 1, 's' => 0],
  41 => ['w' => 3, 'n' => 0, 'e' => 1, 's' => 1],
  42 => ['w' => 3, 'n' => 0, 'e' => 1, 's' => 2],
  43 => ['w' => 3, 'n' => 1, 'e' => 0, 's' => 1],
  44 => ['w' => 3, 'n' => 1, 'e' => 0, 's' => 2],
  45 => ['w' => 3, 'n' => 2, 'e' => 0, 's' => 0],
  46 => ['w' => 3, 'n' => 2, 'e' => 0, 's' => 2],
  47 => ['w' => 3, 'n' => 2, 'e' => 2, 's' => 0],
  48 => ['w' => 1, 'n' => 2, 'e' => 1, 's' => 0],
  49 => ['w' => 3, 'n' => 1, 'e' => 3, 's' => 0],
  50 => ['w' => 3, 'n' => 1, 'e' => 3, 's' => 1],
  51 => ['w' => 3, 'n' => 1, 'e' => 3, 's' => 2],
  52 => ['w' => 3, 'n' => 2, 'e' => 3, 's' => 0],
  53 => ['w' => 3, 'n' => 3, 'e' => 1, 's' => 2],
  54 => ['w' => 3, 'n' => 3, 'e' => 2, 's' => 0],
  55 => ['w' => 1, 'n' => 1, 'e' => 2, 's' => 1],
  56 => ['w' => 3, 'n' => 0, 'e' => 2, 's' => 1],
  57 => ['w' => 3, 'n' => 0, 'e' => 3, 's' => 0],
  58 => ['w' => 3, 'n' => 1, 'e' => 1, 's' => 1],
  59 => ['w' => 3, 'n' => 2, 'e' => 1, 's' => 0],
  60 => ['w' => 3, 'n' => 2, 'e' => 2, 's' => 2],
  61 => ['w' => 1, 'n' => 0, 'e' => 0, 's' => 0],
  62 => ['w' => 1, 'n' => 0, 'e' => 2, 's' => 0],
  63 => ['w' => 2, 'n' => 0, 'e' => 0, 's' => 0],
  64 => ['w' => 0, 'n' => 0, 'e' => 3, 's' => 0],
  65 => ['w' => 0, 'n' => 0, 'e' => 3, 's' => 0],
  66 => ['w' => 0, 'n' => 1, 'e' => 0, 's' => 0],
  67 => ['w' => 0, 'n' => 1, 'e' => 1, 's' => 0],
  68 => ['w' => 0, 'n' => 2, 'e' => 0, 's' => 0],
  69 => ['w' => 0, 'n' => 2, 'e' => 0, 's' => 0],
  70 => ['w' => 0, 'n' => 2, 'e' => 3, 's' => 0],
  71 => ['w' => 0, 'n' => 3, 'e' => 2, 's' => 0],
  72 => ['w' => 1, 'n' => 0, 'e' => 3, 's' => 0],
  73 => ['w' => 1, 'n' => 3, 'e' => 0, 's' => 0],
  74 => ['w' => 2, 'n' => 1, 'e' => 2, 's' => 0],
  75 => ['w' => 2, 'n' => 2, 'e' => 0, 's' => 0],
  76 => ['w' => 2, 'n' => 3, 'e' => 1, 's' => 0],
  77 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  78 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  79 => ['w' => 3, 'n' => 0, 'e' => 2, 's' => 0],
  80 => ['w' => 3, 'n' => 1, 'e' => 0, 's' => 0],
  81 => ['w' => 0, 'n' => 1, 'e' => 0, 's' => 0],
  82 => ['w' => 0, 'n' => 1, 'e' => 3, 's' => 0],
  83 => ['w' => 0, 'n' => 2, 'e' => 0, 's' => 0],
  84 => ['w' => 1, 'n' => 1, 'e' => 1, 's' => 0],
  85 => ['w' => 2, 'n' => 2, 'e' => 2, 's' => 0],
  86 => ['w' => 3, 'n' => 0, 'e' => 3, 's' => 0],
  87 => ['w' => 3, 'n' => 2, 'e' => 0, 's' => 0],
  88 => ['w' => 0, 'n' => 0, 'e' => 0, 's' => 1],
  89 => ['w' => 0, 'n' => 0, 'e' => 0, 's' => 1],
  90 => ['w' => 0, 'n' => 0, 'e' => 0, 's' => 2],
  91 => ['w' => 0, 'n' => 0, 'e' => 0, 's' => 2],
  92 => ['w' => 0, 'n' => 0, 'e' => 1, 's' => 2],
  93 => ['w' => 0, 'n' => 0, 'e' => 2, 's' => 1],
  94 => ['w' => 0, 'n' => 0, 'e' => 2, 's' => 3],
  95 => ['w' => 0, 'n' => 0, 'e' => 3, 's' => 0],
  96 => ['w' => 0, 'n' => 0, 'e' => 3, 's' => 0],
  97 => ['w' => 0, 'n' => 0, 'e' => 3, 's' => 1],
  98 => ['w' => 1, 'n' => 0, 'e' => 0, 's' => 2],
  99 => ['w' => 1, 'n' => 0, 'e' => 0, 's' => 3],
  100 => ['w' => 1, 'n' => 0, 'e' => 1, 's' => 1],
  101 => ['w' => 1, 'n' => 0, 'e' => 2, 's' => 0],
  102 => ['w' => 1, 'n' => 0, 'e' => 2, 's' => 3],
  103 => ['w' => 2, 'n' => 0, 'e' => 0, 's' => 1],
  104 => ['w' => 2, 'n' => 0, 'e' => 1, 's' => 3],
  105 => ['w' => 2, 'n' => 0, 'e' => 2, 's' => 2],
  106 => ['w' => 2, 'n' => 0, 'e' => 3, 's' => 0],
  107 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  108 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  109 => ['w' => 3, 'n' => 0, 'e' => 2, 's' => 0],
  110 => ['w' => 1, 'n' => 3, 'e' => 3, 's' => 0],
  111 => ['w' => 3, 'n' => 3, 'e' => 2, 's' => 2],
  112 => ['w' => 3, 'n' => 3, 'e' => 0, 's' => 0],
  113 => ['w' => 1, 'n' => 2, 'e' => 1, 's' => 0],
  114 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 2],
  115 => ['w' => 2, 'n' => 1, 'e' => 2, 's' => 1],
  116 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 1],
  117 => ['w' => 3, 'n' => 0, 'e' => 2, 's' => 2],
  118 => ['w' => 0, 'n' => 1, 'e' => 0, 's' => 0],
  119 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  120 => ['w' => 2, 'n' => 0, 'e' => 2, 's' => 2],
  121 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 2],
  122 => ['w' => 3, 'n' => 0, 'e' => 1, 's' => 0],
  123 => ['w' => 0, 'n' => 0, 'e' => 0, 's' => 1],
  124 => ['w' => 0, 'n' => 0, 'e' => 0, 's' => 2],
  125 => ['w' => 0, 'n' => 0, 'e' => 1, 's' => 3],
  126 => ['w' => 0, 'n' => 0, 'e' => 3, 's' => 2],
  127 => ['w' => 1, 'n' => 0, 'e' => 1, 's' => 2],
  128 => ['w' => 2, 'n' => 0, 'e' => 0, 's' => 3],
  129 => ['w' => 2, 'n' => 0, 'e' => 2, 's' => 1],
  130 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 1],
  131 => ['w' => 1, 'n' => 1, 'e' => 1, 's' => 0],
  132 => ['w' => 1, 'n' => 2, 'e' => 1, 's' => 0],
  133 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 1],
  134 => ['w' => 3, 'n' => 0, 'e' => 2, 's' => 0],
  135 => ['w' => 3, 'n' => 1, 'e' => 0, 's' => 0],
  136 => ['w' => 2, 'n' => 1, 'e' => 2, 's' => 0],
  137 => ['w' => 2, 'n' => 2, 'e' => 2, 's' => 0],
  138 => ['w' => 3, 'n' => 0, 'e' => 0, 's' => 0],
  139 => ['w' => 3, 'n' => 0, 'e' => 1, 's' => 0],
  140 => ['w' => 3, 'n' => 2, 'e' => 0, 's' => 0],
  141 => ['w' => 1, 'n' => 0, 'e' => 1, 's' => 3],
  142 => ['w' => 1, 'n' => 1, 'e' => 0, 's' => 1],
  143 => ['w' => 1, 'n' => 2, 'e' => 1, 's' => 2],
  144 => ['w' => 2, 'n' => 0, 'e' => 0, 's' => 3],
];
foreach ($tileConnectors as $id => $tile) {
  foreach ($tile as $dir => $conType) {
    $this->tiles[$id][$dir] = $conType;
  }
}