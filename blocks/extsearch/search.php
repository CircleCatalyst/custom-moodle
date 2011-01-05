<?php
require_once '../../config.php';

require_login();

$id       = required_param('id', PARAM_INT); // extsearch block instance ID
$query    = stripslashes(optional_param('query', '', PARAM_NOTAGS)); // search query
$page     = optional_param('page', 0, PARAM_INT); // page number to display
$searchid = optional_param('searchid', 0, PARAM_INT); // resultset token
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$pinned   = optional_param('pinned', 0, PARAM_INT);
$choose   = optional_param('choose', '', PARAM_TEXT); // optional parameter when the block is used as a picker
$filter   = optional_param('filter',array(), PARAM_TEXT);
$direction = optional_param('direction',0,PARAM_INT); //options 0 or 1. if 1, sets sort direction to desc. (DNZ specific)
$sort     = optional_param('sort','',PARAM_TEXT); //field to sort by (category, content_provider, date, syndication_date, title) (DNZ specific)

if (!$course = $DB->get_record('course', array('id'=>$courseid))) {
    print_error('error:incorrectcourseid', 'block_extsearch');
}
$courselink = $CFG->wwwroot.'/course/view.php?id='.$courseid;

if ($page < 0) {
    $page = 0;
}

if (!$blockinstance = $DB->get_record('block_instances', array('id'=>$id))) {
    if (empty($choose)) {
        print_error('error:incorrectblockid', 'block_extsearch', $courselink);
    }
    else {
        print_error('error:incorrectblockidpicker', 'block_extsearch', $courselink);
    }
}
$blockconfig = unserialize(base64_decode($blockinstance->configdata));

$searchprovider = '';
$searchprovidername = get_string('pluginname', 'block_extsearch');
if (!empty($blockconfig->search_provider)) {
    $searchprovider = clean_param($blockconfig->search_provider, PARAM_ALPHANUM);
    $searchprovidername = get_string($blockconfig->search_provider, 'block_extsearch');
}

$searchengineclassname = "SearchEngine_$searchprovider";
if (!file_exists("$searchengineclassname.php")) {
    print_error('error:unsupportedsearchprovider', 'block_extsearch', $courselink);
}

// Page header
$pagetitle = $searchprovidername;
$navlinks = array();
if ($courseid != SITEID) {
    $navlinks[] = array('name' => $course->fullname, 'link' => $courselink, 'type' => 'course');
    $pagetitle = $course->fullname . ': ' . $pagetitle;
}
$navlinks[] = array('name' => $searchprovidername, 'link' => '', 'type' => 'activity');
$navigation = build_navigation($navlinks);

print_header_simple($pagetitle, "", $navigation, "", "", true, "", navmenu($course));

if (empty($query)) {
    print '<p>'.get_string('entersearchterms', 'block_extsearch', $searchprovidername).'</p>';
}
else {
    require_once "$searchengineclassname.php";
    $searchengine = new $searchengineclassname;
    $searchengine->filter = &$filter;
    if (!empty($choose)) {
        $searchengine->choose = $choose;
    }
    $searchengine->pinned = $pinned;
    $searchengine->sort = $sort;
    $searchengine->direction = $direction;
    $searchengine->set_query($query, $page, $searchid, $blockconfig, $courselink);

    if (debugging('', DEBUG_DEVELOPER)) {
        print '<div><table cellpadding="10" border="1" summary="">';
        print '<tr><td>Search URL:</td>';
        print '<td><a href="'.format_string($searchengine->searchurl).'">'.format_string($searchengine->searchurl).'</a></td></tr>';
        print '</table></div>';
    }
}

// Print a search box at the top of the page
print '<form action="'.$CFG->wwwroot.'/blocks/extsearch/search.php" method="get">';
print '<p><input type="hidden" name="id" value="'.$id.'" />';
print '<input type="hidden" name="courseid" value="'.$courseid.'" />';
print '<input type="hidden" name="choose" value="'.$choose.'" />';
print '<input type="hidden" name="pinned" value="'.$pinned.'" />';
if (!empty($searchengine) and $searchengine->supportsfacets) {
    foreach ($filter as $key => $value) {
        print '<input type="hidden" id="filter['.$key.']" name="filter['.$key.']" value="'.$value.'" />';
    }
}
if (!empty($sort)) {
    print '<input type="hidden" id="sort" name="sort" value="'.$sort.'" />';
}
if (!empty($direction)) {
    print '<input type="hidden" id="direction" name="direction" value="'.$direction.'" />';
}
print '<input type="text" id="query" name="query" size="48" value="'.htmlspecialchars($query).'" />';
print '<input type="submit" value="'.get_string('searchbutton', 'block_extsearch').'" />';
print helpbutton('querysyntax_'.$searchprovider, get_string('querysyntax', 'block_extsearch'), 'block_extsearch').'</p>';
print '</form>';

if (!empty($query) && $searchengine->search()) {
    $searchengine->print_results($id, $courseid, $choose);
}

print_footer($course, $courseid);

?>
