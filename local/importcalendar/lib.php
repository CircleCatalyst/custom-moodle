<?php

function importcalendar_show_subscriptions($courseid) {
    global $DB, $OUTPUT;

    $out = '';
    $str->update = get_string('update');
    $str->remove = get_string('remove');
    $str->add    = get_string('add');

    $out .= $OUTPUT->box_start('generalbox calendarsubs');
    $table = new html_table();
    $table->head  = array('URL', 'Poll', 'Actions');
    $table->align = array('left', 'left', 'center');
    $table->width = '90%';
    $table->data  = array();

    $subs = $DB->get_records('event_subscriptions', array('courseid' => $courseid));
    foreach ($subs as $id => $sub) {
        $actions =  "<input type=\"button\" value=\"{$str->update}\" />";
        $actions .= "<input type=\"button\" value=\"{$str->remove}\" />";
        $table->data[] = array($sub->url, $sub->pollinterval, $actions);
    }
    $pollselect = "<select name=\"pollinterval\">
        <option value=\"0\"> Never </option>
        <option value=\"3600\"> Hourly </option>
        <option value=\"86400\"> Daily </option>
        <option value=\"604800\"> Weekly </option>
        <option value=\"31536000\"> Annually </option>
    </select>";

    $table->data[] = array('<input type="text" name="url" />', $pollselect, "<input type=\"button\" value=\"{$str->add}\" />");

    $out .= html_writer::table($table);
    $out .= $OUTPUT->box_end();
    return $out;
}

