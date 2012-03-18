<?php
require_once('config.php');

function callback_simple_load_content(&$navigation, $course, $coursenode) {
        return $navigation->load_generic_course_sections($course, $coursenode, 'simple');
}

function callback_simple_get_section_name($course, $section) {
    global $CFG, $DB;
    $sql = "SELECT name FROM ".$CFG->prefix."course_sections ". 
           "WHERE course=".$course->id." AND section=".$section->section;

    $sectioninfo = $DB->get_record_sql($sql);
    if (!empty($sectioninfo)) {
        if (!empty($sectioninfo->name)) {
            return $sectioninfo->name;
        } else {
            return get_string('section').' '.$section->section;
        }
    } else {
        return get_string('section').' '.$section->section;
    }
}

function simple_topics_add_sectioninfo() {
    global $CFG, $DB, $COURSE;
    $sections = $DB->get_records_sql("SELECT id FROM ".$CFG->prefix."course_sections WHERE course=".$COURSE->id);
    
    if (!empty($sections)) {
        foreach ($sections as $section) {
            $simplesection = $DB->get_record_sql("SELECT id FROM ".$CFG->prefix."simple_topics_sections WHERE sectionid=".$section->id);

            if (empty($simplesection)) {
                $DB->insert_record('simple_topics_sections', (object) array('sectionid' => $section->id, 'showtitle' => 1));
            }
        }
    }
}
?>
