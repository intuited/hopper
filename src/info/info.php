<?php

/**
 * @file
 *  Functions which provide information on the hopper and its various capacities
 */

/**
 * @function
 * Generates output informing a consumer about how to access the hopper.
 */
function info_print_info($details) {
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
      $details = info_general();
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
 *  Returns a string containing rendered HTML text giving general info about the Hopper.
 */
function info_general() {
  ob_start();
  include('info-general.tpl.php');
  return ob_get_clean();
}

/**
 * @function
 *  Return a string containing a rendered list of available feeds.
 */
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

