<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // logo image setting
    // moved this to local/nzschools/settings_page.php
    //	$name = 'theme_nz_schools/logo';
    //	$title = get_string('schoollogolabel','theme_nz_schools');
    //	$description = get_string('schoollogoinstructions', 'theme_nz_schools');
    //        $setting = new admin_setting_configfile($name, $title, $description, '/');
    //	$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_URL);
    //	$settings->add($setting);

    $setting = new admin_setting_configcheckbox('theme_glendowie/plainbg', get_string('logosolidlabel', 'theme_glendowie'), null, false);
    $settings->add($setting);

    // link color setting
    $name = 'theme_glendowie/colour1';
    $title = get_string('backgroundcolourlabel','theme_glendowie');
    $description = null;
    $default = '#555454';
    $previewconfig = array(
		'selector'=>'#page, html, body',
		'style'=>'background'
	);
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

	// link hover color setting
	$name = 'theme_glendowie/colour2';
	$title = get_string('navigationcolourlabel','theme_glendowie');
	$description = null;
	$default = '#a1a1a1';
	$previewconfig = array(
        'selector'=>'.navbar, .navbar-home',
        'style'=>'background'
	);
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

	// main color setting
	$name = 'theme_glendowie/colour3';
	$title = get_string('blocktitlecolourlabel','theme_glendowie');
	$description = null;
	$default = '#a1a1a1';
	$previewconfig = array(
        'selector'=>'.block .header',
        'style'=>'background'
	);
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

}