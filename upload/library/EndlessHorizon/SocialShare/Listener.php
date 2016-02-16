<?php

class EndlessHorizon_SocialShare_Listener
{
	private static function getCount($service, $url) {
		$cURLTimeout = XenForo_Application::get('options')->EHSS_CurlTimeout;
		
		$shareLinks = array(
			"facebook"    => "https://graph.facebook.com/fql?q=SELECT%20url,%20normalized_url,%20share_count,%20like_count,%20comment_count,%20total_count,commentsbox_count,%20comments_fbid,%20click_count%20FROM%20link_stat%20WHERE%20url=%27{url}%27",
            "twitter"     => "http://opensharecount.com/count.json?url={url}",
            "googleplus"  => "https://plusone.google.com/u/0/_/+1/fastbutton?count=true&url={url}",
            "linkedin"    => "https://www.linkedin.com/countserv/count/share?format=json&url={url}",
            "pinterest"   => "https://api.pinterest.com/v1/urls/count.json?url={url}",
            "delicious"   => "https://feeds.delicious.com/v2/json/urlinfo/data?url={url}",
            "buffer"      => "https://api.bufferapp.com/1/links/shares.json?url={url}",
			"vk"          => "https://vk.com/share.php?act=count&index=1&url="
        );
		
		$shareUrl = str_replace("{url}", $url, $shareLinks[$service]);
		
		if (function_exists('curl_version')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $shareUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT_MS, $cURLTimeout);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $result = curl_exec($ch);
            curl_close($ch);
        } else {
            $result = @file_get_contents($shareUrl);
        }
		
		if ($result) {
            switch($service) {
				case "facebook":
					$result = json_decode($result);
					$count = $result->data[0]->total_count;
					break;
				case "google":
					preg_match( '/window\.__SSR = {c: (\d+(?:\.\d+)+)/', $result, $matches);
					if(isset($matches[0]) && isset($matches[1])) {
						$bits = explode('.',$matches[1]);
						$count = (int)( empty($bits[0]) ?: $bits[0]) . ( empty($bits[1]) ?: $bits[1] ); 
					}
					break;
				case "pinterest":
					$result = substr($result, 13, -1);
				case "linkedin":
				case "twitter":
					$result = json_decode($result);
					$count = $result->count;
					break;
				case "delicious":
					$result = json_decode($result);
					$count = $result[0]->total_posts;
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
	
	private static function cacheHitDebug($message) {
		$permitted = XenForo_Application::get('options')->EHSS_CacheHitDebug;
		
		if (!$permitted) { return false; }
		
		return XenForo_Error::logException(new XenForo_Exception($message));
	}
	
	public static function getShareCounts() {
		$siteList = array("facebook", "twitter", "googleplus", "linkedin", "pinterest", "delicious", "buffer", "vk");
		$counts = array();
		
		$tmp = XenForo_Application::get('requestPaths');
        $url = $tmp['fullUri'];
		
		$cacheId = "ehss_".sprintf('%u', crc32($url)); // crc32 hash with 'ehss_' prefix
		$cacheObject = XenForo_Application::getCache();
		$cacheTime = XenForo_Application::get('options')->EHSS_CacheTime;
		$previousCache = false;
		
		if ($cacheObject) {
			$previousCache = $cacheObject->load($cacheId);
		
			if ($previousCache) {
				$counts = json_decode($previousCache, true);
				
				foreach ($counts as $value) {
					if ($value !== -1) {
						self::cacheHitDebug('Share counters were loaded from cache and the data was valid - Cache ID: '.$cacheId);
						return json_encode($counts); // Immediately return counts if at least one count was valid
					}
				}
			}
		}
		
		// Fetch share counts if there was no previous cache or the previous cache held no valid counts
		$keepTrying = XenForo_Application::get('options')->EHSS_KeepTrying;
		$completeFailure = true;
		
		foreach ($siteList as $site) {
			$counts[$site] = -1;
            
            $tmp = XenForo_Application::get('options')->EHSS_ShareCounter;
			if ($tmp[$site]) { $counts[$site] = self::getCount($site, $url); }
			
			if ($counts[$site] !== -1) { $completeFailure = false; }
		}
			
		if ($completeFailure && $keepTrying) {
			self::cacheHitDebug('WARNING: Did not save share counters because none were successfully fetched - Cache ID: '.$cacheId);
		} else {
			if ($cacheObject) {
				$cacheObject->save(json_encode($counts), $cacheId, array(), $cacheTime);
				self::cacheHitDebug('Share counters were stored on cache - Cache ID: '.$cacheId);
			} else {
				self::cacheHitDebug('WARNING: Did not save share counters because of missing cache object - Cache ID: '.$cacheId);
			}
		}
		
		return json_encode($counts);
	}
}

?>