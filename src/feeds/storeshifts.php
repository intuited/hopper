<?php
/**
 * @file
 * Produce a feed indicating whether upcoming store shifts are filled.
 */

/**
 * @function
 * Generate the feed to stdout.
 * Implements hook_produce_feed:
 *   Functions of the form *_produce_feed are called by hopper_router when their corresponding feed is requested by a consumer.
 *   They must be located in a file matching 'feeds/$feedname.php'
 */
function storeshifts_produce_feed() {
  $calendarEventFeed = storeshifts_get_calendar_event_feed();
  $entries = storeshifts_parse_event_feed($calendarEventFeed);
  storeshifts_generate_feed($entries);
}

/**
 * Connect to google and retrieve the grainery storeshifts calendar feed
 * @return
 *  a Zend_Gdata_Calendar_EventFeed containing the entries for the store shifts calendar for the Grainery
 */
function storeshifts_get_calendar_event_feed() {
  require_once 'Zend/Loader.php';
  Zend_Loader::loadClass('Zend_Gdata');
  Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
  Zend_Loader::loadClass('Zend_Gdata_Calendar');
  
  // set up authentication data
  // The auth file will set the $user and $pass variables.
  // This file is excluded from versioning since we're using a public repo.
  require('storeshifts-auth.php');
  $service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar
  $client = Zend_Gdata_ClientLogin::getHttpClient($user,$pass,$service);
  unset($user);  unset($pass);

  // Set up the calendar object; this is used for the calendar and event feed requests.
  $gdataCal = new Zend_Gdata_Calendar($client);

  // Set up a query to get event info for the default calendar
  // Check shifts between now and a week from now
  $startDate = date('c');
  $endDate = date('c', time() + 3600*24*7);
  $query = $gdataCal->newEventQuery();
  $query->setUser('default');
  $query->setVisibility('private');
  $query->setProjection('full');
  $query->setOrderby('starttime');
  $query->setStartMin($startDate);
  $query->setStartMax($endDate);
  $eventFeed = $gdataCal->getCalendarEventFeed($query);

  return $eventFeed;
}

/**
 * Parse the Google Calendar event feed and return an array of entries suitable for inclusion in Zend RSS feed object
 * @param calendarEventFeed a Zend_Gdata_Calendar_EventFeed containing the feed for store shifts
 * @return
 *  an array of entries
 * ##++ move the html rendering into a tpl.php file.
 */
function storeshifts_parse_event_feed($calendarEventFeed) {
  $entries = array();

  foreach($calendarEventFeed as $event) {
    if ($event->recurrence) {
      // Check whether or not the shift is filled
      $shift_filled = preg_match('/Grainery.*shift/i', $event->title->text);

      // Convert the RFC 3339 formatted time into a numeric timestamp
      $start_time  = strtotime($event->when[0]->startTime);
      $finish_time = strtotime($event->when[0]->endTime);

      // Set the entry timestamp
      $entry['lastUpdate'] = $start_time;

      // Set up fields common to both filled and unfilled shifts

      // This should link to the event for those logged in to gmail with access to the calendar.
      // It is used to anchor the 'title' element.
      $entry['link'] = $event->id;

      // Enclose everything in a div that gives the filled status
      $entry['description']  = '<div id="hopper-storeshifts-shift" class="hopper-' . $shift_filled? "filled" : "unfilled" . '">';

      // Set the time in the description field.
      $entry['description']  = '  <div id="hopper-storeshifts-time" class="hopper-event-time">';
      $entry['description'] .= '    <div class="hopper-label">time</div>';
      $entry['description'] .= '    <div class="hopper-value">';
      $entry['description'] .= '      <div class="hopper-date-date">' . date('l - M j Y') . '</div>';
      $entry['description'] .= '      <div class="hopper-date-separator-date-time">: </div>';
      $entry['description'] .= '      <div class="hopper-date-time-from">' . date('g:i A', $start_time) . '</div>';
      $entry['description'] .= '      <div class="hopper-date-time-separator-from-to"> - </div>';
      $entry['description'] .= '      <div class="hopper-date-time-to">' . date('g:i A', $finish_time) . '</div>';
      $entry['description'] .= '    </div> <!-- /.hopper-value -->';
      $entry['description'] .= '  </div> <!-- /#hopper-storeshifts-time -->';

      // If this shift is unfilled
      if ($shift_filled) {
        $entry['title'] = "unfilled shift - click to sign up";
        $entry['description'] .= '<div id="hopper-storeshifts-description" class="hopper-event-description">';
        $entry['description'] .= "  This shift hasn't been filled yet.  If you're logged in to gmail and have added the calendar to your account, click on the link above to sign up.";
        $entry['description'] .= '</div>';
      } 
      else {
        $entry['title'] = "filled shift - come on up!";
        $entry['description'] .= '  <div id="hopper-storeshifts-description" class="hopper-event-description">';
        $entry['description'] .= "    This shift is filled, so we should be open.<br />";
        $entry['description'] .= "    If you're logged in to gmail and have added the calendar to your account, click on the link above to get details on who's working.";
        $entry['description'] .= '  </div>';
      }

      // Close out the description field by closing the wrapper div
      $entry['description'] .= '</div>';

      $entries["$start_time"] = $entry;
    }
  }

  // Sort the array of shifts by timestamp
  ksort($entries);

  return $entries;
}


/**
 * Generate the feed using the retrieved store shift data.
 */
function storeshifts_generate_feed($entries) {
  // Set up the production feed
  require_once 'Zend/Feed.php';

  // create the input array describing the feed to create
  $array = array(
    'title' => 'The Grainery Hopper: Storeshifts feed',
    'link' => 'http://freecaine.ca/hopper',
    'description' => 'Description of the feed',
    'author' => 'The Hopper',
    'email' => 'graineryhopper@gmail.com',
    'charset' => 'utf-8',
    'lastUpdate' => time(),
    'entries' => $entries,
    /**
    'entries' => array(
      array(
        'title' => 'First article',
        'link' => 'http://www.example.com',
        'description' => 'First article description',
        'content' => 'First article <strong>content</strong>',
        'lastUpdate' => time()
      )
    )
    /**/
  );

  // create a Zend_Feed_Atom instance from the array
  // $feed = Zend_Feed::factory('atom', null, $array);

  // create a Zend_Feed_Rss instance from the array
  $feed = Zend_Feed::importArray($array, 'rss');

  // create a Zend_Feed_Rss instance with Zend_Feed_Interface
  // $feed = Zend_Feed::factory('rss', new MyInterface());

  // dump the feed to standard output
  // print $feed->saveXML();

  // send http headers and dump the feed
  $feed->send();
}
