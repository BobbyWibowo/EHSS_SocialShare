<?php

class EndlessHorizon_SocialShare_Listener
{
    private static function doesComplyWithMinCurlVersion($minVersion) {
        $ver_min = explode('.', $minVersion);
        if (!isset($ver_min[2])) { $ver_min[2] = '0'; } // Some cURL version doesn't have patch number - https://curl.haxx.se/docs/releases.html
        
        $tmp = curl_version();
        $ver_cur = explode('.', $tmp['version']);
        if (!isset($ver_cur[2])) { $ver_cur[2] = '0'; }
        
        $result = false;
        // Nested check (someone help simplify this, if possible)
        if ((int)$ver_cur[0] > (int)$ver_min[0]) {
            $result = true;
        } elseif ((int)$ver_cur[0] === (int)$ver_min[0]) {
            if ((int)$ver_cur[1] > (int)$ver_min[1]) {
                $result = true;
            } elseif ((int)$ver_cur[1] === (int)$ver_min[1]) {
                if ((int)$ver_cur[2] >= (int)$ver_min[2]) {
                    $result = true;
                }
            }
        }
        return $result;
    }
    
    private static function logExceptionByType($message, $type) {
        $permitted = false;
        
        if ($type === 1) {
            $permitted = XenForo_Application::get('options')->EHSS_CacheHitDebug;
        } elseif ($type === 2) {
            $permitted = XenForo_Application::get('options')->EHSS_CurlDebug;
        }
        
        if ($permitted) {
            return XenForo_Error::logException(new XenForo_Exception($message));
        } else {
            return false;
        }
    }
    
    private static function getCount($service, $siteUrl, $timeout, $useCurl, $useMS, $useSsl, $useSslVerifyPeer) {
        $shareLinks = array(
            "facebook"    => "https://graph.facebook.com/fql?q=SELECT%20url,%20normalized_url,%20share_count,%20like_count,%20comment_count,%20total_count,commentsbox_count,%20comments_fbid,%20click_count%20FROM%20link_stat%20WHERE%20url=%27{url}%27",
            "twitter"     => "http://opensharecount.com/count.json?url={url}",
            "googleplus"  => "https://plusone.google.com/u/0/_/+1/fastbutton?count=true&url={url}",
            "linkedin"    => "https://www.linkedin.com/countserv/count/share?format=json&url={url}",
            "pinterest"   => "https://widgets.pinterest.com/v1/urls/count.json?url={url}",
            "buffer"      => "https://api.bufferapp.com/1/links/shares.json?url={url}", // Waiting for FontAwesome to add Buffer icon
            "vk"          => "https://vk.com/share.php?act=count&index=1&url="
        );
        
        $url = str_replace("{url}", rawurlencode($siteUrl), $shareLinks[$service]);
        
        if ($useCurl) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            if ($useMS) {
                curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout);
            } else {
                $tmp = ($timeout/1000);
                curl_setopt($ch, CURLOPT_TIMEOUT, (int)ceil($tmp));
            }

            if ($useSsl) {
                curl_setopt($ch, CURLOPT_CAINFO, getcwd().'/library/EndlessHorizon/SocialShare/cacert.pem'); // Taken from https://curl.haxx.se/ca/cacert.pem
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                if ($useSslVerifyPeer) { curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1); }
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                if ($useSslVerifyPeer) { curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); }
            }
            curl_setopt($ch, CURLOPT_FAILONERROR, 1);

            $result = curl_exec($ch);

            if ($result === false) {
                self::logExceptionByType('ERROR: cURL ('.$service.'): '.curl_error($ch), 2);
            }

            curl_close($ch);
        } else {
            $result = @file_get_contents($url);
        }
        
        if ($result) {
            switch($service) {
                case "facebook":
                    $result = json_decode($result);
                    $count = $result->data[0]->total_count;
                    break;
                case "googleplus":
                    preg_match( '/window\.__SSR = {c: (\d+(?:\.\d+)+)/', $result, $matches);
                    if(isset($matches[0]) && isset($matches[1])) {
                        $bits = explode('.',$matches[1]);
                        $count = (int)(empty($bits[0]) ?: $bits[0]); 
                    }
                    break;
                case "pinterest":
                    $result = substr($result, 13, -1);
                case "linkedin":
                case "twitter":
                    $result = json_decode($result);
                    $count = $result->count;
                    break;
                case "buffer":
                    $result = json_decode($result);
                    $count = $result->shares;
                    break;
                case "vk":
                    $result = preg_match('/^VK.Share.count\(\d+,\s+(\d+)\);$/i', $result, $matches);
                    $count = $matches[1];
                    break;
                default: break;
            }
            $count = (int) $count;
        } else {
           $count = -1;
        }
        
        return $count;
    }
    
    public static function getShareCounts() {
        $services      = array("facebook", "twitter", "googleplus", "linkedin", "pinterest", "vk");
        $counts        = array();
        $tmp           = XenForo_Application::get('requestPaths');
        $siteUrl       = $tmp['fullUri'];
        $cacheIdSuffix = XenForo_Application::get('options')->EHSS_CacheIdSuffix;
        $cacheId       = "ehss_".sprintf('%u', crc32($siteUrl)).($cacheIdSuffix ? '_'.$cacheIdSuffix : ''); // CRC32 hash of the page's URL (fastest built-in hash for non-crypto use) with 'ehss_' prefix and custom suffix if available
        $cacheObject   = XenForo_Application::getCache();
        $cacheTime     = XenForo_Application::get('options')->EHSS_CacheTime;
        $previousCache = false;
        
        if ($cacheObject) {
            $previousCache = $cacheObject->load($cacheId);
        
            if ($previousCache) {
                $counts = json_decode($previousCache, true);
                
                foreach ($counts as $value) {
                    if ($value !== -1) {
                        self::logExceptionByType('INFO: Share counters were loaded from cache and the data was valid ('.$cacheId.')', 1);
                        return json_encode($counts); // Immediately return counts if at least one count was valid
                    }
                }
            }
        }
        
        // Fetch share counts if there was no previous cache or the previous cache held no valid counts
        $keepTrying      = XenForo_Application::get('options')->EHSS_KeepTrying;
        $completeFailure = true; // Mark current session as completely failed
        
        $curlTimeout     = XenForo_Application::get('options')->EHSS_CurlTimeout;
        $curlDisabled    = XenForo_Application::get('options')->EHSS_DisableCurl;
        $curlExist       = function_exists('curl_version');
        $curlVerPassMS   = false;
        $curlSslVerify   = XenForo_Application::get('options')->EHSS_CurlSslVerify;
        $curlVerPassPeer = false;
        
        if ($curlExist) {
            $curlVerPassMS   = self::doesComplyWithMinCurlVersion('7.16.2');
            $curlVerPassPeer = self::doesComplyWithMinCurlVersion('7.10');

            $tmp = curl_version();
            if (!$curlVerPassMS) { self::logExceptionByType('INFO: Did not use milliseconds as cURL timeout because cURL version was older than 7.16.2 (version: '.$tmp['version'].')', 2); }
            if (!$curlVerPassPeer) { self::logExceptionByType('INFO: Did not cURL option \'CURLOPT_SSL_VERIFYPEER\' because cURL version was older than 7.10 (version: '.$tmp['version'].')', 2); }
        }
        
        foreach ($services as $service) {
            $counts[$service] = -1;
            
            $tmp = XenForo_Application::get('options')->EHSS_ShareCounter;
            if ($tmp[$service]) { $counts[$service] = self::getCount($service, $siteUrl, $curlTimeout, (!$curlDisabled && $curlExist ? true : false), $curlVerPassMS, $curlSslVerify, $curlVerPassPeer); }
            
            if ($counts[$service] !== -1) {
                $completeFailure = false; // If at least one service was fetched properly, mark current session as not completely failed, then store to cache
            }
        }
            
        if ($completeFailure && $keepTrying) {
            self::logExceptionByType('WARNING: Did not save share counters because none were successfully fetched ('.$cacheId.')', 1);
        } else {
            if ($cacheObject) {
                $cacheObject->save(json_encode($counts), $cacheId, array(), $cacheTime);
                self::logExceptionByType('INFO: Share counters were stored on cache ('.$cacheId.')', 1);
            } else {
                self::logExceptionByType('WARNING: Did not save share counters because of missing cache object ('.$cacheId.')', 1);
            }
        }
        
        return json_encode($counts);
    }
    
    public static function front_controller_post_view(XenForo_FrontController $fc, &$output) {
        $responseType = $fc->route()->getResponseType();
        $controllerName = $fc->route()->getControllerName();
        $dependencies = $fc->getDependencies();
        
        // Disable on Admin features
        if ($dependencies instanceof XenForo_Dependencies_Admin) { return; }
        
        // Disable on attachments
        if ($controllerName === "XenForo_ControllerPublic_Attachment") { return; }
        
        if ($responseType === "html") {
            $ehss_keyPos = strpos($output, '<!--EHSS_Widget_Exist-->');
            if ($ehss_keyPos !== false) {
                // Is this the only way to fetch template..?
                $request      = new Zend_Controller_Request_Http();
                $response     = new Zend_Controller_Response_Http();
                $viewRenderer = $dependencies->getViewRenderer($response, 'html', $request);
                $template     = $viewRenderer->renderView('', array(), 'eh_socialshare_js');
                
                $output       = str_replace('<!--EHSS_Widget_Exist-->', '', $output);
                $output       = str_replace('<!--EHSS_Require_JS-->', $template, $output);
            } else {
                $output       = str_replace('<!--EHSS_Require_JS-->', '', $output);
            }
        }
    }
}

?>