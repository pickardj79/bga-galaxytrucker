<?php
class GT_Constants
{
  public static $CONTENT_INT_TYPE_MAP = [
    1 => 'crew',
    2 => 'cell',
    3 => 'goods',
  ];

  // Must be array_flip of $CONTENT_INT_TYPE_MAP
  public static $CONTENT_TYPE_INT_MAP = [
    'crew' => 1,
    'cell' => 2,
    'goods' => 3,
  ];

  public static $ALLOWABLE_SUBTYPES = [
    'crew' => ['human', 'brown', 'purple', 'ask_human', 'ask_brown', 'ask_purple'],
    'cell' => ['cell'],
    'goods' => ['red', 'yellow', 'green', 'blue'],
  ];

  // human-readable direction used in the context: "meteor incident from the $DIREC"
  public static $DIRECTION_NAMES = [
    0 => 'front',
    90 => 'right',
    180 => 'rear',
    270 => 'left',
  ];

  public static $SIZE_NAMES = ['s' => 'small', 'b' => 'big'];

  // map shipClasses to ints for global values
  public static $SHIP_CLASS_INTS = [
    'I' => 1,
    'II' => 2,
    'III' => 3,
    'IIIa' => 4,
  ];

  // rows/columns that miss given ship classes (using SHIP_CLASS_INTS)
  public static $SHIP_CLASS_MISSES = [
    '1_row' => [2, 3, 4, 10, 11, 12],
    '1_column' => [2, 3, 4, 10, 11, 12],
    '2_row' => [2, 3, 10, 11, 12],
    '2_column' => [2, 3, 11, 12],
    '3_row' => [2, 3, 10, 11, 12],
    '3_column' => [2, 12],
    '4_row' => [2, 12],
    '4_column' => [2, 3, 4, 10, 11, 12],
  ];
}
?>
