<?php

require_once '../config.php';
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/calendar/lib.php');

class calendar_import_form extends moodleform {

    /**
     * Defines the form elements
     */
    function definition() {
        $mform    =& $this->_form;
        $mform->addElement('header', 'importheader', 'Select iCal file to import:');
        $mform->addElement('filepicker', 'importfile', get_string('importcalendar', 'calendar'));
        $mform->addElement('hidden', 'courseid');

        $mform->addElement('submit', 'preview', 'Preview Import');
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

