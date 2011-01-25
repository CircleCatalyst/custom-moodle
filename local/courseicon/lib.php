<?php

/**
 *

 * @param $mform MoodleQuickForm
 * @param $course stdClass
 * @return void
 */
function local_courseicon_course_form_definition($mform, $course){
    global $CFG, $PAGE;
    $mform->addElement('header','',get_string('courseicon','local_courseicon'));
    $mform->addElement('static','currenticon',get_string('currenticon','local_courseicon'), local_courseicon_course_icon_tag($course));
    $mform->addElement('select','icon',get_string('icon','local_courseicon'), local_courseicon_get_stock_icons('course'));

// todo: Javascript to make the icon on the form update automatically so the user can preview it
//    $iconel = $mform->getElement('icon');
//    $iconel->updateAttributes(array('onchange'=>'previewCourseIcon(); '));
//    $PAGE->requires->js('/local/courseicon/icons.js.php');

// todo: picker for custom icon
//    $filemanager_options = array();
//    // 3 == FILE_EXTERNAL & FILE_INTERNAL
//    // These two constant names are defined in repository/lib.php
//    $filemanager_options['return_types'] = 3;
//    $filemanager_options['accepted_types'] = 'web_image';
//    $filemanager_options['maxbytes'] = get_max_upload_file_size($CFG->maxbytes);
//    $mform->addElement('filepicker', 'uploadicon', get_string('uploadicon', 'local_courseicon'), null, $filemanager_options);
//

//    $mform->disabledIf('uploadicon','icon','neq','custom');
}

/**
 *

 * @param $mform MoodleQuickForm
 * @param $category stdClass
 * @return void
 */
function local_courseicon_category_form_definition($mform, $category){
    global $CFG, $PAGE;
    $mform->addElement('static','currenticon',get_string('currenticon','local_courseicon'), local_courseicon_category_icon_tag($category));
    $mform->addElement('select','icon',get_string('icon','local_courseicon'), local_courseicon_get_stock_icons('coursecategory'));

// todo: Javascript to make the icon on the form update automatically so the user can preview it
//    $iconel = $mform->getElement('icon');
//    $iconel->updateAttributes(array('onchange'=>'previewCourseIcon(); '));
//    $PAGE->requires->js('/local/courseicon/icons.js.php');

// todo: picker for custom icon
//    $filemanager_options = array();
//    // 3 == FILE_EXTERNAL & FILE_INTERNAL
//    // These two constant names are defined in repository/lib.php
//    $filemanager_options['return_types'] = 3;
//    $filemanager_options['accepted_types'] = 'web_image';
//    $filemanager_options['maxbytes'] = get_max_upload_file_size($CFG->maxbytes);
//    $mform->addElement('filepicker', 'uploadicon', get_string('uploadicon', 'local_courseicon'), null, $filemanager_options);
//

//    $mform->disabledIf('uploadicon','icon','neq','custom');
}

/**
 * Return img tag to a course icon
 *
 * @param object $course Course object
 * @param string $size Size of icon set to use
 * @global $CFG
 * @global $COURSE
 * @return string img tag
 */
function local_courseicon_course_icon_tag($course=null, $size='large') {
    global $CFG, $COURSE, $DB;
    if (!isset($course)) {
        $course = $COURSE;
    }
    // Get the course icon if it's not set
    if (!isset($course->icon)) {
        if (isset($course->id)){
            $course->icon = $DB->get_field('course','icon',array('id'=>$course->id));
        } else {
            $course->icon = '';
        }
    }
    return '<img id="iconpreview" src="'.$CFG->wwwroot.'/local/courseicon/icon.php?id='.$course->id.'&amp;icon='.$course->icon.'&amp;size='.$size.'&type=course" alt="'.$course->shortname.'" class="class_icon" />';
}

/**
 * Return img tag to a course category icon
 *
 * @param object $coursecat Course category object
 * @param string $size Size of icon set to use
 * @global $CFG
 * @global $COURSE
 * @return string img tag
 */
function local_courseicon_category_icon_tag($coursecat, $size='large') {
    global $CFG;
    return '<img src="'.$CFG->wwwroot.'/local/courseicon/icon.php?icon='.$coursecat->icon.'&amp;size='.$size.'&type=coursecategory" alt="'.$coursecat->name.'" class="class_icon" />';
}

/**
 * Get list of stock course icons for use in a MoodleQuickForm select
 * @param $type string
 * @return array
 */
function local_courseicon_get_stock_icons($type) {
    global $CFG;
    $icons = array('custom' => get_string('customicon', 'local_courseicon'),
                   'none' => get_string('noicons', 'local_courseicon'));

    if ($path = local_courseicon_get_stock_icon_dir($type)) {
        $d = dir($path.'/large');
        while(($icon = $d->read()) !== false) {
            if (is_file($path.'/large/'.$icon)) {

                $icons[$icon] = ucwords(strtr($icon, array('_' => ' ', '-' => ' ', '.png' => '')));
            }
        }
        $d->close();
    }
    return($icons);
}

/**
 * Return the path to the proper icon directory
 * @param $type string The type of icon
 * @return string
 */
function local_courseicon_get_stock_icon_dir($type) {
    global $CFG;

    if ($type == 'course') {
        $dir = 'courseicons';
    } elseif ($type == 'coursecategory') {
        $dir = 'coursecategoryicons';
    } else {
        return(false);
    }

    if (is_dir($CFG->dirroot.'/theme/'.$CFG->theme.'/'.$dir)) {
        return($CFG->dirroot.'/theme/'.$CFG->theme.'/'.$dir);
    } else {
        return $CFG->dirroot.'/local/courseicon/'.$dir;
    }
}

/**
 * Send icon data
 *
 * @param int $courseid Course id
 * @param string $courseicon Name of icon to send
 * @param string $size Icon size to send
 * @global $CFG
 */
function local_courseicon_output_course_icon($courseid, $courseicon, $size='large') {
    global $CFG;

    $icondir = local_courseicon_get_stock_icon_dir('course');
    $iconname = 'default.png';
    $icon = $icondir.'/'.$size.'/default.png';

    // Todo: rewrite this to work with the new file API
    if ($courseicon == 'custom') {
        if (is_file($CFG->dataroot.'/'.$courseid.'/icons/'.$size.'.png')) {
            $icon = $CFG->dataroot.'/'.$courseid.'/icons/'.$size.'.png';
            $iconname = 'customicon.png';
        }
    } else {
        if (is_file($icondir.'/'.$size.'/'.$courseicon)) {
            $icon = $icondir.'/'.$size.'/'.$courseicon;
            $iconname = $courseicon;
        }
    }
    send_file($icon, $iconname);
}

/**
 * Send course cateogry icon data
 *
 * @param string $courseicon Name of icon to send
 * @param string $size Icon size to send
 * @global $CFG
 */
function local_courseicon_output_category_icon($iconname, $size='large') {
    global $CFG;

    $site = get_site();

    $icondir = local_courseicon_get_stock_icon_dir('coursecategory');
    $iconpath = 'default.png';
    $icon = $icondir.'/'.$size.'/default.png';

    // Todo: rewrite this to work with the new file API
    if ($iconname == 'custom') {
        if (is_file($CFG->dataroot.'/'.$site->id.'/icons/coursecategory/'.$size.'.png')) {
            $icon = $CFG->dataroot.'/'.$site->id.'/icons/coursecategory/'.$size.'.png';
            $iconpath = 'customicon.png';
        }
    } else {
        if (is_file($icondir.'/'.$size.'/'.$iconname)) {
            $icon = $icondir.'/'.$size.'/'.$iconname;
        }
        $iconpath = $iconname;
    }
    send_file($icon, $iconname);
}

/**
 * Update course icon
 *
 * @param object $course Course object
 * @param object $data Formslib form data
 * @param MoodleQuickForm $mform Moodle form
 * @global $CFG
 */
function local_courseicon_update_course_icon($course, $data, &$mform) {
    global $CFG, $DB;

    $updatecourse = new stdClass();
    $updatecourse->id = $course->id;

    if ($data->icon == 'custom') {
        //Move icon to course
        $updatecourse->icon = 'custom';

        // todo: make this work with new file API
//        $dest = $CFG->dataroot.'/'.$course->id.'/icons/';
//        make_upload_directory($dest);
//        $mform->save_files($dest);
//
//        if ($filename =  $mform->get_new_filename()) {
//            resize_image($dest.$filename, $dest.'large', 50, 50, 'png');
//            resize_image($dest.$filename, $dest.'small', 25, 25, 'png');
//            unlink($dest.$filename);
//        }

    } elseif ($data->icon == 'none') {
        $updatecourse->icon = '';
    } else {
        $updatecourse->icon = $data->icon;
    }

    $DB->update_record('course', $updatecourse);
}

/**
 * Update course category icon
 *
 * @param object $coursecategory Course object
 * @param object $data Formslib form data
 * @param MoodleQuickForm $mform Moodle form
 * @global $CFG
 */
function local_courseicon_update_category_icon($coursecategory, $data, &$mform) {
    global $CFG, $DB;

    $site = get_site();

    $updatecoursecat = new stdClass;
    $updatecoursecat->id = $coursecategory->id;

    if ($data->icon == 'custom') {
        // todo: make this work with new File API
//        //Move icon to course
//        $updatecoursecat->icon = 'custom';
//
//        $dest = $CFG->dataroot.'/'.$site->id.'/icons/coursecategory/';
//        make_upload_directory($dest);
//        $mform->save_files($dest);
//
//        if ($filename =  $mform->get_new_filename()) {
//            resize_image($dest.$filename, $dest.'large', 50, 50, 'png');
//            resize_image($dest.$filename, $dest.'small', 25, 25, 'png');
//            unlink($dest.$filename);
//        }
//
    } elseif ($data->icon == 'none') {
        $updatecoursecat->icon = '';
    } else {
        $updatecoursecat->icon = $data->icon;
    }
    $DB->update_record('course_categories', $updatecoursecat);
}
