<?php
class GT_Constants {
    public static $CONTENT_INT_TYPE_MAP = [
        1 => 'crew', 2 => 'cell', 3 => 'goods'
    ];

    // Must be array_flip of $CONTENT_INT_TYPE_MAP
    public static $CONTENT_TYPE_INT_MAP = [
        'crew' => 1, 'cell' => 2, 'goods' => 3
    ];

    public static $ALLOWABLE_SUBTYPES = array(
        "crew" => array("human", "brown", "purple", "ask_human", "ask_brown", "ask_purple"),
        "cell" => array("cell"),
        "goods" => array("red", "yellow", "green", "blue")
    );

    // human-readable direction used in the context: "meteor incident from the $DIREC"
    public static $DIRECTION_NAMES = array(
        0 => "front",
        90 => "right",
        180 => "rear",
        270 => "left"
    );

    public static $SIZE_NAMES = array( 's' => 'small', 'b' => 'big');


    // map shipClasses to ints for global values
    public static $SHIP_CLASS_INTS = array(
        'I' => 1, 'II' => 2, 'III' => 3, 'IIIa' => 4
    );

    // rows/columns that miss given ship classes (using SHIP_CLASS_INTS)
    public static $SHIP_CLASS_MISSES = array(
        '1_row' => array(2,3,4,10,11,12),
        '1_column' => array(2,3,4,10,11,12),
        '2_row' => array(2,3,10,11,12),
        '2_column' => array(2,3,11,12),
        '3_row' => array(2,3,10,11,12),
        '3_column' => array(2,12),
        '4_row' => array(2,12),
        '4_column' => array(2,3,4,10,11,12),
    );
}
?>