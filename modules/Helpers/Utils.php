<?php
namespace GT\Helpers;

// This is a class for static functions which help with some common calculations
abstract class Utils {
  public static function array_find($xs, $f) {
    foreach ($xs as $x) {
      if (call_user_func($f, $x) === true)
        return $x;
    }
    return null;
  }
}