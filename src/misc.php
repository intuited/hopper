<?php
/**
 * @file
 * Various support functions integral to the operation of the Hopper
 */

/**
 * Locate the Zend framework and add it to the include path.
 */
function find_zend() {
  $foundZend = false;
  foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {

    // In this case the Zend Framework is already in the include path
    if (file_exists("$path/Zend/Loader.php")) {
      $foundZend = true;
      break;
    }

    // Check each subdirectory for the Zend Framework.
    $d = dir($path);
    while (false !== ($entry = $d->read())) {
      if (is_dir("$path/$entry") && file_exists("$path/$entry/Zend/Loader.php")) {
        // If we find it, add the directory to the include path and break.
        $foundZend = true;
        set_include_path(get_include_path() . PATH_SEPARATOR . "$path/$entry");
        $d->close();
        break 2;
      }
    }
    $d->close();
  }

  // If we didn't find the Zend Framework in the php path, use the local version.
  if (!$foundZend) {
    $gdata_path = '../lib/ZendGdata-1.9.1/library';
    set_include_path(get_include_path() . PATH_SEPARATOR . $gdata_path);
  }
}

/**
 *  Convert a time as retrieved by 
 *    $gdataCal = new Zend_Gdata_Calendar($client);
 *    // create $query
 *    $eventFeed = $gdataCal->getCalendarEventFeed($query);
 *    foreach($eventFeed as $event) {
 *      $entry['lastUpdate'] = $event->when->startTime;
 *  to a UNIX timestamp.
 *  These dates have the format 2009-08-18T16:30:00.000-03:00
 *  For some reason there doesn't seem to be an API function that does this.
 *  If no date is given, returns the current time.
 */

function GDataDateToTimestamp($date) {
  if (!$date) return time();

  if (!preg_match('/([0-9]+)-([0-9]+)-([0-9]+)T([0-9]+):([0-9]+):([0-9]+).([0-9]+)([-+])([0-9]+):([0-9]+)/', $date, $matches)) {
    // If the given date is unrecognizable, throw an exception.
    throw new Exception('GDataDateToTimestamp: given date did not match the regexp.  $date: "'.$date.'"');
    return NULL;
  }

  // Parse the date format
  $date = array(
    'year' => $matches[1],
    'month' => $matches[2],
    'date' => $matches[3],
    'hour' => $matches[4],
    'minute' => $matches[5],
    'second' => $matches[6],
    'ms' => $matches[7],
    'plusminus' => $matches[8],
    'hourdiff' => $matches[9],
    'minutediff' => $matches[10],
  );

  // mktime's parameters: ( [int hour [, int minute [, int second [, int month [, int day [, int year [, int is_dst]]]]]]] 
  $timestamp = mktime($date['hour'], $date['minute'], $date['second'], $date['month'], $date['date'], $date['year'], false);

  // do the math
  if ($date['plusminus'] == '+') {
    $timestamp += 3600 * $date['hourdiff'];
    $timestamp += 60 * $date['minutediff'];
  } else {
    $timestamp -= 3600 * $date['hourdiff'];
    $timestamp -= 60 * $date['minutediff'];
  }

  return $timestamp;
}
