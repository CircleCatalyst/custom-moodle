<?php
require_once(dirname(__FILE__) . '/../../config.php');
//require_once(dirname(__FILE__).'/settings_form.php');
require_once($CFG->dirroot.'/local/nzschools/settings_form.php');
require_once($CFG->dirroot.'/local/nzschools/lib.php');
if (false) {
    $DB = new moodle_database();
    $OUTPUT = new core_renderer(null, null);
    $PAGE = new moodle_page();
}

$site = get_site();
if (!$site) {
    redirect($CFG->wwwroot.'/admin/index.php');
    exit;
}

// Security check
require_login(0, false);
$context = get_context_instance(CONTEXT_SYSTEM);
require_capability('moodle/site:config', $context);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/nzschools/settings_page.php');
$mform = new nzschoolssettings_form();

if ($data = $mform->get_data()) {

    // Set site name
    $site->fullname     = $data->sitename;
    $site->shortname    = $data->shortname;

    $DB->update_record('course', $site);

    // Save theme info
    set_config('theme_plainbg', !empty($data->plainbg));

    // Save install profile type
    set_config('installprofile', $data->installprofile);

    // Save years
    set_config('fromyear', $data->fromyear+1);
    set_config('toyear', $data->toyear+1);

    // Auto create cats
    set_config('createcats', !empty($data->createcats));

    // Handle logo
//    $um = new upload_manager('logo',true,false,null,false,0,true,true);

    $dir = $CFG->dataroot.'/theme';

    if (!is_dir($dir)) {
        mkdir($dir);
    }

//    if ($um->process_file_uploads($dir)) {
//        $message  = $um->get_errors();
//        if($filename = $um->get_new_filename()) {
//            $newfilename = process_logo($filename);
//        }
//    }
    // todo: redirect back to this same settings page with a "Settings Saved" message
    redirect($CFG->wwwroot.'/admin/index.php');
} else {


    // Display
    $strnzschoolssettings = get_string('nzschoolssettings', 'local_nzschools');
//    $navigation  = build_navigation(array(array('name' => $strnzschoolssettings, 'link' => null, 'type' => 'misc')));
//    $PAGE->requires->yui2_lib(array('yui_yahoo',
//                'yui_event',
//                'yui_dom',
//                'yui_connection',
//                'yui_animation',
//                'yui_container',
//                'yui_dragdrop',
//                'yui_slider',
//                'yui_element',
//                'yui_get',
//                'yui_colorpicker'));

//    echo "<script type=\"text/javascript\" src=\"/schools/local/nzschools/settings.js\" />";
    $PAGE->requires->js('/local/nzschools/settings.js');

    // Temporarily set CSS files to be loaded for this page
//    $CFG->stylesheets[] = $CFG->wwwroot.'/lib/yui/colorpicker/assets/skins/sam/colorpicker.css';
//    $CFG->stylesheets[] = $CFG->wwwroot.'/lib/yui/slider/assets/skins/sam/slider.css';
//    $CFG->stylesheets[] = $CFG->wwwroot.'/lib/yui/container/assets/skins/sam/container.css';


//    print_header($strnzschoolssettings, $strnzschoolssettings, $navigation, '', '', false, '&nbsp;', '&nbsp;');
    echo $OUTPUT->header();
//    print_heading($strnzschoolssettings);
    echo $OUTPUT->heading($strnzschoolssettings);

    $mform->set_data(array('colour1'        => @$CFG->theme_colour1,
                           'colour2'        => @$CFG->theme_colour2,
                           'colour3'        => @$CFG->theme_colour3,
                           'plainbg'        => @$CFG->theme_plainbg,
                           'sitename'       => $site->fullname,
                           'shortname'      => $site->shortname,
                           'installprofile' => @$CFG->installprofile,
                           'fromyear'       => empty($CFG->fromyear) ? 0 : $CFG->fromyear-1,
                           'toyear'         => empty($CFG->toyear) ? 12 : $CFG->toyear-1,
                           'createcats'     => isset($CFG->createcats) ? $CFG->createcats : 1
                           ));
    // Print path to dynamic css file for use in JS
    echo '<script type="text/javascript">var dynamic_css = "'.$CFG->wwwroot.'/theme/'.current_theme().'/dynamic_css.php";</script>';

    // Print page body
    echo '<table width="100%"><tr><td width="80%">';

    $mform->display();

    echo '</td><td valign="top">';

    // Print a block to assist with styling
//    print_side_block('<h2>'.get_string('themetips', 'local_nzschools').'</h2>', get_string('themetipsdetail', 'local_nzschools'));

    echo '</td></tr></table>';

    echo $OUTPUT->footer();
//    print_footer('none');
}

?>