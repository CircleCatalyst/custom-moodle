<?php

require_once('../config.php');
require_once($CFG->libdir.'/bennu/bennu.inc.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/calendar/import_form.php');
require_once($CFG->dirroot.'/calendar/lib.php');
require_once "{$CFG->dirroot}/local/importcalendar/lib.php";

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid));

if ($courseid && $courseid != SITEID) {
    require_login($courseid);
} else if ($CFG->forcelogin) {
    require_login();
}

$site = get_site();

$url = new moodle_url('/calendar/import.php');
if ($courseid !== 0) {
    $url->param('course', $courseid);
}
$PAGE->set_url($url);

//TODO: the courseid handling in /calendar/ is a bloody mess!!!
if ($courseid && $courseid != SITEID) {
    require_login($courseid);
} else if ($CFG->forcelogin) {
    $PAGE->set_context(get_context_instance(CONTEXT_SYSTEM)); //TODO: wrong
    require_login();
} else {
    $PAGE->set_context(get_context_instance(CONTEXT_SYSTEM)); //TODO: wrong
}

$now = usergetdate(time());
$pagetitle = get_string('importcalendar', 'calendar');
$strcalendar = get_string('calendar', 'calendar');
$link = calendar_get_link_href(new moodle_url(CALENDAR_URL.'view.php', array('view'=>'upcoming', 'course'=>$courseid)), $now['mday'], $now['mon'], $now['year']);
$PAGE->navbar->add($strcalendar, $link);
$PAGE->navbar->add($pagetitle, null);

// Print title and header
$PAGE->set_title("$site->shortname: $strcalendar: $pagetitle");
$PAGE->set_heading($COURSE->fullname);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo '<h1>'.get_string('importcalendar', 'calendar').'</h1>';

if (!calendar_user_can_add_event()) {
    print_error('nopermissions', 'error', '', 'Create Calendar Entires');
}

$mform = new calendar_import_form();
$data = new stdClass;
$data->courseid = $courseid;
$mform->set_data($data);

$allowed = new stdClass;
calendar_get_allowed_types($allowed);

$importform = new calendar_import_confirm_form();
if($data = $importform->get_data()) {

    $ical = new iCalendar();
    $ical->unserialize($data->calendar);
    echo importcalendar_import_icalendar_events($ical, $data->eventtypes['eventtype']);

    echo '<p><a href="'.calendar_get_link_href(CALENDAR_URL.'view.php?view=upcoming&amp;course='.$courseid.'&amp;', $now['mday'], $now['mon'], $now['year']).'">Back to Calendar.</a></p>';

} else {
    if ($formdata = $mform->get_data()) {
        $calendar = $mform->get_ical_data();

        $ical = new iCalendar();
        $ical->unserialize($calendar);

        if (!empty($ical->parser_errors)) {
            echo "<script type=\"text/javascript\"><!--
                  function showhide_parsererrors() {
                    errorlist = document.getElementById('bennu_parser_errors');
                    if (errorlist.style.display=='none') {
                        errorlist.style.display = 'block';
                    } else {
                        errorlist.style.display = 'none';
                    }
                  }
                  //--></script>\n";
            echo "<p>
                  <img alt=\"Bennu parser errors\" src=\"".$OUTPUT->pix_url('t/stop')."\" class=\"iconsmall\" onclick=\"showhide_parsererrors();\" />
                  Calendar iCal parser encountered errors, <a href=\"#\" onclick=\"showhide_parsererrors()\">click here to view them</a>.
                  </p>\n";
            echo "<ul id=\"bennu_parser_errors\" style=\"display:none\">\n";
            foreach ($ical->parser_errors as $error) {
                echo "<li class=\"bennu_parser_error\"> {$error} </li>\n";
            }
            echo '</ul>';
        }
        $table = new flexible_table('ical_import');
        $columns = array('summary', 'description', 'start', 'duration', 'uid');
        $headers = array('Summary', 'Description', 'Start', 'Duration', 'UUID');
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->setup();
        $count = 0;

        foreach($ical->components['VEVENT'] as $event) {
            if($count < 20) {
                $mevent = new stdClass;
                $mevent->name = $event->properties['SUMMARY'][0]->value;
                $mevent->description = $event->properties['DESCRIPTION'][0]->value;
                $mevent->timestart = strtotime($event->properties['DTSTART'][0]->value);
                $mevent->duration = strtotime($event->properties['DTEND'][0]->value) - $mevent->timestart;
                $mevent->uuid = $event->properties['UID'][0]->value;
                $mevent->timemodified = time();

                    $row = array();
                $row[] = $mevent->name;
                $row[] = $mevent->description;
                $row[] = date('d/m/Y H:i', $mevent->timestart);
                $row[] = date('H:i', $mevent->duration);
                $row[] = $mevent->uuid;
                $table->add_data($row);
            }
            $count++;

        }
        echo '<h2>Import Preview</h2>';
        $table->print_html();
        if($count > 20) {
            echo ($count-20).' more...';
        }
        $toform = new stdClass();
        $toform->courseid = $courseid;
        $toform->importfrom = $formdata->importfrom;
        if ($formdata->importfrom == CALENDAR_IMPORT_URL) {
            $toform->importurl  = $formdata->importurl;
        }
        $toform->calendar = $calendar;
        $importform->set_data($toform);
        $importform->display();

    }
    $mform->display();
}

echo $OUTPUT->footer();

