<?php

require_once($CFG->dirroot.'/lib/formslib.php');

class nzschoolssettings_form extends moodleform {

    function definition() {
        global $CFG;

        $mform =& $this->_form;

         // the upload manager is used directly in post precessing, moodleform::save_files() is not used yet
         //todo: replace this with Moodle 2 equivalent
//        $this->set_upload_manager(new upload_manager('logo', true, false, null, false, null, true, true));
        //$mform->addElement('url', 'logo', get_string('externalurl', 'extsearch'), array('size'=>'60'), array('usefilepicker'=>true));

        $mform->addElement('header', 'schooldetails', get_string('schooldetails', 'local_nzschools'));

        $mform->addElement('text', 'sitename', get_string('sitename', 'local_nzschools'), array('size'=>'48', 'onblur'=>'updateShortName(); '));
        $mform->setType('sitename', PARAM_TEXT);
        $mform->addRule('sitename', get_string('required'), 'required', null, 'client');
        $mform->addRule('sitename', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_nzschools'), 'size="10"');
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', get_string('required'), 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 20), 'maxlength', 20, 'client');

        $installprofiles = array('primary'      => get_string('primary', 'local_nzschools'),
                                 'secondary'    => get_string('secondary', 'local_nzschools'));

        $mform->addElement('select', 'installprofile', get_string('installprofile', 'local_nzschools'), $installprofiles);
        $mform->addRule('installprofile', get_string('required'), 'required', null, 'client');

        $years = range(1, 13);
        $yeargroup = array();
        $yeargroup[] =& $mform->createElement('select', 'fromyear', null, $years);
        $yeargroup[] =& $mform->createElement('static', 'to', null, get_string('to', 'local_nzschools'));
        $yeargroup[] =& $mform->createElement('select', 'toyear', null, $years);
        $mform->addGroup($yeargroup, 'yeargroup', get_string('years', 'local_nzschools'), '', false);
        $mform->setDefault('toyear', 12);
        $mform->addRule('yeargroup', get_string('required'), 'required', null, 'client');


        // Theme customisation
        $mform->addElement('header', 'colours', get_string('themecustomisation', 'local_nzschools'));
        // todo: This is apparently deprecated in Moodle 2.0
//        $mform->addElement('file', 'logo', get_string('logo', 'local_nzschools'));

        $mform->addElement('checkbox', 'plainbg', get_string('themeplainbg', 'local_nzschools'), get_string('themeplainbghelp', 'local_nzschools'), array('id' => 'plainbg'));

//        $mform->addElement('text', 'colour1', get_string('colour1', 'local_nzschools'), 'size="6"');
//        $mform->addRule('colour1', get_string('maximumchars', '', 6), 'maxlength', 6, 'client');
//
//        $mform->addElement('text', 'colour2', get_string('colour2', 'local_nzschools'), 'size="6"');
//        $mform->addRule('colour2', get_string('maximumchars', '', 6), 'maxlength', 6, 'client');
//
//        $mform->addElement('text', 'colour3', get_string('colour3', 'local_nzschools'), 'size="6"');
//        $mform->addRule('colour3', get_string('maximumchars', '', 6), 'maxlength', 6, 'client');

        // Options
        $mform->addElement('header', 'options', get_string('options', 'local_nzschools'));

        $mform->addElement('checkbox', 'createcats', get_string('createcategories', 'local_nzschools'));
        $mform->setDefault('createcats', true);


        $this->add_action_buttons();

    }
}
