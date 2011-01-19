<?php

require('../../config.php');
require_once($CFG->libdir.'/filelib.php');

// disable moodle specific debug messages
define('NO_DEBUG_DISPLAY', true);
$force = optional_param('force',false,PARAM_BOOL);
$fs = get_file_storage();
$context = get_context_instance(CONTEXT_SYSTEM);
$PAGE->set_context($context);
$files = $fs->get_area_files($context->id, 'local_nzschools', 'logo', 0);
if ( count($files) ){
	$file = array_pop($files);
	send_stored_file($file);
} elseif ($force){
	die();
} elseif (is_file($CFG->dirroot.'/theme/'.current_theme().'/images/logo.png')) {
    send_file($CFG->dirroot.'/theme/'.current_theme().'/images/logo.png', 'logo.png', 525600);
} else {
    send_file($CFG->dirroot.'/pix/moodlelogo-med-white.gif', 'moodlelogo-med-white.gif', 525600);
}
?>