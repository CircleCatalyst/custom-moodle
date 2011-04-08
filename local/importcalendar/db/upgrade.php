<?php

defined('MOODLE_INTERNAL') or die();
require_once "{$CFG->libdir}/db/upgradelib.php";

function xmldb_local_importcalendar_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2011032100) {

        // Define table event_subscriptions to be created
        $table = new xmldb_table('event_subscriptions');

        // Adding fields to table event_subscriptions
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('url', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('pollinterval', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastupdated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table event_subscriptions
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for event_subscriptions
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2011032100, 'local', 'importcalendar');
    }

    if ($oldversion < 2011032101) {

        // Add subscription field to the event table
        $table = new xmldb_table('event');
        $field = new xmldb_field('subscriptionid', XMLDB_TYPE_INTEGER, '10');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2011032101, 'local', 'importcalendar');
    }

    if ($oldversion < 2011032102) {

        // Add name field to the event_subscriptions table
        $table = new xmldb_table('event_subscriptions');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2011032102, 'local', 'importcalendar');
    }

    if ($oldversion < 2011040800) {
        // From RFC-2445:
        // Implementations MUST be able to receive and persist values of at least
        // 255 characters for this property [UID].
        $table = new xmldb_table('event');
        $field = new xmldb_field('uuid', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL, null, '', 'visible');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        upgrade_plugin_savepoint(true, 2011040800, 'local', 'importcalendar');
    }

    return true;
}

