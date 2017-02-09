<?php
/*
 * EH_SocialShare
 * by Bobby Wibowo
 */

class EndlessHorizon_SocialShare_Listener2
{
    public static function load_class_controller($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Account')
        {
            $extend[] = 'EndlessHorizon_SocialShare_ControllerPublic_Account';
        }
    }

    public static function load_class_datawriter($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_User')
        {
            $extend[] = 'EndlessHorizon_SocialShare_DataWriter_User';
        }
    }

    /** DEBUG VARIABLES **/
    private static $dCacheHit;
    private static $dFetching;

    /** MAIN VARIABLES **/
    private static $mSiteUrl;
    private static $mCacheId;
    private static $mTimeout;
    private static $mCurlUsable;
    private static $mCurlMilliSeconds;
    private static $mCurlSecure;
    private static $mCurlSecurePeer;
    private static $mCurlLocalCert;
    private static $mFacebookAppId;
    private static $mFacebookAppSecret;

    /** DEBUG FUNCTION **/
    private static function logExceptionByType($m, $t)
    {
        if ($m && (($t === 1 && self::$dCacheHit) || ($t === 2 && self::$dFetching)))
        {
            return XenForo_Error::logException(new XenForo_Exception($m));
        }
        else
        {
            return false;
        }
    }

    /** MAIN FUNCTIONS **/
    private static function getCount($s)
    {
        $api = array(
            "facebook"    => "https://graph.facebook.com/fql?q=SELECT%20url,%20total_count%20FROM%20link_stat%20WHERE%20url=%27{url}%27",
            "facebook_v2" => "https://graph.facebook.com/?id={url}",
            "twitter"     => "http://opensharecount.com/count.json?url={url}",
            "googleplus"  => "https://plusone.google.com/u/0/_/+1/fastbutton?count=true&url={url}",
            "linkedin"    => "https://www.linkedin.com/countserv/count/share?format=json&url={url}",
            "pinterest"   => "https://widgets.pinterest.com/v1/urls/count.json?url={url}",
            //"buffer"      => "https://api.bufferapp.com/1/links/shares.json?url={url}",
            "vk"          => "https://vk.com/share.php?act=count&index=1&url="
        );
        
        $url = str_replace("{url}", rawurlencode(self::$mSiteUrl), $api[$s]);

        // Facebook v2 patch (use newer Graph API with access token if available)
        if ($s === 'facebook_v2') { $url = $url.'&access_token='.self::$mFacebookAppId.'|'.self::$mFacebookAppSecret; }

        self::logExceptionByType('EHSS_DEBUG: '.$s.' service will be fetched with API: '.$url.' (Session: '.self::$mCacheId.')', 2);
        
        if (self::$mCurlUsable)
        {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            if (self::$mCurlMilliSeconds)
            {
                curl_setopt($ch, CURLOPT_TIMEOUT_MS, self::$mTimeout);
            }
            else
            {
                $t = self::$mTimeout / 1000;
                curl_setopt($ch, CURLOPT_TIMEOUT, (int) ceil($t));
            }

            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, (self::$mCurlSecure ? 2 : 0));

            if (self::$mCurlSecurePeer) { curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (self::$mCurlSecure ? 1 : 0)); }

            if (self::$mCurlLocalCert && self::$mCurlSecure) { curl_setopt($ch, CURLOPT_CAINFO, getcwd().'/library/EndlessHorizon/SocialShare/cacert.pem'); }

            curl_setopt($ch, CURLOPT_FAILONERROR, 1);

            $res = curl_exec($ch);

            if ($res === false) { self::logExceptionByType('EHSS_DEBUG: '.$s.' service encountered issue with cURL: '.curl_error($ch), 2); }

            curl_close($ch);
        }
        else
        {
            if (self::$mTimeout > 0)
            {
                $t = self::$mTimeout / 1000;
                $ctx = stream_context_create(array('http'=>array('timeout' => ceil($t))));
                $res = @file_get_contents($url, false, $ctx);
            }
            else
            {
                $res = @file_get_contents($url);
            }
        }
        
        if ($res)
        {
            switch($s)
            {
                case "facebook":
                    $res = json_decode($res);
                    $count = $res->data[0]->total_count;
                    break;
                case "facebook_v2":
                    $res = json_decode($res);
                    $count = (property_exists($res, 'shares') ? $res->shares : $res->share->share_count);
                    break;
                case "googleplus":
                    preg_match( '/window\.__SSR = {c: (\d+(?:\.\d+)+)/', $res, $matches);
                    if (isset($matches[0]) && isset($matches[1]))
                    {
                        $bits = explode('.', $matches[1]);
                        if ($bits[0] !== null) { $count = $bits[0]; } else { $count = '0'; }
                    }
                    break;
                case "pinterest":
                    $res = substr($res, 13, -1);
                case "linkedin":
                case "twitter":
                    $res = json_decode($res);
                    $count = $res->count;
                    break;
                /*case "buffer":
                    $res = json_decode($res);
                    $count = $res->shares;
                    break;*/
                case "vk":
                    $res = preg_match('/^VK.Share.count\(\d+,\s+(\d+)\);$/i', $res, $matches);
                    $count = $matches[1];
                    break;
                default: break;
            }

            $count = (int) $count;
        }
        else
        {
            $count = -1;
        }
        
        return $count;
    }
    
    public static function getShareCounts()
    {
        // XenForo options
        $o = XenForo_Application::get('options');

        // Debug options
        self::$dCacheHit = $o->EHSS_CacheHitDebug;

        // XenForo variables
        $cache = XenForo_Application::getCache();
        $req   = XenForo_Application::get('requestPaths');
        
        
        // Main options
        $cacheIdPrefix  = $o->EHSS_CacheIdPrefix;
        $cacheTime      = $o->EHSS_CacheTime;
        self::$mSiteUrl = $req['fullUri'];
        self::$mCacheId = ($cacheIdPrefix ?: '').md5(self::$mSiteUrl);

        // Local variables
        $counts        = array();
        $prevCache     = false;

        if ($cacheTime === 0) { self::logExceptionByType('EHSS_DEBUG: Caching was effectively disabled because cache time was set to 0 (Session: '.self::$mCacheId.')', 1); }

        if ($cache && ($cacheTime > 0))
        {
            $prevCache = $cache->load(self::$mCacheId);
        
            if ($prevCache)
            {
                $counts = json_decode($prevCache, true);
                
                foreach ($counts as $v)
                {
                    if ($v !== -1)
                    {
                        self::logExceptionByType('EHSS_DEBUG: Share counters were loaded from cache and the data was valid (Session: '.self::$mCacheId.')', 1);
                        // Return counts if at least one count was valid
                        return json_encode($counts);
                    }
                }
            }
        }

        /** WILL ONLY GET TO THIS POINT IF THERE WAS NO CACHE **/

        // More debug options
        self::$dFetching      = $o->EHSS_FetchingDebug;

        // More main options
        self::$mTimeout       = $o->EHSS_Timeout;
        self::$mCurlSecure    = $o->EHSS_CurlSecure;
        self::$mCurlLocalCert = $o->EHSS_CurlLocalCert;
        $oDisableCurl         = $o->EHSS_DisableCurl;
        $oZeroFallback        = $o->EHSS_ZeroFallback;

        // Define self::$mCurlUsable, self::$mCurlMilliSeconds and self::$mCurlSecurePeer
        if ($oDisableCurl || !function_exists('curl_version'))
        {
            self::$mCurlUsable = false;

            self::logExceptionByType('EHSS_DEBUG: Did not use cURL because either it was disabled or the module did not exist (Session: '.self::$mCacheId.')', 2);
        }
        else
        {
            $t = curl_version();

            self::$mCurlMilliSeconds = version_compare($t['version'], '7.16.2', '>=');

            if (!self::$mCurlMilliSeconds)
            {
                self::logExceptionByType('EHSS_DEBUG: Did not use milliseconds as timeout value because cURL version was older than version 7.16.2 (Your version: '.$t['version'].', Session: '.self::$mCacheId.')', 2);
            }

            self::$mCurlSecurePeer = version_compare($t['version'], '7.10', '>=');

            if (!self::$mCurlSecurePeer)
            {
                self::logExceptionByType('EHSS_DEBUG: Did not use cURL option \'CURLOPT_SSL_VERIFYPEER\' because cURL version was older than version 7.10 (Your version: '.$t['version'].', Session: '.self::$mCacheId.')', 2);
            }

            if (!self::$mCurlSecure)
            {
                self::logExceptionByType('EHSS_DEBUG: Did not use secure connection for cURL as instructed (Your version: '.$t['version'].', Session: '.self::$mCacheId.')', 2);
            }
        }

        // More local variables
        $counters = $o->EHSS_ShareCounter;
        $services = array("facebook", "twitter", "googleplus", "linkedin", "pinterest", "vk");
        $tryAgain = $o->EHSS_TryAgain;

        // Temporarily mark the current session as 'total failure'
        $tFailure = true;

        // Get count for enabled services
        foreach ($services as $s)
        {
            if (!$counters[$s]) { continue; }

            $ts = $s;

            // Facebook v2 patch (use newer Graph API with access token if available)
            if ($s === 'facebook')
            {
                self::$mFacebookAppId     = $o->facebookAppId;
                self::$mFacebookAppSecret = $o->facebookAppSecret;

                if (strlen(self::$mFacebookAppId) && strlen(self::$mFacebookAppSecret))
                {
                    $ts = 'facebook_v2';
                    self::logExceptionByType('EHSS_DEBUG: Used an alternative API for Facebook since Facebook App ID and Secret were available in XenForo options.', 2);
                }
            }

            $counts[$s] = self::getCount($ts);

            self::logExceptionByType('EHSS_DEBUG: '.$ts.' service returned '.$counts[$s].' (Session: '.self::$mCacheId.')', 2);

            // Disable the temporary 'total failure' mark if at least one site were fetched properly
            if ($counts[$s] >= 0) { $tFailure = false; } elseif ($oZeroFallback) { $counts[$s] = 0; }
        }

        $jsonCounts = json_encode($counts);

        if ($tFailure && $tryAgain)
        {
            self::logExceptionByType('EHSS_DEBUG: Did not save share counters because none were successfully fetched (Session: '.self::$mCacheId.')', 1);
        }
        elseif ($cache && ($cacheTime > 0))
        {
            $cache->save($jsonCounts, self::$mCacheId, array(), $cacheTime);
            self::logExceptionByType('EHSS_DEBUG: Share counters were stored to cache (Session: '.self::$mCacheId.')', 1);
        }
        else
        {
            self::logExceptionByType('EHSS_DEBUG: Did not save share counters because of missing cache object or caching was disabled (Session: '.self::$mCacheId.')', 1);
        }

        return $jsonCounts;
    }
    
    public static function front_controller_post_view(XenForo_FrontController $fc, &$output)
    {
        // FailSafe
        if (!$fc) { return false; }

        $dependencies = $fc->getDependencies();

        // Disable on Admin dependencies
        if ($dependencies instanceof XenForo_Dependencies_Admin) { return false; }

        // XenForo Route
        $route = $fc->route();

        // Disable on attachment controller
        if ($route->getControllerName() === "XenForo_ControllerPublic_Attachment") { return false; }
        
        if ($route->getResponseType() === "html")
        {
            $keyPos = strpos($output, '<!--EHSS_Widget_Exists-->');

            if ($keyPos !== false)
            {
                $req = new Zend_Controller_Request_Http();
                $res = new Zend_Controller_Response_Http();

                $viewRenderer = $dependencies->getViewRenderer($res, 'html', $req);

                $template     = $viewRenderer->renderView('', array(), 'eh_socialshare_js');
                
                $output       = str_replace('<!--EHSS_Widget_Exists-->', '', $output);
                $output       = str_replace('<script type="text/javascript" data-ehss="true"></script>', $template, $output);
            }
            else
            {
                $output       = str_replace('<script type="text/javascript" data-ehss="true"></script>', '', $output);
            }

            return true;
        }
        else
        {
            return false;
        }
    }
}

?>