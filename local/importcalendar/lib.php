<?php

defined('MOODLE_INTERNAL') or die();
require_once "{$CFG->libdir}/formslib.php";
require_once "{$CFG->libdir}/bennu/bennu.inc.php";

define('IMPORTCALENDAR_EVENT_UPDATED',  1);
define('IMPORTCALENDAR_EVENT_INSERTED', 2);

/**
 * Returns option list for the pollinterval setting.
 */
function importcalendar_get_pollinterval_choices() {
    return array(
            '0' => get_string('never', 'local_importcalendar'),
            '3600' => get_string('hourly', 'local_importcalendar'),
            '86400' => get_string('daily', 'local_importcalendar'),
            '604800' => get_string('weekly', 'local_importcalendar'),
            '2628000' => get_string('monthly', 'local_importcalendar'),
            '31536000' => get_string('annually', 'local_importcalendar'),
        );
}

/**
 * Form for adding a subscription to a Moodle course calendar.
 */
class importcalendar_addsubscription_form extends moodleform {

    function definition() {
        $mform =& $this->_form;

        // code to show/hide the form from the heading
        $mform->addElement('html', '<script type="text/javascript"><!--
          function showhide_subform() {
            divs = document.getElementById("addsubscriptionform").getElementsByTagName("div");
            for (var i in divs) {
              if (divs[i].style.display=="none") {
                  divs[i].style.display = "block";
              } else {
                  divs[i].style.display = "none";
              }
            }
          }
        //--></script>');
        $mform->addElement('header', 'addsubscriptionform', '<a name="targetsubcriptionform" onclick="showhide_subform()">Add new subscription...</a>');

        $mform->addElement('text', 'name', get_string('subscriptionname', 'local_importcalendar'), PARAM_URL);
        $mform->addRule('name', get_string('required'), 'required');

        $mform->addElement('text', 'url', get_string('calendarurl', 'local_importcalendar'), PARAM_URL);
        $mform->addRule('url', get_string('required'), 'required');

        $choices = importcalendar_get_pollinterval_choices();
        $mform->addElement('select', 'pollinterval', get_string('pollinterval', 'local_importcalendar'), $choices);

        $mform->setDefault('pollinterval', 604800);
        $mform->addHelpButton('pollinterval', 'pollinterval', 'local_importcalendar');

        $mform->addElement('hidden', 'course', optional_param('course', 0, PARAM_INT));
        $mform->addElement('hidden', 'view', optional_param('view', 'upcoming', PARAM_ALPHA));
        $mform->addElement('hidden', 'cal_d', optional_param('cal_d', 0, PARAM_INT));
        $mform->addElement('hidden', 'cal_m', optional_param('cal_m', 0, PARAM_INT));
        $mform->addElement('hidden', 'cal_y', optional_param('cal_y', 0, PARAM_INT));

        $mform->addElement('submit', 'add', get_string('add'));

        // fold up the form
        $mform->addElement('html', '<script type="text/javascript">showhide_subform()</script>');
    }

    function get_ical_data() {
        $formdata = $this->get_data();
        if (!empty($formdata->url)) {
            return file_get_contents($formdata->url);
        } else {
            return false;
        }
    }
}

/**
 * Add an iCalendar subscription to the database.
 * @param object $sub   The subscription object (e.g. from the form)
 * @return int          The insert ID, if any.
 */
function importcalendar_add_subscription($sub) {
    global $DB;
    if (empty($sub->courseid)) {
        $sub->courseid = $sub->course;
    }
    if (!empty($sub->name) and !empty($sub->url)) {
        $r = $DB->get_record('event_subscriptions', array('courseid' => $sub->courseid, 'url' => $sub->url));
        if (empty($r)) {
            $id = $DB->insert_record('event_subscriptions', $sub);
            return $id;
        } else {
            $sub->id = $r->id;
            $DB->update_record('event_subscriptions', $sub);
            return $r->id;
        }
    } else {
        return false;
    }
}

/**
 * Add an iCalendar event to the Moodle calendar.
 * @param object $event         The RFC-2445 iCalendar event
 * @param int $courseid         The course ID
 * @param int $subscriptionid   The iCalendar subscription ID
 * @return int                  Code: 1=updated, 2=inserted, 0=error
 */
function importcalendar_add_icalendar_event($event, $courseid, $subscriptionid=null) {
    global $DB, $USER;

    $eventrecord = new stdClass;

    $name = $event->properties['SUMMARY'][0]->value;
    $name = str_replace('\n', '<br />', $name);
    $name = str_replace('\\', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $eventrecord->name = clean_param($name, PARAM_CLEAN);

    if (empty($event->properties['DESCRIPTION'][0]->value)) {
        $description = '';
    } else {
        $description = $event->properties['DESCRIPTION'][0]->value;
        $description = str_replace('\n', '<br />', $description);
        $description = str_replace('\\', '', $description);
        $description = preg_replace('/\s+/', ' ', $description);
    }
    $eventrecord->description = clean_param($description, PARAM_CLEAN);

    $eventrecord->courseid = $courseid;
    $eventrecord->timestart = strtotime($event->properties['DTSTART'][0]->value);
    $eventrecord->timeduration = strtotime($event->properties['DTEND'][0]->value) - $eventrecord->timestart;
    $eventrecord->uuid = substr($event->properties['UID'][0]->value, 0, 36); // The UUID field only holds 36 characters.
    $eventrecord->userid = $USER->id;
    $eventrecord->timemodified = time();

    // Add the iCal subscription if required
    if (!empty($subscriptionid)) {
        $eventrecord->subscriptionid = $subscriptionid;
    }

    if ($updaterecord = $DB->get_record('event', array('uuid' => $eventrecord->uuid))) {
        $eventrecord->id = $updaterecord->id;
        if ($DB->update_record('event', $eventrecord)) {
            return IMPORTCALENDAR_EVENT_UPDATED;
        } else {
            return 0;
        }
    } else {
        if ($DB->insert_record('event', $eventrecord)) {
            return IMPORTCALENDAR_EVENT_INSERTED;
        } else {
            return 0;
        }
    }
}

/**
 * Create the list of iCalendar subscriptions for a course calendar.
 * @param int $courseid     The course ID
 * @return string           The table output.
 */
function importcalendar_show_subscriptions($courseid, $importresults='') {
    global $DB, $OUTPUT, $CFG;

    $view = optional_param('view', '', PARAM_ALPHA);
    $sesskey = sesskey();
    $out = '';

    $str->update = get_string('update');
    $str->remove = get_string('remove');
    $str->add    = get_string('add');

    $out .= $OUTPUT->box_start('generalbox calendarsubs');
    $out .= $importresults;

    $table = new html_table();
    $table->head  = array('Calendar', 'Last Updated', 'Poll', 'Actions');
    $table->align = array('left', 'left', 'left', 'center');
    $table->width = '100%';
    $table->data  = array();

    $subs = $DB->get_records('event_subscriptions', array('courseid' => $courseid));
    if (empty($subs)) {
        $c = new html_table_cell("No calendar subscriptions.");
        $c->colspan = 4;
        $table->data[] = new html_table_row(array($c));
    }
    foreach ($subs as $id => $sub) {
        $c_url = new html_table_cell("<a href=\"{$sub->url}\">{$sub->name}</a>");
        $lastupdated = empty($sub->lastupdated)
                ? get_string('never', 'local_importcalendar')
                : date('Y-m-d H:i:s', $sub->lastupdated);
        $c_updated = new html_table_cell($lastupdated);

        // assemble pollinterval control
        $pollinterval = "<div style=\"float:left\">
            <select name=\"pollinterval\">\n";
        foreach (importcalendar_get_pollinterval_choices() as $k => $v) {
            $selected = ($k == $sub->pollinterval) ? ' selected' : '';
            $pollinterval .= "<option value=\"{$k}\"{$selected}>{$v}</option>\n";
        }
        $pollinterval .= "</select></div>";

        // assemble form for the subscription row
        $rowform = "
            <form action=\"{$CFG->wwwroot}/calendar/view.php\" method=\"post\">
              {$pollinterval}
              <div style=\"float:right\">
                <input type=\"hidden\" name=\"sesskey\" value=\"{$sesskey}\" />
                <input type=\"hidden\" name=\"view\" value=\"{$view}\" />
                <input type=\"hidden\" name=\"course\" value=\"{$courseid}\" />
                <input type=\"hidden\" name=\"id\" value=\"{$sub->id}\" />
                <input type=\"submit\" name=\"action\" value=\"{$str->update}\" />
                <input type=\"submit\" name=\"action\" value=\"{$str->remove}\" />
              </div>
            </form>";
        $c_form = new html_table_cell($rowform);
        $c_form->colspan = 2;
        $table->data[] = new html_table_row(array($c_url, $c_updated, $c_form));
    }
    $out .= html_writer::table($table);

    // form for adding a new subscription
    $form = new importcalendar_addsubscription_form();
    $formdata = $form->get_data();
    if (empty($formdata)) {
        $formdata = new stdClass;
        $formdata->course = $courseid;
        $form->set_data($formdata);
    }
    $out .= $form->display(); // TODO: grab the output somehow.

    $out .= $OUTPUT->box_end();
    return $out;
}

/**
 * Add a subscription from the form data and add its events to the calendar.
 * @param int $courseid     The course ID
 * @return string           A log of the import progress, including errors
 */
function importcalendar_process_subscription_form($courseid) {
    global $DB;

    $form = new importcalendar_addsubscription_form();
    $formdata = $form->get_data();
    if (!empty($formdata)) {
        $subscriptionid = importcalendar_add_subscription($formdata);
        return importcalendar_update_subscription_events($subscriptionid);
    } else {
        // process any subscription row form data
        return importcalendar_process_subscription_row();
    }
}

function importcalendar_process_subscription_row() {
    global $DB;

    $id             = optional_param('id', 0, PARAM_INT);
    $courseid       = optional_param('course', 0, PARAM_INT);
    $pollinterval   = optional_param('pollinterval', 0, PARAM_INT);
    $action         = optional_param('action', '', PARAM_ALPHA);

    if (empty($id)) {
        return '';
    }

    $str->update = get_string('update');
    $str->remove = get_string('remove');

    // update or remove the subscription, based on action.
    $sub = $DB->get_record('event_subscriptions', array('id' => $id), '*', MUST_EXIST);
    switch ($action) {
      case $str->update:
        $sub->pollinterval = $pollinterval;
        $DB->update_record('event_subscriptions', $sub);

        // update the events
        return "<p>Calendar subscription '{$sub->name}' updated.</p>" . importcalendar_update_subscription_events($id);
        break;

      case $str->remove:
        $sesskey = required_param('sesskey', PARAM_ALPHANUM);
        $DB->delete_records('event', array('subscriptionid' => $id));
        $DB->delete_records('event_subscriptions', array('id' => $id));
        return "Calendar subscription '{$sub->name}' removed.";
        break;

      default:
        break;
    }
    return '';
}

/**
 * From a URL, fetch the calendar and return an iCalendar object.
 * @param string $url   The iCalendar URL
 * @return object       The iCalendar object
 */
function importcalendar_get_icalendar($url) {
    $calendar = file_get_contents($url);
    $ical = new iCalendar();
    $ical->unserialize($calendar);
    return $ical;
}

/**
 * Import events from an iCalendar object into a course calendar.
 * @param object  $ical             The iCalendar object
 * @param integer $courseid         The course ID for the calendar
 * @param integer $subscriptionid   The course ID for the calendar
 * @return string                   A log of the import progress, including
 *                                  errors
 */
function importcalendar_import_icalendar_events($ical, $courseid, $subscriptionid=null) {
    $return = '';
    $eventcount = 0;
    $updatecount = 0;

    foreach($ical->components['VEVENT'] as $event) {
        $res = importcalendar_add_icalendar_event($event, $courseid, $subscriptionid);
        switch ($res) {
          case IMPORTCALENDAR_EVENT_UPDATED:
            $updatecount++;
            break;
          case IMPORTCALENDAR_EVENT_INSERTED:
            $eventcount++;
            break;
          case 0:
            $return .= '<p>Failed to add event: '.$event->properties['SUMMARY'][0]->value." </p>\n";
            break;
        }
    }
    $return .= "<p> Events imported: {$eventcount} </p>\n";
    $return .= "<p> Events updated: {$updatecount} </p>\n";
    return $return;
}

/**
 * Fetch a calendar subscription and update the events in the calendar.
 * @param integer $subscriptionid   The course ID for the calendar
 * @return string                   A log of the import progress, including
 *                                  errors
 */
function importcalendar_update_subscription_events($subscriptionid) {
    global $DB;

    $return = '';
    $eventcount = 0;
    $updatecount = 0;

    $sub = $DB->get_record('event_subscriptions', array('id' => $subscriptionid));
    if (empty($sub)) {
        print_error('error_badsubscription', 'local_importcalendar');
    }
    $ical = importcalendar_get_icalendar($sub->url);
    $return = importcalendar_import_icalendar_events($ical, $sub->courseid, $subscriptionid);
    $sub->lastupdated = time();
    $DB->update_record('event_subscriptions', $sub);
    return $return;
}

/**
 * Update calendar subscriptions.
 */
function local_importcalendar_cron() {
    global $DB;
    mtrace("Updating calendar subscriptions:");
    $time = time();
    foreach ($DB->get_records_sql('select * from {event_subscriptions}
                where pollinterval > 0 and lastupdated + pollinterval < ?', array($time)) as $sub) {
        mtrace("   Updating calendar subscription '{$sub->name}' in course {$sub->courseid}");
        $log = importcalendar_update_subscription_events($sub->id);
        mtrace(trim(strip_tags($log)));
    }
    mtrace("Finished updating calendar subscriptions.");
    return true;
}

