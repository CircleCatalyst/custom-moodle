<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

	// Background image setting
	$name = 'theme_eggs/background';
	$title = get_string('background','theme_eggs');
	$description = get_string('backgrounddesc', 'theme_eggs');
	$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_URL);
	$settings->add($setting);

	// logo image setting
	$name = 'theme_eggs/logo';
	$title = get_string('logo','theme_eggs');
	$description = get_string('logodesc', 'theme_eggs');
	$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_URL);
	$settings->add($setting);

	// link color setting
	$name = 'theme_eggs/linkcolor';
	$title = get_string('linkcolor','theme_eggs');
	$description = get_string('linkcolordesc', 'theme_eggs');
	$default = '#32529a';
	$previewconfig = NULL;
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

	// link hover color setting
	$name = 'theme_eggs/linkhover';
	$title = get_string('linkhover','theme_eggs');
	$description = get_string('linkhoverdesc', 'theme_eggs');
	$default = '#4e2300';
	$previewconfig = NULL;
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

	// main color setting
	$name = 'theme_eggs/maincolor';
	$title = get_string('maincolor','theme_eggs');
	$description = get_string('maincolordesc', 'theme_eggs');
	$default = '#002f2f';
	$previewconfig = NULL;
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

	// main color accent setting
	$name = 'theme_eggs/maincoloraccent';
	$title = get_string('maincoloraccent','theme_eggs');
	$description = get_string('maincoloraccentdesc', 'theme_eggs');
	$default = '#092323';
	$previewconfig = NULL;
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

	// heading color setting
	$name = 'theme_eggs/headingcolor';
	$title = get_string('headingcolor','theme_eggs');
	$description = get_string('headingcolordesc', 'theme_eggs');
	$default = '#4e0000';
	$previewconfig = NULL;
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

	// block heading color setting
	$name = 'theme_eggs/blockcolor';
	$title = get_string('blockcolor','theme_eggs');
	$description = get_string('blockcolordesc', 'theme_eggs');
	$default = '#002f2f';
	$previewconfig = NULL;
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

	// forum subject background color setting
	$name = 'theme_eggs/forumback';
	$title = get_string('forumback','theme_eggs');
	$description = get_string('forumbackdesc', 'theme_eggs');
	$default = '#e6e2af';
	$previewconfig = NULL;
	$setting = new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig);
	$settings->add($setting);

}