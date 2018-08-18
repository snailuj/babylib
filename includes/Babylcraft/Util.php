<?php

namespace Babylcraft;

class Util
{
    /**
     * Generates a v4 GUID
     * @link http://guid.us/GUID/PHP
     */
    static public function generateUid() {
        if (function_exists('com_create_guid')){
            return com_create_guid(); //windows PHP only
        } else {
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);// "}"
            return $uuid;
        }
    }
}