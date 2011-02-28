<?php

defined('MOODLE_INTERNAL') or die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/calendar/lib.php');

class calendar_import_form extends moodleform {

    /**
     * Defines the form elements
     */
    function definition() {
        $mform    =& $this->_form;
        $mform->addElement('header', 'importheader', 'Select iCal to import:');

        $choices = array(CALENDAR_IMPORT_FILE => get_string('importcalendarfile', 'calendar'),
                         CALENDAR_IMPORT_URL  => get_string('importcalendarurl',  'calendar'));
        $mform->addElement('select', 'importfrom', get_string('importcalendarfrom', 'calendar'), $choices);
        $mform->setDefault('importfrom', CALENDAR_IMPORT_FILE);

        $mform->addElement('filepicker', 'importfile', get_string('importcalendarfile', 'calendar'));
        $mform->addElement('text', 'importurl', get_string('importcalendarurl', 'calendar'), PARAM_URL);
        $mform->addElement('hidden', 'courseid');
        $mform->disabledIf('importurl',  'importfrom', 'eq', CALENDAR_IMPORT_FILE);
        $mform->disabledIf('importfile', 'importfrom', 'eq', CALENDAR_IMPORT_URL);

        $mform->addElement('submit', 'preview', 'Preview Import');
    }

    function get_ical_data() {
        $formdata = $this->get_data();
        switch ($formdata->importfrom) {
          case CALENDAR_IMPORT_FILE:
            $calendar = $formdata->get_file_content('importfile');
            break;
          case CALENDAR_IMPORT_URL:
            $calendar = file_get_contents($formdata->importurl);
            break;
        }
        return $calendar;
    }

}

class calendar_import_confirm_form extends moodleform {

    /**
     * Defines the form elements
     */
    function definition() {
        global $allowed, $courseid;
        $mform    =& $this->_form;
        $mform->addElement('header', 'confirmheader', 'Import these events as:');
        $mform->addElement('hidden', 'calendar');
        $mform->addElement('hidden', 'courseid');
        $mform->addElement('hidden', 'importfrom');
        $mform->addElement('hidden', 'importurl');
        $radio = array();

        if($allowed->site) {
            $radio[] = &MoodleQuickForm::createElement('radio', 'eventtype', '', get_string('globalevents', 'calendar'), 1);
        }
        if($allowed->courses) {
            $radio[] = &MoodleQuickForm::createElement('radio', 'eventtype', '', get_string('courseevents', 'calendar'), $courseid);
        }
        if($allowed->user){
            $radio[] = &MoodleQuickForm::createElement('radio', 'eventtype', '', get_string('userevents', 'calendar'), 0);
        }
        $mform->addGroup($radio, 'eventtypes', get_string('eventkind', 'calendar'));

        $mform->addElement('submit', 'import', 'Import Events');
    }
}

