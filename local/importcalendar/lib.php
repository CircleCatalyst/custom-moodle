<?php

defined('MOODLE_INTERNAL') or die();
require_once "{$CFG->libdir}/formslib.php";
require_once "{$CFG->libdir}/bennu/bennu.inc.php";
require_once "{$CFG->dirroot}/calendar/lib.php";

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
 * Returns available options for the calendar event type.
 */
function importcalendar_get_eventtype_choices($courseid) {
    $choices = array();
    $allowed = new stdClass;
    calendar_get_allowed_types($allowed);

    if ($allowed->user) {
        $choices[0] = get_string('userevents', 'calendar');
    }
    if ($allowed->site) {
        $choices[1] = get_string('globalevents', 'calendar');
    }
    if (!empty($allowed->courses)) {
        $choices[$courseid] = get_string('courseevents', 'calendar');
    }
    if (!empty($allowed->groups) and is_array($allowed->groups)) {
        $choices['group'] = get_string('group');
    }

    return array($choices, $allowed->groups);
}

/**
 * Form for adding a subscription to a Moodle course calendar.
 */
class importcalendar_addsubscription_form extends moodleform {

    function definition() {
        $mform =& $this->_form;
        $courseid = optional_param('course', 0, PARAM_INT);

        // code to show/hide the form from the heading
        $mform->addElement('html', '<script type="text/javascript"><!--
          function showhide_subform() {
            divs = document.getElementById("addsubscriptionform").getElementsByTagName("div");
            for (var i = 0; i < divs.length; ++i) {
              if (divs[i].style.display=="none") {
                  divs[i].style.display = "block";
              } else {
                  divs[i].style.display = "none";
              }
            }
          }
        //--></script>');
        $mform->addElement('header', 'addsubscriptionform', '<a name="targetsubcriptionform" onclick="showhide_subform()">Import calendar...</a>');

        $mform->addElement('text', 'name', get_string('subscriptionname', 'local_importcalendar'), 'maxlength="255" size="40"');
        $mform->addRule('name', get_string('required'), 'required');

        $mform->addElement('html', "Please provide either a URL to a remote calendar, or upload a file.");
        $choices = array(CALENDAR_IMPORT_FILE => get_string('importcalendarfile', 'calendar'),
                         CALENDAR_IMPORT_URL  => get_string('importcalendarurl',  'calendar'));
        $mform->addElement('select', 'importfrom', get_string('importcalendarfrom', 'calendar'), $choices);
        $mform->setDefault('importfrom', CALENDAR_IMPORT_URL);

        $mform->addElement('text', 'url', get_string('calendarurl', 'local_importcalendar'), 'maxlength="255" size="50"');
        $mform->addElement('filepicker', 'importfile', get_string('importcalendarfile', 'calendar'));

        $mform->disabledIf('url',  'importfrom', 'eq', CALENDAR_IMPORT_FILE);
        $mform->disabledIf('importfile', 'importfrom', 'eq', CALENDAR_IMPORT_URL);

        $choices = importcalendar_get_pollinterval_choices();
        $mform->addElement('select', 'pollinterval', get_string('pollinterval', 'local_importcalendar'), $choices);

        $mform->setDefault('pollinterval', 604800);
        $mform->addHelpButton('pollinterval', 'pollinterval', 'local_importcalendar');

        // eventtype: 0 = user, 1 = global, anything else = course ID
        list($choices, $groups) = importcalendar_get_eventtype_choices($courseid);
        $mform->addElement('select', 'eventtype', get_string('eventkind', 'calendar'), $choices);
        $mform->addRule('eventtype', get_string('required'), 'required');

        if (!empty($groups) and is_array($groups)) {
            $groupoptions = array();
            foreach ($groups as $group) {
                $groupoptions[$group->id] = $group->name;
            }
            $mform->addElement('select', 'groupid', get_string('typegroup', 'calendar'), $groupoptions);
            $mform->disabledIf('groupid', 'eventtype', 'noteq', 'group');
        }

        $mform->addElement('hidden', 'course', optional_param('course', 0, PARAM_INT));
        $mform->addElement('hidden', 'view', optional_param('view', 'upcoming', PARAM_ALPHA));
        $mform->addElement('hidden', 'cal_d', optional_param('cal_d', 0, PARAM_INT));
        $mform->addElement('hidden', 'cal_m', optional_param('cal_m', 0, PARAM_INT));
        $mform->addElement('hidden', 'cal_y', optional_param('cal_y', 0, PARAM_INT));
        $mform->addElement('hidden', 'id', optional_param('id', 0, PARAM_INT));

        $mform->addElement('submit', 'add', get_string('add'));

        // *sigh* folding up the form breaks the filepicker control
        // $mform->addElement('html', '<script type="text/javascript">showhide_subform()</script>');
    }

    function get_ical_data() {
        $formdata = $this->get_data();
        switch ($formdata->importfrom) {
          case CALENDAR_IMPORT_FILE:
            $calendar = $this->get_file_content('importfile');
            break;
          case CALENDAR_IMPORT_URL:
            $calendar = file_get_contents($formdata->importurl);
            break;
        }
        return $calendar;
    }
}

/**
 * Add an iCalendar subscription to the database.
 * @param object $sub   The subscription object (e.g. from the form)
 * @return int          The insert ID, if any.
 */
function importcalendar_add_subscription($sub) {
    global $DB, $USER;
    $sub->courseid = $sub->eventtype;
    if ($sub->eventtype == 'group') {
        $sub->courseid = $sub->course;
    }
    $sub->userid = $USER->id;

    // file subscriptions never update.
    if (empty($sub->url)) {
        $sub->pollinterval = 0;
    }

    if (!empty($sub->name)) {
        if (empty($sub->id)) {
            $id = $DB->insert_record('event_subscriptions', $sub);
            return $id;
        } else {
            $DB->update_record('event_subscriptions', $sub);
            return $sub->id;
        }
    } else {
        print_error('error_badsubscription', 'importcalendar');
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

    // probably an unsupported X-MICROSOFT-CDO-BUSYSTATUS event.
    if (empty($event->properties['SUMMARY'])) {
        return 0;
    }

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

    // probably a repeating event with RRULE etc. TODO: skip for now
    if (empty($event->properties['DTSTART'][0]->value)) {
        return 0;
    }

    $eventrecord->timestart = strtotime($event->properties['DTSTART'][0]->value);
    if (empty($event->properties['DTEND'])) {
        $eventrecord->timeduration = 3600; // one hour if no end time specified
    } else {
        $eventrecord->timeduration = strtotime($event->properties['DTEND'][0]->value) - $eventrecord->timestart;
    }
    $eventrecord->uuid = $event->properties['UID'][0]->value;
    $eventrecord->timemodified = time();

    // Add the iCal subscription details if required
    if ($sub = $DB->get_record('event_subscriptions', array('id' => $subscriptionid))) {
        $eventrecord->subscriptionid = $subscriptionid;
        $eventrecord->userid = $sub->userid;
        $eventrecord->groupid = $sub->groupid;
        $eventrecord->courseid = $sub->courseid;
    } else {
        $eventrecord->userid = $USER->id;
        $eventrecord->groupid = 0; // TODO: ???
        $eventrecord->courseid = $courseid;
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
    global $DB, $OUTPUT, $CFG, $USER;

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

    $subs = $DB->get_records_sql('select * from {event_subscriptions}
                where courseid = :courseid
                   or (courseid = 0 and userid = :userid)',
                array('courseid' => $courseid, 'userid' => $USER->id));
    if (empty($subs)) {
        $c = new html_table_cell("No calendar subscriptions.");
        $c->colspan = 4;
        $table->data[] = new html_table_row(array($c));
    }
    foreach ($subs as $id => $sub) {
        $label = empty($sub->url) ? $sub->name : "<a href=\"{$sub->url}\">{$sub->name}</a>";
        $c_url = new html_table_cell($label);
        $lastupdated = empty($sub->lastupdated)
                ? get_string('never', 'local_importcalendar')
                : date('Y-m-d H:i:s', $sub->lastupdated);
        $c_updated = new html_table_cell($lastupdated);

        if (empty($sub->url)) {
            // don't update an iCal file, which has no URL.
            $pollinterval = '<input type="hidden" name="pollinterval" value="0" />';
        } else {
            // assemble pollinterval control
            $pollinterval = "<div style=\"float:left\">
                <select name=\"pollinterval\">\n";
            foreach (importcalendar_get_pollinterval_choices() as $k => $v) {
                $selected = ($k == $sub->pollinterval) ? ' selected' : '';
                $pollinterval .= "<option value=\"{$k}\"{$selected}>{$v}</option>\n";
            }
            $pollinterval .= "</select></div>";
        }

        // assemble form for the subscription row
        $rowform = "
            <form action=\"{$CFG->wwwroot}/calendar/view.php\" method=\"post\">
              {$pollinterval}
              <div style=\"float:right\">
                <input type=\"hidden\" name=\"sesskey\" value=\"{$sesskey}\" />
                <input type=\"hidden\" name=\"view\" value=\"{$view}\" />
                <input type=\"hidden\" name=\"course\" value=\"{$courseid}\" />
                <input type=\"hidden\" name=\"id\" value=\"{$sub->id}\" />
                " . (empty($sub->url)
                    ? ''
                    : "<input type=\"submit\" name=\"action\" value=\"{$str->update}\" />") . "
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

    // *sigh* there appears to be no function that returns Moodle Form HTML.
    ob_start();
    $form->display();
    $buffer = ob_get_contents();
    $out .= $buffer;
    ob_end_clean();

    $out .= $OUTPUT->box_end();
    return $out;
}

/**
 * Add a subscription from the form data and add its events to the calendar.
 * The form data will be either from the new subscription form, or from a form
 * on one of the rows in the existing subscriptions table.
 * @param int $courseid     The course ID
 * @return string           A log of the import progress, including errors
 */
function importcalendar_process_subscription_form($courseid) {
    global $DB;

    $form = new importcalendar_addsubscription_form();
    $formdata = $form->get_data();
    if (!empty($formdata)) {
        if (empty($formdata->url) and empty($formdata->importfile)) {
            print_error('error_requiredurlorfile', 'local_importcalendar');
        }
        if ($formdata->importfrom == CALENDAR_IMPORT_FILE) {
            // blank the URL if it's a file import
            $formdata->url = '';
            $subscriptionid = importcalendar_add_subscription($formdata);
            $calendar = $form->get_ical_data();
            $ical = new iCalendar();
            $ical->unserialize($calendar);
            return importcalendar_import_icalendar_events($ical, $courseid, $subscriptionid);
        } else {
            $subscriptionid = importcalendar_add_subscription($formdata);
            return importcalendar_update_subscription_events($subscriptionid);
        }
    } else {
        // process any subscription row form data
        return importcalendar_process_subscription_row();
    }
}

/**
 * Update a subscription from the form data in one of the rows in the existing
 * subscriptions table.
 * @return string           A log of the import progress, including errors
 */
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
        // skip updating file subscriptions
        if (empty($sub->url)) {
            break;
        }
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
 * @param integer $subscriptionid   The subscription ID
 * @return string                   A log of the import progress, including
 *                                  errors
 */
function importcalendar_import_icalendar_events($ical, $courseid, $subscriptionid=null) {
    global $DB;
    $return = '';
    $eventcount = 0;
    $updatecount = 0;

    // large calendars take a while...
    ini_set('max_execution_time', 300);

    // mark all events in a subscription with a zero timestamp
    if (!empty($subscriptionid)) {
        $sql = "update {event} set timemodified = :time where subscriptionid = :id";
        $DB->execute($sql, array('time' => 0, 'id' => $subscriptionid));
    }
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

    // delete remaining zero-marked events since they're not in remote calendar
    if (!empty($subscriptionid)) {
        $deletecount = $DB->count_records('event', array('timemodified' => 0, 'subscriptionid' => $subscriptionid));
        if (!empty($deletecount)) {
            $sql = "delete from {event} where timemodified = :time and subscriptionid = :id";
            $DB->execute($sql, array('time' => 0, 'id' => $subscriptionid));
            $return .= "<p> Events deleted: {$deletecount} </p>\n";
        }
    }

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

    $sub = $DB->get_record('event_subscriptions', array('id' => $subscriptionid));
    if (empty($sub)) {
        print_error('error_badsubscription', 'local_importcalendar');
    }
    // Don't update a file subscription. TODO: Update from a new uploaded file?
    if (empty($sub->url)) {
        return 'File subscription not updated.';
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

