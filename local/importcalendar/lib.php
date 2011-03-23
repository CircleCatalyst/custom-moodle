<?php

defined('MOODLE_INTERNAL') or die();
require_once "{$CFG->libdir}/formslib.php";

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

function importcalendar_show_subscriptions($courseid) {
    global $DB, $OUTPUT;

    $out = '';
    $str->update = get_string('update');
    $str->remove = get_string('remove');
    $str->add    = get_string('add');

    $out .= $OUTPUT->box_start('generalbox calendarsubs');
    $table = new html_table();
    $table->head  = array('Calendar', 'Poll', 'Actions');
    $table->align = array('left', 'left', 'center');
    $table->width = '100%';
    $table->data  = array();

    $subs = $DB->get_records('event_subscriptions', array('courseid' => $courseid));
    foreach ($subs as $id => $sub) {
        $actions =  "<input type=\"submit\" value=\"{$str->update}\" />";
        $actions .= "<input type=\"submit\" value=\"{$str->remove}\" />";
        $choices = importcalendar_get_pollinterval_choices();
        $pollinterval = $choices[$sub->pollinterval];
        $table->data[] = array("<a href=\"{$sub->url}\">{$sub->name}</a>", $pollinterval, $actions);
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
    $out .= $form->display();

    $out .= $OUTPUT->box_end();
    return $out;
}

function importcalendar_process_subscription_form($courseid) {
    return true;
}

