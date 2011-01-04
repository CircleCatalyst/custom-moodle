<?php

$settings->add(new admin_setting_configtext('block_extsearch_google_api_key',
               get_string('googleapikey2', 'block_extsearch'),
               get_string('googleapikey', 'block_extsearch'), '', PARAM_SAFEDIR));

$settings->add(new admin_setting_configtext('block_extsearch_esfs_client_token',
               get_string('esfsclienttoken2', 'block_extsearch'),
               get_string('esfsclienttoken', 'block_extsearch'), 'tester1234', PARAM_ALPHANUM));

$settings->add(new admin_setting_configtext('block_extsearch_digitalnz_api_key',
               get_string('digitalnzapikey2', 'block_extsearch'),
               get_string('digitalnzapikey', 'block_extsearch'), '', PARAM_ALPHANUM));

?>
