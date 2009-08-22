<?php
/**
 * @file
 * Generates a list of available feeds.
 * The variable $feeds contains a list of feeds.
 * The variable $feed_info is an array containing elements of the form
 *   'name' => the name of the feed
 * ##++  Output each feed's description.
 */
?>

<ul id="hopper-feed-list">
<?php foreach ($feed_info as $feed): ?>
  <li class="hopper-feed-list-item"><?php print $feed['name'] ?></li>
<?php endforeach ?>
</ul>

To access a feed, make it the value of the feed parameter in your request to the Hopper.<br />
For example, an rss feed for the status of our shift schedule is available at <?php print info_render_hopper_link('?feed=storeshifts&type=rss') ?>.<br />
