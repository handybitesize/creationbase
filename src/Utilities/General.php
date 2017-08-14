<?php
/**
 * Created by PhpStorm.
 * User: carey
 * Date: 30/08/16
 * Time: 15:18
 */

namespace CreationMedia\Utilities;


use CreationMedia\Config;

class General
{
    public static function isAWS()
    {
        $hv_uuid = @exec('cat /sys/hypervisor/uuid');
        if ($hv_uuid) {
            return true;
        }
        return false;
    }

    public static function getInstanceId()
    {
        if (self::isAWS()) {
            return file_get_contents("http://169.254.169.254/latest/meta-data/instance-id");
        }
        return gethostname();
    }

    public static function getCacheBuster()
    {
        //todo add cache buster to grunt
        $cachebust = [];
        $str = @file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/app/assets/json/cachebust.json');
        $json = json_decode($str) ?: [];

        foreach ($json as $k => $v) {
            if (HOST_TYPE == 'local') {
                $cachebust[basename($k)] = ltrim(str_replace('/static', '' ,$k), '.');
            } else {
                $cachebust[basename($k)] = sprintf('//%s/%s', Config::get('STATIC_URL'), str_replace('./build/static/', '', $v));
            }

        }
        return $cachebust;
    }

    public static function flushJsonResponse(Array $a, $statusCode = 200, $base64encode = false)
    {
        $j = json_encode($a);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        $f3 = \Base::instance();
        $f3->status($statusCode);
        if ($base64encode) {
            $j = base64_encode($j);
        }
        die($j);
    }

    public static function flushXmlResponse( $str, $statusCode = 200, $base64encode = false)
    {
        if (!headers_sent()) {
            header('Content-Type: application/xml');
        }
        $f3 = \Base::instance();
        $f3->status($statusCode);
        die($str);
    }

    public static function makeCleanHtml($html)
    {
        $cleanTags = strip_tags($html, '<iframe><div><span><strong><em><h1><h2><ul><li><img><a><p><table><tbody><tr><td>');
        return $cleanTags;
    }

    public static function makeCleanHtmlSummary($html)
    {
        $cleanTags = strip_tags($html, '<strong><em><ul><li><a><p>');
        return $cleanTags;
    }

    public static function getCurrentMySqlDate()
    {
        return date('Y-m-d H:i:s');
    }

    public static function throw404() {
        $f3 = \Base::instance();
        $f3->error(404);
    }

    public static function colorize($str, $color)
    {
        return chr(27) . "$color" . "$str" . chr(27) . "[0m";
    }
    
    public static function getBodyArrayFromWebRequest($body){
        $out = [];
        $body = explode("\n", $body);
        foreach($body as $line) {
            list($key, $value) = explode('=', trim($line));
            $out[$key] = $value;
        }
        return $out;
    }


}
