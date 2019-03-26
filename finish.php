<?php
/**
 * Page to record the 'end' state.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);
$globalscattertime = optional_param('globalscattertime', 0, PARAM_INT);
$localscattertime = optional_param('localscattertime', 0, PARAM_INT);

$mod = mod_wordcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();
$currentstate = mod_wordcards_module::STATE_END;

require_login($course, true, $cm);
require_sesskey();
$mod->require_view();
$mod->resume_progress($currentstate);

$pagetitle = get_string('activitycompleted', 'mod_wordcards');

$PAGE->set_url('/mod/wordcards/finish.php', ['id' => $cmid]);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

$output = $PAGE->get_renderer('mod_wordcards');

echo $output->header();
echo $output->heading($pagetitle);

echo $output->navigation($mod, $currentstate);

$renderer = $PAGE->get_renderer('mod_wordcards');
echo $renderer->finish_page($mod, $globalscattertime, $localscattertime);

echo $output->footer();
