<?php
/**
 * User: 刘单风
 * DateTime: 2016/6/3 16:13
 * CopyRight：医库软件PHP小组
 */

require_once(__DIR__ . '/../Foundation/Application.php');

if (!function_exists('post'))
{
    /**
     * Identical function to input(), however restricted to $_POST values.
     */
    function post($name = null, $default = null)
    {
        if ($name === null)
            return $_POST;

        /*
         * Array field name, eg: field[key][key2][key3]
         */
        if (class_exists('October\Rain\Html\Helper')) {
//            $name = implode('.', October\Rain\Html\Helper::nameToArray($name));
        }

        return array_get($_POST, $name, $default);
    }
}


