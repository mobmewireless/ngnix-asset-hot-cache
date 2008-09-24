<?php
/*

Nginx-Asset-Hot-Cache
Author: Vishnu Gopal
Copyright: MobME Wireless Solutions Pvt. Ltd. http://mobme.in
Home: 

Licensed under the new BSD license.

*/

/**
*
* This is an Asset Hot cache, meant to be served from within Nginx.
* 
* It works by maintaining a local cache of frequently accessed S3 items.
* Requests to nginx take the form: http://{nginx_host}:{nginx_port}/?{original_asset_url}
* e.g: http://0.0.0.0:0/http://nuser-photo-2.mobshare.in/1212043593_2878.jpg
*
* This is converted to:
* A location on disk: {dump_dir}/{original_asset_url_without_protocoL_and_slashes_replaced_by_double_underscore}
*
* If the location exists on disk, using X-Accel-Redirect, it's proxied back to requestee, otherwise:
* * A 301 is returned to the asset location, and:
* * A GET is made to {asset_retriever/host}:{asset_retriever/port}/{asset_retriever/endpoint}?{asset_location}
*
* Asset retriever then makes a synchronous request to get the asset and store it in the correct location
* (see asset_retriever.rb for more on retriever's functioning)
*
* To expire an asset from the cache, simply delete it from the dump location.
*/

require_once('./vendor/spyc/spyc.php');

$settings = Spyc::YAMLLoad('../settings.yml');
$log = false;

$asset_url = filter_var($_SERVER['QUERY_STRING'], FILTER_SANITIZE_URL);

$dump_location = str_replace(array('http://', 'https://', '/'), array('', '', '__'), $asset_url);
$dump_file = "../" . $settings['dump_dir'] . '/' . $dump_location;

log_message("Initing...");
log_message("Checking for $dump_file...");

if(is_file($dump_file)) {
  # file in cache
  # see http://wiki.codemongers.com/NginxXSendfile
  log_message("File exists");
  header('X-Accel-Redirect: /protected/' . $dump_location);
} else { 
  # file not in cache
  log_message("File does not exist");
  log_message("Location: $asset_url");
  
  # Do a 301 redirect.
  header('Location: ' . $asset_url);
  ob_flush();
  
  # GET to the asset_retriever
  $asset_retriever_url = 'http://' . $settings['asset_retriever']['host'] . ':' . 
    $settings['asset_retriever']['port'] . '/' . $asset_url;
    
  log_message("Asset Retriever url: $asset_retriever_url");
    
  file_get_contents_with_timeout($asset_retriever_url);
}


function log_message($message) {
  global $log;
  if($log) {
    echo $message . "<br />";
  }
}


/* This works only for simple URIs
  Returns:

array(headers, body): 			if everything's fine.
  TIMED OUT 									if it times out.
  UNABLE TO OPEN  						if we can't connect to host.
  */
function file_get_contents_with_timeout($url, $read_timeout = 5, $connection_timeout = 5) {
  $url_parts = parse_url($url);

  $host = $url_parts['host'];
  $port = $url_parts['port'];
  $path = array_key_exists('path', $url_parts) ? $url_parts['path'] : '/';
  $get = $path . '?' . $url_parts['query'];

  $fp = fsockopen($host, $port, $errno, $errstr, $connection_timeout);
  if (!$fp) {
    return "UNABLE TO OPEN";
  } else {
    $out = "GET $get HTTP/1.1\r\n";
    $out .= "Host: $host\r\n";
    $out .= "Connection: Close\r\n\r\n";

    fwrite($fp, $out);
    stream_set_timeout($fp, $read_timeout);

    $result = stream_get_contents($fp);
    $divider = strpos($result, "\r\n\r\n");
    $headers = substr($result, 0, $divider);

    $body = substr($result, $divider, strlen($result));

    $info = stream_get_meta_data($fp);

    fclose($fp);

    if ($info['timed_out']) {
      return 'TIMED OUT';
    } else {
      return array('headers' => $headers, 'body' => $body);
    }
  }
}

