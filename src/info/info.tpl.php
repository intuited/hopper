<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
  <head><title>The Hopper</title></head>
  <body>
    <div class="hopper-header">
      The Hopper
    </div>
    <div id="hopper-info" class="<?php print implode(' ', $classes) ?>">
      <div id="hopper-info-message"><?php print $message ?></div>
      <div id="hopper-info-details"><?php print $details ?></div>
    </div>
    <div class="hopper-footer">
      For general info on the Hopper, access <?php print info_render_hopper_link() ?>.<br />
      Server space for the Hopper is graciously provided by <a href="http://freecaine.ca">the FreeCAINE community wireless project</a>.<br />
      The Hopper is copyright 2009 Ted Tibbetts and licensed freely under the <a href="http://www.gnu.org/copyleft/gpl.html">GNU Public License</a>.<br />
    </div>
  </body>
</html>
