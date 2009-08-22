<?php
/**
 * @file
 * The Hopper provides a variety of feeds related to the operations of the Grainery Organic Food Co-op
 * (thegrainery.wikispaces.com).
 * This file is copyright 2009 Ted Tibbetts and is licensed under the GPL: "http://www.gnu.org/copyleft/gpl.html" 
 */

// First task is to include the Zend Framework.
require_once('misc.php');
find_zend();

// Issue standard http headers that disable caching
header("Expires: Sun, 19 Nov 1978 05:00:00 GMT");
header("Last-Modified: ". gmdate("D, d M Y H:i:s") ." GMT");
header("Cache-Control: store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", FALSE);

hopper_router();

/**
 * @function
 * Parse http parameters and react appropriately
 */
function hopper_router() {
  // Get a list of available feeds
  $feeds = hopper_feed_list();

  // Provide a requested feed.
  if ($feed = $_GET['feed']) {

    if (in_array($feed, $feeds)) {
      require_once('feeds/'.$feed.'/'.$feed.'.php');
      // the _produce_feed functions handle the entire http request including headers.
      call_user_func($feed."_produce_feed");
    }
    else {
      // For invalid feed requests, provide info on available feeds.
      hopper_info(array('state' => 'error', 'cause' => 'argument', 'problem' => 'invalid', 'argument' => 'feed', 'feed' => $feed));
    }
  } 

  // Provide specific info on hopper features
  else if ($focus = $_GET['info']) {
    hopper_info(array('state' => 'info', 'focus' => $focus));
  } 

  // Provide info on how to access the hopper
  else hopper_info(array('state' => 'info', 'focus' => 'general'));

}

/**
 * @function
 * Provide a list of available feeds and info about them
 * @return
 *   A string containing HTML-formatted text.
 * ##++  Get a description of each feed and add it to the $feeds array.
 */
function hopper_feed_list() {
  // Get a list of available feeds
  $feeds = array();
  // Add the names of all non-hidden directories containing a .php file with the same name
  $feed_dir = dir('feeds');
  while (false !== ($entry = $feed_dir->read())) {
    // Exclude '.', '..', hidden files
    if (!preg_match('/^\./', $entry) && is_dir("feeds/$entry") && file_exists("feeds/$entry/$entry.php")) $feeds[] = $entry;
  }
  $feed_dir->close();

  return $feeds;
}

function info_list_feeds() {
  $feeds = hopper_feed_list();
  $feed_info = array();
  foreach ($feeds as $feed) {
    $feed_info[] = array('name' => $feed);
  }

  // Provide info on each feed in the feeds directory
  ob_start();
  include('info-feed-list.tpl.php');
  return ob_get_clean();
}

/**
 * @function
 * Generates output informing a consumer about how to access the hopper.
 */
function hopper_info($details) {
  $classes = array ();
  switch ($details['state']) {
  case 'error':
    $classes[] = "error";
    switch ($details['cause']) {
    case 'argument':
      $classes[] = "argument";
      switch ($details['problem']) {
      case 'missing':
        $classes[] = 'missing';
        $classes[] = $details['argument'];
        $message = "You can't access the Hopper without choosing a feed.<br />Available feeds:";
        $details = info_list_feeds();
        break;
      case 'invalid':
        $classes[] = 'invalid';
        $classes[] = $details['argument'];
        $message = 'The feed you selected, '.$details['feed'].", doesn't exist.<br />Available feeds:";
        $details = info_list_feeds();
        break;
      }
      break;
    }
    break;
  case 'info':
    $classes[] = 'info';
    switch ($details['focus']) {
    case 'feeds':
      $classes[] = 'feeds';
      $message = 'The Hopper provides the following feeds';
      $details = info_list_feeds();
      break;
    default:
      $classes[] = 'general';
      $message = 'General Information on The Hopper';
      $details = hopper_info_general();
      break;
    }
    break;
  }

  // Prepend 'hopper-' to each class name
  foreach ($classes as &$class) $class = 'hopper-'.$class;

  include('info.tpl.php');
}

/**
 * @function
 *  Provides general info about the Hopper.
 */
function hopper_info_general() {
  ob_start();
  include('info-general.tpl.php');
  return ob_get_clean();
}

?>
