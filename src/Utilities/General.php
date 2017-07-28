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
        $str = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/app/assets/json/cachebust.json');
        $json = json_decode($str);
        foreach ($json as $k => $v) {
            if (HOST_TYPE == 'local') {
                //debug deployments
                //$cachebust[basename($k)] = ltrim($v, '.');

                // or

                //normal operations
                // $cachebust[basename($k)] = sprintf('/assets/%s', str_replace('./build/static/', '', $k));

                $cachebust[basename($k)] = ltrim(str_replace('/static', '' ,$k), '.');

            } else {
                $cachebust[basename($k)] = sprintf('//static.noahsarkzoofarm.co.uk/%s', str_replace('./build/static/', '', $v));
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

    public static function makeUrl($title, $area, $count = 0)
    {
        $f3 = \Base::instance();
        $web = \Web::instance();
        $slug = $web->slug($title);
        if ($count == 0 && $area) {
            $url = sprintf('/%s/%s', $web->slug($area), $slug);
        } elseif($count == 0 && $area == NULL){
            $url = sprintf('/%s', $slug);
        } elseif($count > 0 && $area == NULL){
            $url = sprintf('/%s-%d', $slug, $count);
        } else {
            $url = sprintf('/%s/%s-%d', $web->slug($area), $slug, $count);
        }
        $model = new Mapper($f3->get('DB'), 'urls');
        $model->load(['url=?', $url]);
        if ($model->dry()) {
            return ['url' => $url, 'slug' => $slug];
        } else {
            return self::makeUrl($title, $area, ++$count);
        }

    }

    public static function getBlogCats($id = NULL){
        $f3 = \Base::instance();
        $db = $f3->get('DB');
        $cats = $db->exec("SELECT id, title FROM blog_cats ORDER BY title");
        if (!$id){
            return $cats;
        } else {
            foreach ($cats as $c) {
                $d['id'] = $c['id'];
                $d['title'] = $c['title'];
                $d['checked'] = General::blogCatChecked($id, $c['id']);
                $data[] = $d;
            }
            return $data;
        }
    }

    public static function blogCatChecked($blog_id, $cat_id){

        $f3 = \Base::instance();
        $db = $f3->get('DB');
        $cats = $db->exec("SELECT blog_id FROM blog_rel WHERE blog_id = '$blog_id' AND blog_cat_id = '$cat_id'");
        if(!empty($cats)){
            $is_chcked = 1;
        } else {
            $is_chcked = 0;
        }
        return $is_chcked;
    }

    public static function getEventDays($id){
        $f3 = \Base::instance();
        $db = $f3->get('DB');
        $days = $db->exec("SELECT day FROM events_daily_rel WHERE event_id = $id");
        $clean_days = array_map(function($row){
            return $row['day'];
        }, $days);

        $i = 1;
        foreach (range(1, 6) as $day) {
            $d['day'] = $day;
            $d['checked'] = (in_array($i++, $clean_days)) ? 1 : 0;
            $data[] = $d;
        }

        return $data;
    }

    public static function throw404() {
        $f3 = \Base::instance();
        $f3->error(404);
    }

    public static function colorize($str, $color)
    {
        return chr(27) . "$color" . "$str" . chr(27) . "[0m";
    }

    public static function orderHash($id, $email)
    {
        return md5(sprintf('%s%s%s',$email, $id, strrev($id)));
    }
    public static function statusString($status)
    {
        switch ($status) {
            case Config::CHECKOUT_NEW:
                return 'Abandoned';
                break;
            case Config::CHECKOUT_PAYPAL:
                return 'Abandoned Paypal';
                break;
            case Config::CHECKOUT_PAYPAL_CANCEL:
                return 'User aborted Paypal';
                break;
            case Config::CHECKOUT_PAYPAL_FAIL:
                return 'Paypal failure';
                break;
            case Config::CHECKOUT_3DSECURE:
                return '3D Secure Timeout';
                break;
            case Config::CHECKOUT_3DSECURE_FAIL:
                return '3D Secure Failure';
                break;
            case Config::CHECKOUT_TO_BE_DISPATCHED:
                return 'Not dispatched';
                break;
            case Config::CHECKOUT_PARTIAL_DISPATCHED:
                return 'Partial dispatched';
                break;
            case Config::CHECKOUT_COMPLETE:
                return 'Order complete';
                break;
            default:
                return 'NO STATUS STRING FOR CODE '.$status;
                break;
        }
    }

    public static function statusClass($status)
    {
        switch ($status) {
            case Config::CHECKOUT_NEW:
            case Config::CHECKOUT_3DSECURE:
            case Config::CHECKOUT_3DSECURE_FAIL:
            case Config::CHECKOUT_PAYPAL:
            case Config::CHECKOUT_PAYPAL_FAIL:


                return 'danger';
                break;
            case Config::CHECKOUT_TO_BE_DISPATCHED:
            case Config::CHECKOUT_PAYPAL_CANCEL:
                return 'warning';
                break;
            case Config::CHECKOUT_PARTIAL_DISPATCHED:

                return 'info';
                break;
            case Config::CHECKOUT_COMPLETE:
                return 'success';
                break;
            default:
                return 'status-'.$status;
                break;
        }
    }

    public static function getPaymentDisplay($type)
    {
        switch($type) {
            case 'paypal':
            case 'visa':
            case 'mastercard':
                return 'fa fa-cc-'. $type;
                break;
            default:
                return 'fa fa-credit-card';
                break;
        }
    }

    public static function orderAdminLog($which, $who, $what)
    {
        $f3 = \Base::instance();
        $model = new Mapper($f3->get('DB'), 'order_log');
        $model->reset();
        $model->which = $which;
        $model->who = $who;
        $model->what = $what;
        $model->save();

    }

    public static function getAnimalSilhouettes()
    {
        try {
            $file = sprintf('%sapp/assets/stylesheets/web/base/silhouettes.scss', Config::get('BASE_PATH'));
            $c = file_get_contents($file);
            preg_match_all("|&\.(.*):before|U",
                $c,
                $out);
            $out = array_slice($out[1], 13);
            sort($out);

        } catch (\Exception $e) {
            $out = [""];
        }
        array_unshift($out, "");
        return $out;
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

    public static function localiseOrderDate($date)
    {
        $f3 = \Base::instance();
        $offset = intval($f3->get('SESSION.na_cms_admin.offset')) * -1;
        $dt = (new \DateTime($date, new \DateTimeZone('UTC')))->modify(sprintf('%s minutes', $offset));
        return $dt->format('Y-m-d H:i:s');
    }

    public static function countryToTimezone($country)
    {
        switch ($country) {
            case 'GB':
                return 'Europe/London';
                break;

            default:
                return 'UTC';
        }
    }


}