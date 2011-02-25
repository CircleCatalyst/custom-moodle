<?php

require_once('../config.php');
require_once($CFG->libdir.'/bennu/bennu.inc.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/calendar/import_form.php');
require_once($CFG->dirroot.'/calendar/lib.php');

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

if (calendar_user_can_add_event()) {

    $mform = new calendar_import_form();
    $data = new stdClass;
    $data->courseid = $courseid;
    $mform->set_data($data);

    $allowed = new stdClass;
    calendar_get_allowed_types($allowed);

    $importform = new calendar_import_confirm_form();
    if($data = $importform->get_data()) {

        $ical = new iCalendar;
        $ical->unserialize($data->calendar);
        $eventcount = 0;
        $updatecount = 0;

        foreach($ical->components['VEVENT'] as $event) {
            $eventrecord = new stdClass;

            $name = $event->properties['SUMMARY'][0]->value;
            $name = str_replace('\n', '<br />', $name);
            $name = str_replace('\\', '', $name);
            $name = preg_replace('/\s+/', ' ', $name);
            $eventrecord->name = clean_param($name, PARAM_CLEAN);

            $description = $event->properties['DESCRIPTION'][0]->value;
            $description = str_replace('\n', '<br />', $description);
            $description = str_replace('\\', '', $description);
            $description = preg_replace('/\s+/', ' ', $description);
            $eventrecord->description = clean_param($description, PARAM_CLEAN);

            $eventrecord->courseid = $data->eventtypes['eventtype'];
            $eventrecord->timestart = strtotime($event->properties['DTSTART'][0]->value);
            $eventrecord->timeduration = strtotime($event->properties['DTEND'][0]->value) - $eventrecord->timestart;
            $eventrecord->uuid = substr($event->properties['UID'][0]->value, 0, 36); // The UUID field only holds 36 characters.
            $eventrecord->userid = $USER->id;
            $eventrecord->timemodified = time();

            if ($updaterecord = $DB->get_record('event', array('uuid' => $eventrecord->uuid))) {
                $eventrecord->id = $updaterecord->id;
                if ($DB->update_record('event', $eventrecord)) {
                    $updatecount++;
                } else {
                    echo '<p>Failed to update event: '.$eventrecord->name.' '.date('H:i d/m/Y', $eventrecord->timestart).'</p>';
                }
            } else {
                if ($DB->insert_record('event', $eventrecord)) {
                    $eventcount++;
                } else {
                    echo '<p>Failed to add event: '.$eventrecord->name.' '.date('H:i d/m/Y', $eventrecord->timestart).'</p>';
                }
            }
        }
        echo '<p>'.$eventcount.' events imported successfully.</p>';
        echo '<p>'.$updatecount.' events updated.</p>';
        echo '<p><a href="'.calendar_get_link_href(CALENDAR_URL.'view.php?view=upcoming&amp;course='.$courseid.'&amp;', $now['mday'], $now['mon'], $now['year']).'">Back to Calendar.</a></p>';

    } else {
        if ($formdata = $mform->get_data()) {
            var_dump($formdata->importfile);
            $calendar = $mform->get_file_content('importfile');

            $ical = new iCalendar;
            $ical->unserialize($calendar);

            echo '<p>';
            foreach ($ical->parser_errors as $error) {
                echo $error.'<br />';
            }
            echo '</p>';

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
            $toform->calendar = $calendar;
            $importform->set_data($toform);
            $importform->display();

        }
        $mform->display();
    }

} else {
    print_error('nopermissions', 'error', '', 'Create Calendar Entires');
}

print_footer();

