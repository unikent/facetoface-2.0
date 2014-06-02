<?php

require_once '../../config.php';
require_once 'lib.php';

global $DB;

$id = required_param('id', PARAM_INT); // Course Module ID

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('error:coursemisconfigured', 'facetoface');
}

require_course_login($course);
$context = context_course::instance($course->id);
require_capability('mod/facetoface:view', $context);

$event = \mod_facetoface\event\course_module_instance_list_viewed::create(array(
    'objectid' => $course->id,
    'context' => \course_context::instance($course->id)
));
$event->trigger();

$strfacetofaces = get_string('modulenameplural', 'facetoface');
$strfacetoface = get_string('modulename', 'facetoface');
$strfacetofacename = get_string('facetofacename', 'facetoface');
$strweek = get_string('week');
$strtopic = get_string('topic');
$strcourse = get_string('course');
$strname = get_string('name');

$pagetitle = format_string($strfacetofaces);

$PAGE->set_url('/mod/facetoface/index.php', array('id' => $id));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

if (!$facetofaces = get_all_instances_in_course('facetoface', $course)) {
    notice(get_string('nofacetofaces', 'facetoface'), "../../course/view.php?id=$course->id");
    die;
}

$timenow = time();

$table = new html_table();
$table->width = '100%';

if ($course->format == 'weeks' && has_capability('mod/facetoface:viewattendees', $context)) {
    $table->head  = array ($strweek, $strfacetofacename, get_string('sign-ups', 'facetoface'));
    $table->align = array ('center', 'left', 'center');
}
elseif ($course->format == 'weeks') {
    $table->head  = array ($strweek, $strfacetofacename);
    $table->align = array ('center', 'left', 'center', 'center');
}
elseif ($course->format == 'topics' && has_capability('mod/facetoface:viewattendees', $context)) {
    $table->head  = array ($strcourse, $strfacetofacename, get_string('sign-ups', 'facetoface'));
    $table->align = array ('center', 'left', 'center');
}
elseif ($course->format == 'topics') {
    $table->head  = array ($strcourse, $strfacetofacename);
    $table->align = array ('center', 'left', 'center', 'center');
}
else {
    $table->head  = array ($strfacetofacename);
    $table->align = array ('left', 'left');
}

$currentsection = '';

foreach ($facetofaces as $facetoface) {

    $submitted = get_string('no');

    if (!$facetoface->visible) {
        //Show dimmed if the mod is hidden
        $link = html_writer::link("view.php?f=$facetoface->id", $facetoface->name, array('class' => 'dimmed'));
    }
    else {
        //Show normal if the mod is visible
        $link = html_writer::link("view.php?f=$facetoface->id", $facetoface->name);
    }

    $printsection = '';
    if ($facetoface->section !== $currentsection) {
        if ($facetoface->section) {
            $printsection = $facetoface->section;
        }
        $currentsection = $facetoface->section;
    }

    $totalsignupcount = 0;
    if ($sessions = facetoface_get_sessions($facetoface->id)) {
        foreach ($sessions as $session) {
            if (!facetoface_has_session_started($session, $timenow)) {
                $signupcount = facetoface_get_num_attendees($session->id);
                $totalsignupcount += $signupcount;
            }
        }
    }
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $courselink = html_writer::link($url, $course->shortname, array('title' => $course->shortname));
    if ($course->format == 'weeks' or $course->format == 'topics') {
        if (has_capability('mod/facetoface:viewattendees', $context)) {
            $table->data[] = array ($courselink, $link, $totalsignupcount);
        }
        else {
            $table->data[] = array ($courselink, $link);
        }
    }
    else {
        $table->data[] = array ($link, $submitted);
    }
}

echo html_writer::empty_tag('br');

echo html_writer::table($table);
echo $OUTPUT->footer($course);
