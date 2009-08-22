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
 * Return an array containing a list of available feeds
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

/**
 * @function
 *  Calls the info routine to render informational output on the Hopper
 */
function hopper_info($details) {
  include_once('info/info.php');
  info_print_info($details);
}

?>
