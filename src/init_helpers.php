<?php
/**
 * Created by PhpStorm.
 * User: carey
 * Date: 31/07/17
 * Time: 15:22
 */

if (!function_exists('ddd')) {

    function ddd($game)
    {
        foreach (func_get_args() as $var) {
            d($var);
        }
        die();
    }
}


if (!function_exists('sd')) {
    function sd($game)
    {
        foreach (func_get_args() as $var) {
            s($var);
        }
        die();
    }
}