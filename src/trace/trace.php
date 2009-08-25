<?php
/**
 * @file
 *  defines functions enabling tracing.
 *  the startup and shutdown public methods happen via static functions; 
 *    however this is (at least for now) to no great advantage since there's no static trace function.
 *    creating one would require using *gasp* a global variable.
 *  Although the door has been left open for non-file-based tracing the Tracer class is file-specific.
 *  The file also declares a global instance of the Trace class, and a trace() function that uses it.
 *  If traces are attempted and the process does not have write access to the trace file or its directory does not exist,
 *    it is completely silent and not error or warning message is issued.
 */

class Tracer {
  var $target;

  /**
   * Set the target of traces.
   *  If the target file cannot be created or cannot be written to,
   *    traces will not be emitted.
   */
  function Tracer($target = NULL) {
    if ($target === NULL) {
      $target = 'traces/default.trace';
    }
    $this->target = self::init_target($target);

    // Set session completion to happen when the server request is terminating.
    register_shutdown_function(array($this, 'finish_target'));
  }

  /**
   * Check to see if traces can be emitted to the trace target,
   *  If so, initializes it and returns a verified value
   *  Otherwise returns NULL.
   */
  static function init_target($target) {
    // Translate a relative path to an absolute one.
    $target = preg_replace('/^[^\/].*/', getcwd().'/$0', $target);

    // Lambda-style rerouting of PHP error handling to ErrorException throws.
    set_error_handler(create_function('$a, $b, $c, $d', 'throw new ErrorException($b, 0, $a, $c, $d);'), E_ALL);
    try {
      file_put_contents($target, 
        "\n{===============\n"
        . "--trace started at ".time().": ".date('c')."--\n"
        ##~~  . "--cwd: ".getcwd()."--\n"
        ##~~  . "--target: ".$target."\n"
        . "----------------\n"
        , FILE_APPEND
      );
    } catch (ErrorException $e) {
      restore_error_handler();
      return NULL;
    }
    restore_error_handler();

    return $target;
  }

  /**
   * Calls print_r for each argument in succession.
   */
  function trace() {
    $args = func_get_args();

    ob_start();
    foreach ($args as $arg) {
      call_user_func('print_r', $arg);
    }
    file_put_contents($this->target, ob_get_clean(), FILE_APPEND);
  }

  /**
   * Emits a signal that the trace session is completing.
   */
  function finish_target() {
    self::trace_finish_target($this->target);
  }
  /* Static version of finish_target(). */
  static function trace_finish_target($target) {
    file_put_contents($target, 
      "----------------\n"
      . "--trace ended at ".time().": ".date('c')."--\n"
      . "===============}\n"
      , FILE_APPEND
    );
  }

}

global $tracer;
$tracer = new Tracer();

function trace() {
  global $tracer;
  $args = func_get_args();
  call_user_func_array(array($tracer, 'trace'), $args);
}
