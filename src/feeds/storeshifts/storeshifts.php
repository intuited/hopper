<?php
/**
 * @file
 * Produce a feed indicating whether upcoming store shifts are filled.
 */

function storeshifts_info() {
  $info  = '<div id="hopper-feed-storeshift-info">';
  $info .= '  Publish the filled or unfilled status of upcoming store shifts.';
  $info .= '  <div class="hopper-feed-info-options">';
  $info .= '    <div class="hopper-label">Options</div>';
  $info .= '    <div class="hopper-content"><ul>';
  $info .= '      <li>';
  $info .= '        <div class="hopper-label">date</div>';
  $info .= '        <div class="hopper-content">';
  $info .= '          if set to "starttime" (the default), each shift will have its lastUpdate field set to the start time of the shift.<br />';
  $info .= '          if set to "reverse-enumerated", the lastUpdate field of chronologically consecutive shifts will be set to decreasing values, so that they will be listed in the correct order when presented as blog posts.';
  $info .= '        </div>';
  $info .= '      </li>';
  $info .= '    </ul></div> <!-- /.hopper-content -->';
  $info .= '  </div> <!-- /.hopper-feed-info-options -->';
  $info .= '</div> <!-- /.hopper-feed-storeshift-info -->';
}

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
  $query = $gdataCal->newEventQuery();

  // Retrieve shifts whose start time is after the beginning of today
  $preg_match_iso_8601 = '/(\d+)-(\d+)-(\d+)T(\d+):(\d+):(\d+)([+-])(\d+):(\d+)/';
  $preg_replace_mask_time = '$1-$2-$3T00:00:00$7$8:$9';
  ##~~  trace( '$preg_match_iso_8601: '."$preg_match_iso_8601  " . '$preg_replace_mask_time: '."$preg_replace_mask_time\n" );
  ##~~  trace( '$startDate: '."$startDate\n" );
  $startDate = preg_replace($preg_match_iso_8601, $preg_replace_mask_time, date('c'));
  trace('$startDate: '.$startDate."\n");
  $query->setStartMin($startDate);
  $query->setRecurrenceExpansionStart($startDate);

  // ...and before the end of the seventh day from today
  $endDate = preg_replace($preg_match_iso_8601, $preg_replace_mask_time, date('c', time() + 3600*24*7));
  trace('$endDate: '.$endDate."\n");
  $query->setStartMax($endDate);
  $query->setRecurrenceExpansionEnd($endDate);

  // Sort them by start time
  ##::  this doesn't seem to work.
  $query->setOrderby('starttime');

  $query->setUser('default');
  $query->setVisibility('private');
  $query->setProjection('full');

  // http://code.google.com/apis/calendar/docs/2.0/reference.html#Parameters
  //   Setting this to true causes recurring events to be shown as single events.
  //     The resulting events will not have recurrence pseudo-properties.
  //   With it set to false, it's possible to iterate through recurrences of a recurring event via the ->when array.
  $query->setSingleEvents(false);
  $eventFeed = $gdataCal->getCalendarEventFeed($query);

  return $eventFeed;
}

/**
 * Return 'filled', 'unfilled', or NULL depending on whether $event represents an filled shift, unfilled shift, or non-shift.
 * An event is considered to be a filled shift if either
 *  - the following conditions are true (CASE 1):
 *    - It is a non-recurring event
 *    - It has an original event
 *  - or the following conditions are true (CASE 2):
 *    - It is a recurring event
 *    - Its text contains "CSA" but not ("name" and "number")
 * An event is considered to be an unfilled shift if the following conditions are true (CASE 3):
 *  - It is a recurring event
 *  - Its text contains "name" and "number"
 *  - there is no non-recurring event for which this is the original event.
 * Otherwise the event is considered to be a non-shift (CASE 4)
 * 
 * ##++ rewrite this algorithm so that it doesn't potentially check each event each time it needs the status of a recurring event.
 * ##!! add logic to check all expanded recurrences of a recurring event.
 */
function storeshifts_get_filled_shift_status($event, $eventFeed) {

  trace('$event->id: '.$event->id."\n");
  trace('  $event->recurrence: '.(bool)$event->recurrence."\n");
  ##~~  trace('  $event->originalEvent: ');
  ##~~  trace_export($event->originalEvent);
  ##~~  trace("\n");
  if ($event->originalEvent) {
    trace('  $event->originalEvent->href: '.$event->originalEvent->href."\n");
  }
  trace('  $event->title->text: '.$event->title->text."\n");
  trace('  $event->when[0]->startTime: '.$event->when[0]->startTime."\n");
  trace('  $event->when[0]->endTime:   '.$event->when[0]->endTime  ."\n");

  if ($event->recurrence) {
    trace('  $event->recurrence: true'."\n");
    if ( (stripos($event->title->text, 'name') !== FALSE) && (stripos($event->title->text, 'number') !== FALSE) ) {
      trace("  'name' and 'number' found in event title text.\n");
      // If there is no non-recurring event for which this is the original event
      foreach ($eventFeed as $specificEvent) {
        if (!$specificEvent->recurrence && ($specificEvent->originalEvent->href == $event->id->text)) {
          // CASE 1
          trace("  found a specific event whose original event is this one.\n");
          return 'filled';
        }
      }
      // CASE 3
      trace("  did not find a specific event whose original event is this one.\n");
      return 'unfilled';
    } else {
      if (stripos($event->title->text, 'CSA') !== FALSE) {
        // CASE 2
        trace("  'CSA' found in event title text.\n");
        return "filled";
      }
    }
    trace("  neither title text string matched.\n");
  } else {
    trace('  $event->recurrence: false'."\n");
    ##~~this is checked, less efficiently, in the section that returns case 3.
    ##~~  if ($event->originalEvent) {
    ##~~    // CASE 1
    ##~~    return 'filled';
    ##~~  }
  }
  // CASE 4
  return NULL;
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

  // If the date argument is set to 'starttime', then the lastUpdate field of entries will be set to the shift's start time.
  // The default is to enumerate them in reverse order so that they will appear in forward chronological order when interpreted as blog posts.
  if (array_key_exists('date', $_GET)) {
    $shift_lastupdate = $_GET['date'];
  }
  else $shift_lastupdate = 'starttime';

  foreach($calendarEventFeed as $event) {

    // Check whether or not the shift is filled
    // The criteria here is simply (along with the fact that it is a recurring event)
    //   that it contain the words "name" and "number".
    // passing the 'entry' property of the event feed will allow for sub-iterations of the entries
    //   because it passes the underlying array element rather than the iterator object.
    $filled_shift_status = storeshifts_get_filled_shift_status($event, $calendarEventFeed->entry);
    trace('  $filled_shift_status: '.$filled_shift_status."\n");

    if ($filled_shift_status) {
      // Convert the RFC 3339 formatted time into a numeric timestamp
      $start_time  = strtotime($event->when[0]->startTime);
      trace('  $start_time: '.date('c', $start_time)."\n");
      $finish_time = strtotime($event->when[0]->endTime);
      trace('  $finish_time: '.date('c', $finish_time)."\n");

      // Set up fields common to both filled and unfilled shifts

      // Set the entry timestamp
      $entry['lastUpdate'] = $start_time;

      // This should link to the event for those logged in to gmail with access to the calendar.
      // It is used to anchor the 'title' element.
      ##++find a way to translate the event ID, which is a Gdata feed URI, into a browseable URL that opens the event in a new tab/window
      ##++  $entry['link'] = $event->id;
      ##--for now just make the title of the RSS entry link to the main Google Calendar
      $entry['link'] = 'https://www.google.com/calendar/render?tab=mc';

      // Enclose everything in a div that gives the filled status
      $entry['description']  = '<div id="hopper-storeshifts-shift" class="hopper-' . $filled_shift_status . '">';

      // Set the time in the description field.
      $entry['description']  = '  <div id="hopper-storeshifts-time" class="hopper-event-time">';
      $entry['description'] .= '    <div class="hopper-label">time</div>';
      $entry['description'] .= '    <div class="hopper-value">';
      $entry['description'] .= '      <div class="hopper-date-date">' . date('l - M j Y', $start_time) . '</div>';
      $entry['description'] .= '      <div class="hopper-date-separator-date-time">: </div>';
      $entry['description'] .= '      <div class="hopper-date-time-from">' . date('g:i A', $start_time) . '</div>';
      $entry['description'] .= '      <div class="hopper-date-time-separator-from-to"> - </div>';
      $entry['description'] .= '      <div class="hopper-date-time-to">' . date('g:i A', $finish_time) . '</div>';
      $entry['description'] .= '    </div> <!-- /.hopper-value -->';
      $entry['description'] .= '  </div> <!-- /#hopper-storeshifts-time -->';

      // If this shift is unfilled
      if ($filled_shift_status == 'unfilled') {
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

  // Set each entry's lastUpdate field to reverse-enumerated timestamps.
  if ($shift_lastupdate == 'reverse-enumerated') {
    $last_update_enumeration = time();
    foreach ($entries as &$entry) {
      $entry['lastUpdate'] = $last_update_enumeration--;
    }
  }

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
