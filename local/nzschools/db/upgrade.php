<?php

defined('MOODLE_INTERNAL') || die();
require_once "{$CFG->libdir}/db/upgradelib.php";

function xmldb_local_nzschools_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2011051800) {
        /*
         * Needs miscellaneous course category so adding a course without specifiying a category works
         */

        // If category does exist, and there are no courses in the category, make it hidden
        if ($misccat = $DB->get_record('course_categories', array('name'=>'Miscellaneous'))) {
            if(!$DB->record_exists('course', array('category'=>$misccat->id))) {
                $DB->update_record('course_categories', array('id'=>$misccat->id, 'visible'=>0));
            }
        // If category does not exist, and there is no category with ID 1, create the category and set it to hidden
        } else if (!$misccat = $DB->get_record('course_categories', array('id'=>1))) {
            $misccat = array(
                'id'=>1,
                'name'=>'Miscellaneous',
                'description'=>'',
                'descriptionformat'=>0,
                'parent'=>0,
                'sortorder'=>10000,
                'coursecount'=>0,
                'visible'=>0,
                'visibleold'=>1,
                'timemodified'=>time(),
                'depth'=>1,
                'path'=>"/1",
                'theme'=>'',
                'icon'=>'',
            );
            // Import record is used to allow ID of 1 to be set
            $DB->import_record('course_categories', $misccat);
        }
        upgrade_plugin_savepoint(true, 2011051800, 'local', 'nzschools');
    }
    return true;
}
