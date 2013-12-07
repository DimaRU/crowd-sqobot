<?php namespace Sqobot;

class TaskWebcron extends Task {
  public $title = 'Cron Jobs';

  static function splitArgv($cl) {
    $argv = array();
    $joiner = null;

    foreach (explode(' ', $cl) as $part) {
      if ($joiner) {
        if ($part !== '' and substr($part, -1) === $joiner) {
          $joiner = null;
          $argv[count($argv) - 1] .= ' '.substr($part, 0, -1);
        }
      } elseif ($part === '""' or $part === "''") {
        $argv[] = '';
      } elseif ($part === '') {
        // skip.
      } elseif ($tail = strpbrk($part, '"\'')) {
        $argv[] = str_replace($tail[0], '', $part, $count);
        $count % 2 == 1 and $joiner = $tail[0];
      } else {
        $argv[] = $part;
      }
    }

    return $argv;
  }

  static function mailTo($addr, $subject, $body) {
    $host = Web::info('HTTP_HOST') ?: dirname(S::first(get_included_files()));
/*
    $mail = new \MiMeil($addr, "$subject — Sqobot at $host");
    $mail->from = cfg('mailFrom');
    $mail->body('html', Web::template('mail', compact('body')));
    $mail->send() or warn("Problem sending e-mail to $addr (\"$subject\").");
 */
  }

  function do_(array $args = null) {
    $tasks = S(cfgGroup('webcron'), function ($cl, $name) {
      @list($action, $query) = explode('?', taskURL('cron-exec', compact('name')), 2);

      $button = HLEx::tag('form', compact('action')).
                  HLEx::hiddens($query).
                  HLEx::button_q($name, array('type' => 'submit')).
                '</form>';

      return HLEx::tr(HLEx::th($button).HLEx::td(HLEx::kbd_q($cl)));
    });

    if ($tasks) {
      $tasks = HLEx::table(join($tasks), 'tasks');
    } else {
      $tasks = HLEx::p('No tasks defined with a <b>webcron</b> setting.', 'none');
    }
    echo $tasks;
  }

  function do_exec(array $args = null) {
    $name = &$args['name'];
    $name or Web::quit(400, HLEx::p('The <b>name</b> parameter is missing.'));

    Web::can("cron-$name") or Web::deny("cron task $name.");

    $cl = cfg("webcron $name");
    $cl or Web::quit(404, HLEx::p('No '.HLEx::b_q("webcron $name").' setting.'));

    Core::$cl = S::parseCL(static::splitArgv($cl), true);

    Core::$cl['index'] += array('', '');
    $task = array_shift(Core::$cl['index']);
    $method = array_shift(Core::$cl['index']);

    echo HLEx::p(HLEx::kbd_q("# $task $method"));

    try {
      $obj = Task::make($task);
      $output = $obj->capture($method, Core::$cl['options']);
      Core::$cl = null;
    } catch (ENoTask $e) {
      Web::quit(400, HLEx::p_q($e->getMessage()));
    } catch (\Exception $e) {
      Web::quit(500, HLEx::p_q('Exception '.exLine($e)));
    }

    echo HLEx::pre_q(trim($output, "\r\n"), 'gen output');
  }


}