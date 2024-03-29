<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Sets up the tabs used by the scorm pages based on the users capabilities.
 *
 * @author Dan Marsden and others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package hotpot
 */

if (empty($hotpot)) {
    error('You cannot call this script in that way');
}
if (!isset($currenttab)) {
    $currenttab = '';
}
if (!isset($cm)) {
    $cm = get_coursemodule_from_instance('hotpot', $hotpot->id);
}
if (!isset($tabreturn)) {
    $tabreturn = false;
}
$contextmodule = get_context_instance(CONTEXT_MODULE, $cm->id);

$tabs = array();
$row = array();
$inactive = array();
$activated = array();

if (has_capability('mod/hotpot:reviewallattempts', $contextmodule)) {
    $row[] = new tabobject('reports', "$CFG->wwwroot/mod/hotpot/report.php?id=$cm->id", get_string('reports', 'hotpot'));
    $tabs[] = $row;

    //$reportlist = get_plugin_list('hotpotreport');
    $reportlist = array('analysis', 'overview', 'responses', 'scores'); //don't show all plugins as not all can be loaded like this.
    if ($currenttab == 'reports' && !empty($reportlist) && count($reportlist) > 1) {
        $row2 = array();
        foreach ($reportlist as $shortname) {
            $row2[] = new tabobject('hotpotreport_'.$shortname, $CFG->wwwroot."/mod/hotpot/report.php?id=$cm->id&mode=$shortname", get_string('pluginname', 'hotpotreport_'.$shortname));
        }
        $tabs[] = $row2;
    }
    $taboutput = print_tabs($tabs, $currenttab, $inactive, $activated, $tabreturn);
}
