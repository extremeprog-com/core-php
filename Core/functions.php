<?php

function is_array_of_arrays($array) {
    if(!is_array($array))
        return false;
    foreach($array as $a)
        if(!is_array($a))
            return false;
    return true;
}

/**
 * Проверка существуют ли все ключи в массиве
 *
 * @param array $keys
 * @param array $array
 * @return bool
 */
function array_keys_exist(array $keys, array $array) {
    return sizeof($keys) === sizeof(array_intersect($keys, array_keys($array)));
}

function array_diff_multi($arr1, $arr2) {
  $result = array();
  foreach ($arr1 as $k=>$v) {
    if(!isset($arr2[$k])) {
      $result[$k] = $v;
    } else {
      if (is_array($v) && is_array($arr2[$k])) {
        $diff = array_diff_multi($v, $arr2[$k]);
        if (!empty($diff))
          $result[$k] = $diff;
      }
    }
  }
  return $result;
}

function BENCHMARK(){}