<?php

/**
 * Settings for the alfriston theme
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {


    // Custom tagline
    $name = 'theme_alfriston/tagline';
    $title = get_string('tagline','theme_alfriston');
    $description = get_string('taglinedesc', 'theme_alfriston');
    //$default = 'Zest for Learning - "Te Ihi ki te Ako"';
    //$setting = new admin_setting_configtext($name, $title, $description, $default, '');
	$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_URL);
    $settings->add($setting);

    // Custom CSS file
    $name = 'theme_alfriston/customcss';
    $title = get_string('customcss','theme_alfriston');
    $description = get_string('customcssdesc', 'theme_alfriston');
    $setting = new admin_setting_configtextarea($name, $title, $description, '');
    $settings->add($setting);
}