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
 * @package    moodlecore
 * @subpackage backup-dbops
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Base abstract class for all the helper classes providing DB operations
 *
 * TODO: Finish phpdocs
 */
abstract class restore_dbops {

    /**
     * Return one array containing all the tasks that have been included
     * in the restore process. Note that these tasks aren't built (they
     * haven't steps nor ids data available)
     */
    public static function get_included_tasks($restoreid) {
        $rc = restore_controller_dbops::load_controller($restoreid);
        $tasks = $rc->get_plan()->get_tasks();
        $includedtasks = array();
        foreach ($tasks as $key => $task) {
            // Calculate if the task is being included
            $included = false;
            // blocks, based in blocks setting and parent activity/course
            if ($task instanceof restore_block_task) {
                if (!$task->get_setting_value('blocks')) { // Blocks not included, continue
                    continue;
                }
                $parent = basename(dirname(dirname($task->get_taskbasepath())));
                if ($parent == 'course') { // Parent is course, always included if present
                    $included = true;

                } else { // Look for activity_included setting
                    $included = $task->get_setting_value($parent . '_included');
                }

            // ativities, based on included setting
            } else if ($task instanceof restore_activity_task) {
                $included = $task->get_setting_value('included');

            // sections, based on included setting
            } else if ($task instanceof restore_section_task) {
                $included = $task->get_setting_value('included');

            // course always included if present
            } else if ($task instanceof restore_course_task) {
                $included = true;
            }

            // If included, add it
            if ($included) {
                $includedtasks[] = $task;
            }
        }
        return $includedtasks;
    }

    /**
     * Load one inforef.xml file to backup_ids table for future reference
     */
    public static function load_inforef_to_tempids($restoreid, $inforeffile) {

        if (!file_exists($inforeffile)) { // Shouldn't happen ever, but...
            throw new backup_helper_exception('missing_inforef_xml_file', $inforeffile);
        }
        // Let's parse, custom processor will do its work, sending info to DB
        $xmlparser = new progressive_parser();
        $xmlparser->set_file($inforeffile);
        $xmlprocessor = new restore_inforef_parser_processor($restoreid);
        $xmlparser->set_processor($xmlprocessor);
        $xmlparser->process();
    }

    /**
     * Load the needed role.xml file to backup_ids table for future reference
     */
    public static function load_roles_to_tempids($restoreid, $rolesfile) {

        if (!file_exists($rolesfile)) { // Shouldn't happen ever, but...
            throw new backup_helper_exception('missing_roles_xml_file', $rolesfile);
        }
        // Let's parse, custom processor will do its work, sending info to DB
        $xmlparser = new progressive_parser();
        $xmlparser->set_file($rolesfile);
        $xmlprocessor = new restore_roles_parser_processor($restoreid);
        $xmlparser->set_processor($xmlprocessor);
        $xmlparser->process();
    }

    /**
     * Precheck the loaded roles, return empty array if everything is ok, and
     * array with 'errors', 'warnings' elements (suitable to be used by restore_prechecks)
     * with any problem found. At the same time, store all the mapping into backup_ids_temp
     * and also put the information into $rolemappings (controller->info), so it can be reworked later by
     * post-precheck stages while at the same time accept modified info in the same object coming from UI
     */
    public static function precheck_included_roles($restoreid, $courseid, $userid, $samesite, $rolemappings) {
        global $DB;

        $problems = array(); // To store warnings/errors

        // Get loaded roles from backup_ids
        $rs = $DB->get_recordset('backup_ids_temp', array('backupid' => $restoreid, 'itemname' => 'role'), '', 'itemid');
        foreach ($rs as $recrole) {
            // If the rolemappings->modified flag is set, that means that we are coming from
            // manually modified mappings (by UI), so accept those mappings an put them to backup_ids
            if ($rolemappings->modified) {
                $target = $rolemappings->mappings[$recrole->itemid]->targetroleid;
                self::set_backup_ids_record($restoreid, 'role', $recrole->itemid, $target);

            // Else, we haven't any info coming from UI, let's calculate the mappings, matching
            // in multiple ways and checking permissions. Note mapping to 0 means "skip"
            } else {
                $role = (object)self::get_backup_ids_record($restoreid, 'role', $recrole->itemid)->info;
                $match = self::get_best_assignable_role($role, $courseid, $userid, $samesite);
                // Send match to backup_ids
                self::set_backup_ids_record($restoreid, 'role', $recrole->itemid, $match);
                // Build the rolemappings element for controller
                unset($role->id);
                unset($role->nameincourse);
                unset($role->nameincourse);
                $role->targetroleid = $match;
                $rolemappings->mappings[$recrole->itemid] = $role;
                // Prepare warning if no match found
                if (!$match) {
                    $problems['warnings'][] = get_string('cannotfindassignablerole', 'backup', $role->name);
                }
            }
        }
        $rs->close();
        return $problems;
    }

    /**
     * Given one role, as loaded from XML, perform the best possible matching against the assignable
     * roles, using different fallback alternatives (shortname, archetype, editingteacher => teacher, defaultcourseroleid)
     * returning the id of the best matching role or 0 if no match is found
     */
    protected static function get_best_assignable_role($role, $courseid, $userid, $samesite) {
        global $CFG, $DB;

        // Gather various information about roles
        $coursectx = get_context_instance(CONTEXT_COURSE, $courseid);
        $allroles = $DB->get_records('role');
        $assignablerolesshortname = get_assignable_roles($coursectx, ROLENAME_SHORT, false, $userid);

        // Note: under 1.9 we had one function restore_samerole() that performed one complete
        // matching of roles (all caps) and if match was found the mapping was availabe bypassing
        // any assignable_roles() security. IMO that was wrong and we must not allow such
        // mappings anymore. So we have left that matching strategy out in 2.0

        // Empty assignable roles, mean no match possible
        if (empty($assignablerolesshortname)) {
            return 0;
        }

        // Match by shortname
        if ($match = array_search($role->shortname, $assignablerolesshortname)) {
            return $match;
        }

        // Match by archetype
        list($in_sql, $in_params) = $DB->get_in_or_equal(array_keys($assignablerolesshortname));
        $params = array_merge(array($role->archetype), $in_params);
        if ($rec = $DB->get_record_select('role', "archetype = ? AND id $in_sql", $params, 'id', IGNORE_MULTIPLE)) {
            return $rec->id;
        }

        // Match editingteacher to teacher (happens a lot, from 1.9)
        if ($role->shortname == 'editingteacher' && in_array('teacher', $assignablerolesshortname)) {
            return array_search('teacher', $assignablerolesshortname);
        }

        // No match, return 0
        return 0;
    }


    /**
     * Process the loaded roles, looking for their best mapping or skipping
     * Any error will cause exception. Note this is one wrapper over
     * precheck_included_roles, that contains all the logic, but returns
     * errors/warnings instead and is executed as part of the restore prechecks
     */
     public static function process_included_roles($restoreid, $courseid, $userid, $samesite, $rolemappings) {
        global $DB;

        // Just let precheck_included_roles() to do all the hard work
        $problems = self::precheck_included_roles($restoreid, $courseid, $userid, $samesite, $rolemappings);

        // With problems of type error, throw exception, shouldn't happen if prechecks executed
        if (array_key_exists('errors', $problems)) {
            throw new restore_dbops_exception('restore_problems_processing_roles', null, implode(', ', $problems['errors']));
        }
    }

    /**
     * Load the needed users.xml file to backup_ids table for future reference
     */
    public static function load_users_to_tempids($restoreid, $usersfile) {

        if (!file_exists($usersfile)) { // Shouldn't happen ever, but...
            throw new backup_helper_exception('missing_users_xml_file', $usersfile);
        }
        // Let's parse, custom processor will do its work, sending info to DB
        $xmlparser = new progressive_parser();
        $xmlparser->set_file($usersfile);
        $xmlprocessor = new restore_users_parser_processor($restoreid);
        $xmlparser->set_processor($xmlprocessor);
        $xmlparser->process();
    }

    /**
     * Load the needed questions.xml file to backup_ids table for future reference
     */
    public static function load_categories_and_questions_to_tempids($restoreid, $questionsfile) {

        if (!file_exists($questionsfile)) { // Shouldn't happen ever, but...
            throw new backup_helper_exception('missing_questions_xml_file', $questionsfile);
        }
        // Let's parse, custom processor will do its work, sending info to DB
        $xmlparser = new progressive_parser();
        $xmlparser->set_file($questionsfile);
        $xmlprocessor = new restore_questions_parser_processor($restoreid);
        $xmlparser->set_processor($xmlprocessor);
        $xmlparser->process();
    }

    /**
     * Check all the included categories and questions, deciding the action to perform
     * for each one (mapping / creation) and returning one array of problems in case
     * something is wrong.
     *
     * There are some basic rules that the method below will always try to enforce:
     *
     * Rule1: Targets will be, always, calculated for *whole* question banks (a.k.a. contexid source),
     *     so, given 2 question categories belonging to the same bank, their target bank will be
     *     always the same. If not, we can be incurring into "fragmentation", leading to random/cloze
     *     problems (qtypes having "child" questions).
     *
     * Rule2: The 'moodle/question:managecategory' and 'moodle/question:add' capabilities will be
     *     checked before creating any category/question respectively and, if the cap is not allowed
     *     into upper contexts (system, coursecat)) but in lower ones (course), the *whole* question bank
     *     will be created there.
     *
     * Rule3: Coursecat question banks not existing in the target site will be created as course
     *     (lower ctx) question banks, never as "guessed" coursecat question banks base on depth or so.
     *
     * Rule4: System question banks will be created at system context if user has perms to do so. Else they
     *     will created as course (lower ctx) question banks (similary to rule3). In other words, course ctx
     *     if always a fallback for system and coursecat question banks.
     *
     * Also, there are some notes to clarify the scope of this method:
     *
     * Note1: This method won't create any question category nor question at all. It simply will calculate
     *     which actions (create/map) must be performed for each element and where, validating that all those
     *     actions are doable by the user executing the restore operation. Any problem found will be
     *     returned in the problems array, causing the restore process to stop with error.
     *
     * Note2: To decide if one question bank (all its question categories and questions) is going to be remapped,
     *     then all the categories and questions must exist in the same target bank. If able to do so, missing
     *     qcats and qs will be created (rule2). But if, at the end, something is missing, the whole question bank
     *     will be recreated at course ctx (rule1), no matter if that duplicates some categories/questions.
     *
     * Note3: We'll be using the newitemid column in the temp_ids table to store the action to be performed
     *     with each question category and question. newitemid = 0 means the qcat/q needs to be created and
     *     any other value means the qcat/q is mapped. Also, for qcats, parentitemid will contain the target
     *     context where the categories have to be created (but for module contexts where we'll keep the old
     *     one until the activity is created)
     *
     * Note4: All these "actions" will be "executed" later by {@link restore_create_categories_and_questions}
     */
    public static function precheck_categories_and_questions($restoreid, $courseid, $userid, $samesite) {

        $problems = array();

        // TODO: Check all qs, looking their qtypes are restorable

        // Precheck all qcats and qs looking for target contexts / warnings / errors
        list($syserr, $syswarn) = self::prechek_precheck_qbanks_by_level($restoreid, $courseid, $userid, $samesite, CONTEXT_SYSTEM);
        list($caterr, $catwarn) = self::prechek_precheck_qbanks_by_level($restoreid, $courseid, $userid, $samesite, CONTEXT_COURSECAT);
        list($couerr, $couwarn) = self::prechek_precheck_qbanks_by_level($restoreid, $courseid, $userid, $samesite, CONTEXT_COURSE);
        list($moderr, $modwarn) = self::prechek_precheck_qbanks_by_level($restoreid, $courseid, $userid, $samesite, CONTEXT_MODULE);

        // Acummulate and handle errors and warnings
        $errors   = array_merge($syserr, $caterr, $couerr, $moderr);
        $warnings = array_merge($syswarn, $catwarn, $couwarn, $modwarn);
        if (!empty($errors)) {
            $problems['errors'] = $errors;
        }
        if (!empty($warnings)) {
            $problems['warnings'] = $warnings;
        }
        return $problems;
    }

    /**
     * This function will process all the question banks present in restore
     * at some contextlevel (from CONTEXT_SYSTEM to CONTEXT_MODULE), finding
     * the target contexts where each bank will be restored and returning
     * warnings/errors as needed.
     *
     * Some contextlevels (system, coursecat), will delegate process to
     * course level if any problem is found (lack of permissions, non-matching
     * target context...). Other contextlevels (course, module) will
     * cause return error if some problem is found.
     *
     * At the end, if no errors were found, all the categories in backup_temp_ids
     * will be pointing (parentitemid) to the target context where they must be
     * created later in the restore process.
     *
     * Note: at the time these prechecks are executed, activities haven't been
     * created yet so, for CONTEXT_MODULE banks, we keep the old contextid
     * in the parentitemid field. Once the activity (and its context) has been
     * created, we'll update that context in the required qcats
     *
     * Caller {@link precheck_categories_and_questions} will, simply, execute
     * this function for all the contextlevels, acting as a simple controller
     * of warnings and errors.
     *
     * The function returns 2 arrays, one containing errors and another containing
     * warnings. Both empty if no errors/warnings are found.
     */
    public static function prechek_precheck_qbanks_by_level($restoreid, $courseid, $userid, $samesite, $contextlevel) {
        global $CFG, $DB;

        // To return any errors and warnings found
        $errors   = array();
        $warnings = array();

        // Specify which fallbacks must be performed
        $fallbacks = array(
            CONTEXT_SYSTEM => CONTEXT_COURSE,
            CONTEXT_COURSECAT => CONTEXT_COURSE);

        // For any contextlevel, follow this process logic:
        //
        // 0) Iterate over each context (qbank)
        // 1) Iterate over each qcat in the context, matching by stamp for the found target context
        //     2a) No match, check if user can create qcat and q
        //         3a) User can, mark the qcat and all dependent qs to be created in that target context
        //         3b) User cannot, check if we are in some contextlevel with fallback
        //             4a) There is fallback, move ALL the qcats to fallback, warn. End qcat loop
        //             4b) No fallback, error. End qcat loop.
        //     2b) Match, mark qcat to be mapped and iterate over each q, matching by stamp and version
        //         5a) No match, check if user can add q
        //             6a) User can, mark the q to be created
        //             6b) User cannot, check if we are in some contextlevel with fallback
        //                 7a) There is fallback, move ALL the qcats to fallback, warn. End qcat loop
        //                 7b) No fallback, error. End qcat loop
        //         5b) Match, mark q to be mapped

        // Get all the contexts (question banks) in restore for the given contextlevel
        $contexts = self::restore_get_question_banks($restoreid, $contextlevel);

        // 0) Iterate over each context (qbank)
        foreach ($contexts as $contextid => $contextlevel) {
            // Init some perms
            $canmanagecategory = false;
            $canadd            = false;
            // get categories in context (bank)
            $categories = self::restore_get_question_categories($restoreid, $contextid);
            // cache permissions if $targetcontext is found
            if ($targetcontext = self::restore_find_best_target_context($categories, $courseid, $contextlevel)) {
                $canmanagecategory = has_capability('moodle/question:managecategory', $targetcontext, $userid);
                $canadd            = has_capability('moodle/question:add', $targetcontext, $userid);
            }
            // 1) Iterate over each qcat in the context, matching by stamp for the found target context
            foreach ($categories as $category) {
                $matchcat = false;
                if ($targetcontext) {
                    $matchcat = $DB->get_record('question_categories', array(
                                    'contextid' => $targetcontext->id,
                                    'stamp' => $category->stamp));
                }
                // 2a) No match, check if user can create qcat and q
                if (!$matchcat) {
                    // 3a) User can, mark the qcat and all dependent qs to be created in that target context
                    if ($canmanagecategory && $canadd) {
                        // Set parentitemid to targetcontext, BUT for CONTEXT_MODULE categories, where
                        // we keep the source contextid unmodified (for easier matching later when the
                        // activities are created)
                        $parentitemid = $targetcontext->id;
                        if ($contextlevel == CONTEXT_MODULE) {
                            $parentitemid = null; // null means "not modify" a.k.a. leave original contextid
                        }
                        self::set_backup_ids_record($restoreid, 'question_category', $category->id, 0, $parentitemid);
                        // Nothing else to mark, newitemid = 0 means create

                    // 3b) User cannot, check if we are in some contextlevel with fallback
                    } else {
                        // 4a) There is fallback, move ALL the qcats to fallback, warn. End qcat loop
                        if (array_key_exists($contextlevel, $fallbacks)) {
                            foreach ($categories as $movedcat) {
                                $movedcat->contextlevel = $fallbacks[$contextlevel];
                                self::set_backup_ids_record($restoreid, 'question_category', $movedcat->id, 0, $contextid, $movedcat);
                                // Warn about the performed fallback
                                $warnings[] = get_string('qcategory2coursefallback', 'backup', $movedcat);
                            }

                        // 4b) No fallback, error. End qcat loop.
                        } else {
                            $errors[] = get_string('qcategorycannotberestored', 'backup', $category);
                        }
                        break; // out from qcat loop (both 4a and 4b), we have decided about ALL categories in context (bank)
                    }

                // 2b) Match, mark qcat to be mapped and iterate over each q, matching by stamp and version
                } else {
                    self::set_backup_ids_record($restoreid, 'question_category', $category->id, $matchcat->id, $targetcontext->id);
                    $questions = self::restore_get_questions($restoreid, $category->id);
                    foreach ($questions as $question) {
                        $matchq = $DB->get_record('question', array(
                                      'category' => $matchcat->id,
                                      'stamp' => $question->stamp,
                                      'version' => $question->version));
                        // 5a) No match, check if user can add q
                        if (!$matchq) {
                            // 6a) User can, mark the q to be created
                            if ($canadd) {
                                // Nothing to mark, newitemid means create

                             // 6b) User cannot, check if we are in some contextlevel with fallback
                            } else {
                                // 7a) There is fallback, move ALL the qcats to fallback, warn. End qcat loo
                                if (array_key_exists($contextlevel, $fallbacks)) {
                                    foreach ($categories as $movedcat) {
                                        $movedcat->contextlevel = $fallbacks[$contextlevel];
                                        self::set_backup_ids_record($restoreid, 'question_category', $movedcat->id, 0, $contextid, $movedcat);
                                        // Warn about the performed fallback
                                        $warnings[] = get_string('question2coursefallback', 'backup', $movedcat);
                                    }

                                // 7b) No fallback, error. End qcat loop
                                } else {
                                    $errors[] = get_string('questioncannotberestored', 'backup', $question);
                                }
                                break 2; // out from qcat loop (both 7a and 7b), we have decided about ALL categories in context (bank)
                            }

                        // 5b) Match, mark q to be mapped
                        } else {
                            self::set_backup_ids_record($restoreid, 'question', $question->id, $matchq->id);
                        }
                    }
                }
            }
        }

        return array($errors, $warnings);
    }

    /**
     * Return one array of contextid => contextlevel pairs
     * of question banks to be checked for one given restore operation
     * ordered from CONTEXT_SYSTEM downto CONTEXT_MODULE
     * If contextlevel is specified, then only banks corresponding to
     * that level are returned
     */
    public static function restore_get_question_banks($restoreid, $contextlevel = null) {
        global $DB;

        $results = array();
        $qcats = $DB->get_records_sql("SELECT itemid, parentitemid AS contextid
                                         FROM {backup_ids_temp}
                                       WHERE backupid = ?
                                         AND itemname = 'question_category'", array($restoreid));
        foreach ($qcats as $qcat) {
            // If this qcat context haven't been acummulated yet, do that
            if (!isset($results[$qcat->contextid])) {
                $temprec = self::get_backup_ids_record($restoreid, 'question_category', $qcat->itemid);
                // Filter by contextlevel if necessary
                if (is_null($contextlevel) || $contextlevel == $temprec->info->contextlevel) {
                    $results[$qcat->contextid] = $temprec->info->contextlevel;
                }
            }
        }
        // Sort by value (contextlevel from CONTEXT_SYSTEM downto CONTEXT_MODULE)
        asort($results);
        return $results;
    }

    /**
     * Return one array of question_category records for
     * a given restore operation and one restore context (question bank)
     */
    public static function restore_get_question_categories($restoreid, $contextid) {
        global $DB;

        $results = array();
        $qcats = $DB->get_records_sql("SELECT itemid
                                         FROM {backup_ids_temp}
                                        WHERE backupid = ?
                                          AND itemname = 'question_category'
                                          AND parentitemid = ?", array($restoreid, $contextid));
        foreach ($qcats as $qcat) {
            $temprec = self::get_backup_ids_record($restoreid, 'question_category', $qcat->itemid);
            $results[$qcat->itemid] = $temprec->info;
        }
        return $results;
    }

    /**
     * Calculates the best context found to restore one collection of qcats,
     * al them belonging to the same context (question bank), returning the
     * target context found (object) or false
     */
    public static function restore_find_best_target_context($categories, $courseid, $contextlevel) {
        global $DB;

        $targetcontext = false;

        // Depending of $contextlevel, we perform different actions
        switch ($contextlevel) {
             // For system is easy, the best context is the system context
             case CONTEXT_SYSTEM:
                 $targetcontext = get_context_instance(CONTEXT_SYSTEM);
                 break;

             // For coursecat, we are going to look for stamps in all the
             // course categories between CONTEXT_SYSTEM and CONTEXT_COURSE
             // (i.e. in all the course categories in the path)
             //
             // And only will return one "best" target context if all the
             // matches belong to ONE and ONLY ONE context. If multiple
             // matches are found, that means that there is some annoying
             // qbank "fragmentation" in the categories, so we'll fallback
             // to create the qbank at course level
             case CONTEXT_COURSECAT:
                 // Build the array of stamps we are going to match
                 $stamps = array();
                 foreach ($categories as $category) {
                     $stamps[] = $category->stamp;
                 }
                 $contexts = array();
                 // Build the array of contexts we are going to look
                 $systemctx = get_context_instance(CONTEXT_SYSTEM);
                 $coursectx = get_context_instance(CONTEXT_COURSE, $courseid);
                 $parentctxs= get_parent_contexts($coursectx);
                 foreach ($parentctxs as $parentctx) {
                     // Exclude system context
                     if ($parentctx == $systemctx->id) {
                         continue;
                     }
                     $contexts[] = $parentctx;
                 }
                 if (!empty($stamps) && !empty($contexts)) {
                     // Prepare the query
                     list($stamp_sql, $stamp_params) = $DB->get_in_or_equal($stamps);
                     list($context_sql, $context_params) = $DB->get_in_or_equal($contexts);
                     $sql = "SELECT contextid
                               FROM {question_categories}
                              WHERE stamp $stamp_sql
                                AND contextid $context_sql";
                     $params = array_merge($stamp_params, $context_params);
                     $matchingcontexts = $DB->get_records_sql($sql, $params);
                     // Only if ONE and ONLY ONE context is found, use it as valid target
                     if (count($matchingcontexts) == 1) {
                         $targetcontext = get_context_instance_by_id(reset($matchingcontexts)->contextid);
                     }
                 }
                 break;

             // For course is easy, the best context is the course context
             case CONTEXT_COURSE:
                 $targetcontext = get_context_instance(CONTEXT_COURSE, $courseid);
                 break;

             // For module is easy, there is not best context, as far as the
             // activity hasn't been created yet. So we return context course
             // for them, so permission checks and friends will work. Note this
             // case is handled by {@link prechek_precheck_qbanks_by_level}
             // in an special way
             case CONTEXT_MODULE:
                 $targetcontext = get_context_instance(CONTEXT_COURSE, $courseid);
                 break;
        }
        return $targetcontext;
    }

    /**
     * Return one array of question records for
     * a given restore operation and one question category
     */
    public static function restore_get_questions($restoreid, $qcatid) {
        global $DB;

        $results = array();
        $qs = $DB->get_records_sql("SELECT itemid
                                      FROM {backup_ids_temp}
                                     WHERE backupid = ?
                                       AND itemname = 'question'
                                       AND parentitemid = ?", array($restoreid, $qcatid));
        foreach ($qs as $q) {
            $temprec = self::get_backup_ids_record($restoreid, 'question', $q->itemid);
            $results[$q->itemid] = $temprec->info;
        }
        return $results;
    }

    /**
     * Given one component/filearea/context and
     * optionally one source itemname to match itemids
     * put the corresponding files in the pool
     *
     * @param string $basepath the full path to the root of unzipped backup file
     * @param string $restoreid the restore job's identification
     * @param string $component
     * @param string $filearea
     * @param int $oldcontextid
     * @param int $dfltuserid default $file->user if the old one can't be mapped
     * @param string|null $itemname
     * @param int|null $olditemid
     * @param int|null $forcenewcontextid explicit value for the new contextid (skip mapping)
     * @param bool $skipparentitemidctxmatch
     * @return array of result object
     */
    public static function send_files_to_pool($basepath, $restoreid, $component, $filearea, $oldcontextid, $dfltuserid, $itemname = null, $olditemid = null, $forcenewcontextid = null, $skipparentitemidctxmatch = false) {
        global $DB;

        $results = array();

        if ($forcenewcontextid) {
            // Some components can have "forced" new contexts (example: questions can end belonging to non-standard context mappings,
            // with questions originally at system/coursecat context in source being restored to course context in target). So we need
            // to be able to force the new contextid
            $newcontextid = $forcenewcontextid;
        } else {
            // Get new context, must exist or this will fail
            if (!$newcontextid = self::get_backup_ids_record($restoreid, 'context', $oldcontextid)->newitemid) {
                throw new restore_dbops_exception('unknown_context_mapping', $oldcontextid);
            }
        }

        // Sometimes it's possible to have not the oldcontextids stored into backup_ids_temp->parentitemid
        // columns (because we have used them to store other information). This happens usually with
        // all the question related backup_ids_temp records. In that case, it's safe to ignore that
        // matching as far as we are always restoring for well known oldcontexts and olditemids
        $parentitemctxmatchsql = ' AND i.parentitemid = f.contextid ';
        if ($skipparentitemidctxmatch) {
            $parentitemctxmatchsql = '';
        }

        // Important: remember how files have been loaded to backup_files_temp
        //   - info: contains the whole original object (times, names...)
        //   (all them being original ids as loaded from xml)

        // itemname = null, we are going to match only by context, no need to use itemid (all them are 0)
        if ($itemname == null) {
            $sql = 'SELECT contextid, component, filearea, itemid, itemid AS newitemid, info
                      FROM {backup_files_temp}
                     WHERE backupid = ?
                       AND contextid = ?
                       AND component = ?
                       AND filearea  = ?';
            $params = array($restoreid, $oldcontextid, $component, $filearea);

        // itemname not null, going to join with backup_ids to perform the old-new mapping of itemids
        } else {
            $sql = "SELECT f.contextid, f.component, f.filearea, f.itemid, i.newitemid, f.info
                      FROM {backup_files_temp} f
                      JOIN {backup_ids_temp} i ON i.backupid = f.backupid
                                              $parentitemctxmatchsql
                                              AND i.itemid = f.itemid
                     WHERE f.backupid = ?
                       AND f.contextid = ?
                       AND f.component = ?
                       AND f.filearea = ?
                       AND i.itemname = ?";
            $params = array($restoreid, $oldcontextid, $component, $filearea, $itemname);
            if ($olditemid !== null) { // Just process ONE olditemid intead of the whole itemname
                $sql .= ' AND i.itemid = ?';
                $params[] = $olditemid;
            }
        }

        $fs = get_file_storage();         // Get moodle file storage
        $basepath = $basepath . '/files/';// Get backup file pool base
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $rec) {
            $file = (object)unserialize(base64_decode($rec->info));
            // ignore root dirs (they are created automatically)
            if ($file->filepath == '/' && $file->filename == '.') {
                continue;
            }
            // set the best possible user
            $mappeduser = self::get_backup_ids_record($restoreid, 'user', $file->userid);
            $file->userid = !empty($mappeduser) ? $mappeduser->newitemid : $dfltuserid;
            // dir found (and not root one), let's create if
            if ($file->filename == '.') {
                $fs->create_directory($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $file->userid);
                continue;
            }
            // arrived here, file found
            // Find file in backup pool
            $backuppath = $basepath . backup_file_manager::get_backup_content_file_location($file->contenthash);
            if (!file_exists($backuppath)) {
                $result = new stdClass();
                $result->code = 'file_missing_in_backup';
                $result->message = sprintf('missing file %s%s in backup', $file->filepath, $file->filename);
                $result->level = backup::LOG_WARNING;
                $results[] = $result;
                continue;
            }
            if (!$fs->file_exists($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $file->filename)) {
                $file_record = array(
                    'contextid'   => $newcontextid,
                    'component'   => $component,
                    'filearea'    => $filearea,
                    'itemid'      => $rec->newitemid,
                    'filepath'    => $file->filepath,
                    'filename'    => $file->filename,
                    'timecreated' => $file->timecreated,
                    'timemodified'=> $file->timemodified,
                    'userid'      => $file->userid,
                    'author'      => $file->author,
                    'license'     => $file->license,
                    'sortorder'   => $file->sortorder);
                $fs->create_file_from_pathname($file_record, $backuppath);
            }
        }
        $rs->close();
        return $results;
    }

    /**
     * Given one restoreid, create in DB all the users present
     * in backup_ids having newitemid = 0, as far as
     * precheck_included_users() have left them there
     * ready to be created. Also, annotate their newids
     * once created for later reference
     */
    public static function create_included_users($basepath, $restoreid, $userid) {
        global $CFG, $DB;

        $authcache = array(); // Cache to get some bits from authentication plugins
        $languages = get_string_manager()->get_list_of_translations(); // Get languages for quick search later
        $themes    = get_list_of_themes(); // Get themes for quick search later

        // Iterate over all the included users with newitemid = 0, have to create them
        $rs = $DB->get_recordset('backup_ids_temp', array('backupid' => $restoreid, 'itemname' => 'user', 'newitemid' => 0), '', 'itemid, parentitemid');
        foreach ($rs as $recuser) {
            $user = (object)self::get_backup_ids_record($restoreid, 'user', $recuser->itemid)->info;

            // if user lang doesn't exist here, use site default
            if (!array_key_exists($user->lang, $languages)) {
                $user->lang = $CFG->lang;
            }

            // if user theme isn't available on target site or they are disabled, reset theme
            if (!empty($user->theme)) {
                if (empty($CFG->allowuserthemes) || !in_array($user->theme, $themes)) {
                    $user->theme = '';
                }
            }

            // if user to be created has mnet auth and its mnethostid is $CFG->mnet_localhost_id
            // that's 100% impossible as own server cannot be accesed over mnet. Change auth to email/manual
            if ($user->auth == 'mnet' && $user->mnethostid == $CFG->mnet_localhost_id) {
                // Respect registerauth
                if ($CFG->registerauth == 'email') {
                    $user->auth = 'email';
                } else {
                    $user->auth = 'manual';
                }
            }
            unset($user->mnethosturl); // Not needed anymore

            // Disable pictures based on global setting
            if (!empty($CFG->disableuserimages)) {
                $user->picture = 0;
            }

            // We need to analyse the AUTH field to recode it:
            //   - if the auth isn't enabled in target site, $CFG->registerauth will decide
            //   - finally, if the auth resulting isn't enabled, default to 'manual'
            if (!is_enabled_auth($user->auth)) {
                if ($CFG->registerauth == 'email') {
                    $user->auth = 'email';
                } else {
                    $user->auth = 'manual';
                }
            }
            if (!is_enabled_auth($user->auth)) { // Final auth check verify, default to manual if not enabled
                $user->auth = 'manual';
            }

            // Now that we know the auth method, for users to be created without pass
            // if password handling is internal and reset password is available
            // we set the password to "restored" (plain text), so the login process
            // will know how to handle that situation in order to allow the user to
            // recover the password. MDL-20846
            if (empty($user->password)) { // Only if restore comes without password
                if (!array_key_exists($user->auth, $authcache)) { // Not in cache
                    $userauth = new stdClass();
                    $authplugin = get_auth_plugin($user->auth);
                    $userauth->preventpassindb = $authplugin->prevent_local_passwords();
                    $userauth->isinternal      = $authplugin->is_internal();
                    $userauth->canresetpwd     = $authplugin->can_reset_password();
                    $authcache[$user->auth] = $userauth;
                } else {
                    $userauth = $authcache[$user->auth]; // Get from cache
                }

                // Most external plugins do not store passwords locally
                if (!empty($userauth->preventpassindb)) {
                    $user->password = 'not cached';

                // If Moodle is responsible for storing/validating pwd and reset functionality is available, mark
                } else if ($userauth->isinternal and $userauth->canresetpwd) {
                    $user->password = 'restored';
                }
            }

            // Creating new user, we must reset the policyagreed always
            $user->policyagreed = 0;

            // Set time created if empty
            if (empty($user->timecreated)) {
                $user->timecreated = time();
            }

            // Done, let's create the user and annotate its id
            $newuserid = $DB->insert_record('user', $user);
            self::set_backup_ids_record($restoreid, 'user', $recuser->itemid, $newuserid);
            // Let's create the user context and annotate it (we need it for sure at least for files)
            // but for deleted users that don't have a context anymore (MDL-30192). We are done for them
            // and nothing else (custom fields, prefs, tags, files...) will be created.
            if (empty($user->deleted)) {
                $newuserctxid = $user->deleted ? 0 : get_context_instance(CONTEXT_USER, $newuserid)->id;
                self::set_backup_ids_record($restoreid, 'context', $recuser->parentitemid, $newuserctxid);

                // Process custom fields
                if (isset($user->custom_fields)) { // if present in backup
                    foreach($user->custom_fields['custom_field'] as $udata) {
                        $udata = (object)$udata;
                        // If the profile field has data and the profile shortname-datatype is defined in server
                        if ($udata->field_data) {
                            if ($field = $DB->get_record('user_info_field', array('shortname'=>$udata->field_name, 'datatype'=>$udata->field_type))) {
                            /// Insert the user_custom_profile_field
                                $rec = new stdClass();
                                $rec->userid  = $newuserid;
                                $rec->fieldid = $field->id;
                                $rec->data    = $udata->field_data;
                                $DB->insert_record('user_info_data', $rec);
                            }
                        }
                    }
                }

                // Process tags
                if (!empty($CFG->usetags) && isset($user->tags)) { // if enabled in server and present in backup
                    $tags = array();
                    foreach($user->tags['tag'] as $usertag) {
                        $usertag = (object)$usertag;
                        $tags[] = $usertag->rawname;
                    }
                    tag_set('user', $newuserid, $tags);
                }

                // Process preferences
                if (isset($user->preferences)) { // if present in backup
                    foreach($user->preferences['preference'] as $preference) {
                        $preference = (object)$preference;
                        // Prepare the record and insert it
                        $preference->userid = $newuserid;
                        $status = $DB->insert_record('user_preferences', $preference);
                    }
                }

                // Create user files in pool (profile, icon, private) by context
                restore_dbops::send_files_to_pool($basepath, $restoreid, 'user', 'icon', $recuser->parentitemid, $userid);
                restore_dbops::send_files_to_pool($basepath, $restoreid, 'user', 'profile', $recuser->parentitemid, $userid);
            }
        }
        $rs->close();
    }

    /**
    * Given one user object (from backup file), perform all the neccesary
    * checks is order to decide how that user will be handled on restore.
    *
    * Note the function requires $user->mnethostid to be already calculated
    * so it's caller responsibility to set it
    *
    * This function is used both by @restore_precheck_users() and
    * @restore_create_users() to get consistent results in both places
    *
    * It returns:
    *   - one user object (from DB), if match has been found and user will be remapped
    *   - boolean true if the user needs to be created
    *   - boolean false if some conflict happened and the user cannot be handled
    *
    * Each test is responsible for returning its results and interrupt
    * execution. At the end, boolean true (user needs to be created) will be
    * returned if no test has interrupted that.
    *
    * Here it's the logic applied, keep it updated:
    *
    *  If restoring users from same site backup:
    *      1A - Normal check: If match by id and username and mnethost  => ok, return target user
    *      1B - Handle users deleted in DB and "alive" in backup file:
    *           If match by id and mnethost and user is deleted in DB and
    *           (match by username LIKE 'backup_email.%' or by non empty email = md5(username)) => ok, return target user
    *      1C - Handle users deleted in backup file and "alive" in DB:
    *           If match by id and mnethost and user is deleted in backup file
    *           and match by email = email_without_time(backup_email) => ok, return target user
    *      1D - Conflict: If match by username and mnethost and doesn't match by id => conflict, return false
    *      1E - None of the above, return true => User needs to be created
    *
    *  if restoring from another site backup (cannot match by id here, replace it by email/firstaccess combination):
    *      2A - Normal check: If match by username and mnethost and (email or non-zero firstaccess) => ok, return target user
    *      2B - Handle users deleted in DB and "alive" in backup file:
    *           2B1 - If match by mnethost and user is deleted in DB and not empty email = md5(username) and
    *                 (username LIKE 'backup_email.%' or non-zero firstaccess) => ok, return target user
    *           2B2 - If match by mnethost and user is deleted in DB and
    *                 username LIKE 'backup_email.%' and non-zero firstaccess) => ok, return target user
    *                 (to cover situations were md5(username) wasn't implemented on delete we requiere both)
    *      2C - Handle users deleted in backup file and "alive" in DB:
    *           If match mnethost and user is deleted in backup file
    *           and by email = email_without_time(backup_email) and non-zero firstaccess=> ok, return target user
    *      2D - Conflict: If match by username and mnethost and not by (email or non-zero firstaccess) => conflict, return false
    *      2E - None of the above, return true => User needs to be created
    *
    * Note: for DB deleted users email is stored in username field, hence we
    *       are looking there for emails. See delete_user()
    * Note: for DB deleted users md5(username) is stored *sometimes* in the email field,
    *       hence we are looking there for usernames if not empty. See delete_user()
    */
    protected static function precheck_user($user, $samesite) {
        global $CFG, $DB;

        // Handle checks from same site backups
        if ($samesite && empty($CFG->forcedifferentsitecheckingusersonrestore)) {

            // 1A - If match by id and username and mnethost => ok, return target user
            if ($rec = $DB->get_record('user', array('id'=>$user->id, 'username'=>$user->username, 'mnethostid'=>$user->mnethostid))) {
                return $rec; // Matching user found, return it
            }

            // 1B - Handle users deleted in DB and "alive" in backup file
            // Note: for DB deleted users email is stored in username field, hence we
            //       are looking there for emails. See delete_user()
            // Note: for DB deleted users md5(username) is stored *sometimes* in the email field,
            //       hence we are looking there for usernames if not empty. See delete_user()
            // If match by id and mnethost and user is deleted in DB and
            // match by username LIKE 'backup_email.%' or by non empty email = md5(username) => ok, return target user
            if ($rec = $DB->get_record_sql("SELECT *
                                              FROM {user} u
                                             WHERE id = ?
                                               AND mnethostid = ?
                                               AND deleted = 1
                                               AND (
                                                       UPPER(username) LIKE UPPER(?)
                                                    OR (
                                                           ".$DB->sql_isnotempty('user', 'email', false, false)."
                                                       AND email = ?
                                                       )
                                                   )",
                                           array($user->id, $user->mnethostid, $user->email.'.%', md5($user->username)))) {
                return $rec; // Matching user, deleted in DB found, return it
            }

            // 1C - Handle users deleted in backup file and "alive" in DB
            // If match by id and mnethost and user is deleted in backup file
            // and match by email = email_without_time(backup_email) => ok, return target user
            if ($user->deleted) {
                // Note: for DB deleted users email is stored in username field, hence we
                //       are looking there for emails. See delete_user()
                // Trim time() from email
                $trimemail = preg_replace('/(.*?)\.[0-9]+.?$/', '\\1', $user->username);
                if ($rec = $DB->get_record_sql("SELECT *
                                                  FROM {user} u
                                                 WHERE id = ?
                                                   AND mnethostid = ?
                                                   AND UPPER(email) = UPPER(?)",
                                               array($user->id, $user->mnethostid, $trimemail))) {
                    return $rec; // Matching user, deleted in backup file found, return it
                }
            }

            // 1D - If match by username and mnethost and doesn't match by id => conflict, return false
            if ($rec = $DB->get_record('user', array('username'=>$user->username, 'mnethostid'=>$user->mnethostid))) {
                if ($user->id != $rec->id) {
                    return false; // Conflict, username already exists and belongs to another id
                }
            }

        // Handle checks from different site backups
        } else {

            // 2A - If match by username and mnethost and
            //     (email or non-zero firstaccess) => ok, return target user
            if ($rec = $DB->get_record_sql("SELECT *
                                              FROM {user} u
                                             WHERE username = ?
                                               AND mnethostid = ?
                                               AND (
                                                       UPPER(email) = UPPER(?)
                                                    OR (
                                                           firstaccess != 0
                                                       AND firstaccess = ?
                                                       )
                                                   )",
                                           array($user->username, $user->mnethostid, $user->email, $user->firstaccess))) {
                return $rec; // Matching user found, return it
            }

            // 2B - Handle users deleted in DB and "alive" in backup file
            // Note: for DB deleted users email is stored in username field, hence we
            //       are looking there for emails. See delete_user()
            // Note: for DB deleted users md5(username) is stored *sometimes* in the email field,
            //       hence we are looking there for usernames if not empty. See delete_user()
            // 2B1 - If match by mnethost and user is deleted in DB and not empty email = md5(username) and
            //       (by username LIKE 'backup_email.%' or non-zero firstaccess) => ok, return target user
            if ($rec = $DB->get_record_sql("SELECT *
                                              FROM {user} u
                                             WHERE mnethostid = ?
                                               AND deleted = 1
                                               AND ".$DB->sql_isnotempty('user', 'email', false, false)."
                                               AND email = ?
                                               AND (
                                                       UPPER(username) LIKE UPPER(?)
                                                    OR (
                                                           firstaccess != 0
                                                       AND firstaccess = ?
                                                       )
                                                   )",
                                           array($user->mnethostid, md5($user->username), $user->email.'.%', $user->firstaccess))) {
                return $rec; // Matching user found, return it
            }

            // 2B2 - If match by mnethost and user is deleted in DB and
            //       username LIKE 'backup_email.%' and non-zero firstaccess) => ok, return target user
            //       (this covers situations where md5(username) wasn't being stored so we require both
            //        the email & non-zero firstaccess to match)
            if ($rec = $DB->get_record_sql("SELECT *
                                              FROM {user} u
                                             WHERE mnethostid = ?
                                               AND deleted = 1
                                               AND UPPER(username) LIKE UPPER(?)
                                               AND firstaccess != 0
                                               AND firstaccess = ?",
                                           array($user->mnethostid, $user->email.'.%', $user->firstaccess))) {
                return $rec; // Matching user found, return it
            }

            // 2C - Handle users deleted in backup file and "alive" in DB
            // If match mnethost and user is deleted in backup file
            // and match by email = email_without_time(backup_email) and non-zero firstaccess=> ok, return target user
            if ($user->deleted) {
                // Note: for DB deleted users email is stored in username field, hence we
                //       are looking there for emails. See delete_user()
                // Trim time() from email
                $trimemail = preg_replace('/(.*?)\.[0-9]+.?$/', '\\1', $user->username);
                if ($rec = $DB->get_record_sql("SELECT *
                                                  FROM {user} u
                                                 WHERE mnethostid = ?
                                                   AND UPPER(email) = UPPER(?)
                                                   AND firstaccess != 0
                                                   AND firstaccess = ?",
                                               array($user->mnethostid, $trimemail, $user->firstaccess))) {
                    return $rec; // Matching user, deleted in backup file found, return it
                }
            }

            // 2D - If match by username and mnethost and not by (email or non-zero firstaccess) => conflict, return false
            if ($rec = $DB->get_record_sql("SELECT *
                                              FROM {user} u
                                             WHERE username = ?
                                               AND mnethostid = ?
                                           AND NOT (
                                                       UPPER(email) = UPPER(?)
                                                    OR (
                                                           firstaccess != 0
                                                       AND firstaccess = ?
                                                       )
                                                   )",
                                           array($user->username, $user->mnethostid, $user->email, $user->firstaccess))) {
                return false; // Conflict, username/mnethostid already exist and belong to another user (by email/firstaccess)
            }
        }

        // Arrived here, return true as the user will need to be created and no
        // conflicts have been found in the logic above. This covers:
        // 1E - else => user needs to be created, return true
        // 2E - else => user needs to be created, return true
        return true;
    }

    /**
     * Check all the included users, deciding the action to perform
     * for each one (mapping / creation) and returning one array
     * of problems in case something is wrong (lack of permissions,
     * conficts)
     */
    public static function precheck_included_users($restoreid, $courseid, $userid, $samesite) {
        global $CFG, $DB;

        // To return any problem found
        $problems = array();

        // We are going to map mnethostid, so load all the available ones
        $mnethosts = $DB->get_records('mnet_host', array(), 'wwwroot', 'wwwroot, id');

        // Calculate the context we are going to use for capability checking
        $context = get_context_instance(CONTEXT_COURSE, $courseid);

        // Calculate if we have perms to create users, by checking:
        // to 'moodle/restore:createuser' and 'moodle/restore:userinfo'
        // and also observe $CFG->disableusercreationonrestore
        $cancreateuser = false;
        if (has_capability('moodle/restore:createuser', $context, $userid) and
            has_capability('moodle/restore:userinfo', $context, $userid) and
            empty($CFG->disableusercreationonrestore)) { // Can create users

            $cancreateuser = true;
        }

        // Iterate over all the included users
        $rs = $DB->get_recordset('backup_ids_temp', array('backupid' => $restoreid, 'itemname' => 'user'), '', 'itemid');
        foreach ($rs as $recuser) {
            $user = (object)self::get_backup_ids_record($restoreid, 'user', $recuser->itemid)->info;

            // Find the correct mnethostid for user before performing any further check
            if (empty($user->mnethosturl) || $user->mnethosturl === $CFG->wwwroot) {
                $user->mnethostid = $CFG->mnet_localhost_id;
            } else {
                // fast url-to-id lookups
                if (isset($mnethosts[$user->mnethosturl])) {
                    $user->mnethostid = $mnethosts[$user->mnethosturl]->id;
                } else {
                    $user->mnethostid = $CFG->mnet_localhost_id;
                }
            }

            // Now, precheck that user and, based on returned results, annotate action/problem
            $usercheck = self::precheck_user($user, $samesite);

            if (is_object($usercheck)) { // No problem, we have found one user in DB to be mapped to
                // Annotate it, for later process. Set newitemid to mapping user->id
                self::set_backup_ids_record($restoreid, 'user', $recuser->itemid, $usercheck->id);

            } else if ($usercheck === false) { // Found conflict, report it as problem
                 $problems[] = get_string('restoreuserconflict', '', $user->username);

            } else if ($usercheck === true) { // User needs to be created, check if we are able
                if ($cancreateuser) { // Can create user, set newitemid to 0 so will be created later
                    self::set_backup_ids_record($restoreid, 'user', $recuser->itemid, 0, null, (array)$user);

                } else { // Cannot create user, report it as problem
                    $problems[] = get_string('restorecannotcreateuser', '', $user->username);
                }

            } else { // Shouldn't arrive here ever, something is for sure wrong. Exception
                throw new restore_dbops_exception('restore_error_processing_user', $user->username);
            }
        }
        $rs->close();
        return $problems;
    }

    /**
     * Process the needed users in order to decide
     * which action to perform with them (create/map)
     *
     * Just wrap over precheck_included_users(), returning
     * exception if any problem is found
     */
    public static function process_included_users($restoreid, $courseid, $userid, $samesite) {
        global $DB;

        // Just let precheck_included_users() to do all the hard work
        $problems = self::precheck_included_users($restoreid, $courseid, $userid, $samesite);

        // With problems, throw exception, shouldn't happen if prechecks were originally
        // executed, so be radical here.
        if (!empty($problems)) {
            throw new restore_dbops_exception('restore_problems_processing_users', null, implode(', ', $problems));
        }
    }

    /**
     * Process the needed question categories and questions
     * to check all them, deciding about the action to perform
     * (create/map) and target.
     *
     * Just wrap over precheck_categories_and_questions(), returning
     * exception if any problem is found
     */
    public static function process_categories_and_questions($restoreid, $courseid, $userid, $samesite) {
        global $DB;

        // Just let precheck_included_users() to do all the hard work
        $problems = self::precheck_categories_and_questions($restoreid, $courseid, $userid, $samesite);

        // With problems of type error, throw exception, shouldn't happen if prechecks were originally
        // executed, so be radical here.
        if (array_key_exists('errors', $problems)) {
            throw new restore_dbops_exception('restore_problems_processing_questions', null, implode(', ', $problems['errors']));
        }
    }

    public static function set_backup_files_record($restoreid, $filerec) {
        global $DB;

        $filerec->info     = base64_encode(serialize($filerec)); // Serialize the whole rec in info
        $filerec->backupid = $restoreid;
        $DB->insert_record('backup_files_temp', $filerec);
    }


    public static function set_backup_ids_record($restoreid, $itemname, $itemid, $newitemid = 0, $parentitemid = null, $info = null) {
        global $DB;

        // Build the basic (mandatory) record info
        $record = array(
            'backupid' => $restoreid,
            'itemname' => $itemname,
            'itemid'   => $itemid
        );
        // Build conditionally the extra record info
        $extrarecord = array();
        if ($newitemid != 0) {
            $extrarecord['newitemid'] = $newitemid;
        }
        if ($parentitemid != null) {
            $extrarecord['parentitemid'] = $parentitemid;
        }
        if ($info != null) {
            $extrarecord['info'] = base64_encode(serialize($info));
        }

        // TODO: Analyze if some static (and limited) cache by the 3 params could save us a bunch of get_record() calls
        // Note: Sure it will! And also will improve getter
        if (!$dbrec = $DB->get_record('backup_ids_temp', $record)) { // Need to insert the complete record
            $DB->insert_record('backup_ids_temp', array_merge($record, $extrarecord));

        } else { // Need to update the extra record info if there is something to
            if (!empty($extrarecord)) {
                $extrarecord['id'] = $dbrec->id;
                $DB->update_record('backup_ids_temp', $extrarecord);
            }
        }
    }

    public static function get_backup_ids_record($restoreid, $itemname, $itemid) {
        global $DB;

        // Build the basic (mandatory) record info to look for
        $record = array(
            'backupid' => $restoreid,
            'itemname' => $itemname,
            'itemid'   => $itemid
        );
        // TODO: Analyze if some static (and limited) cache by the 3 params could save us a bunch of get_record() calls
        if ($dbrec = $DB->get_record('backup_ids_temp', $record)) {
            if ($dbrec->info != null) {
                $dbrec->info = unserialize(base64_decode($dbrec->info));
            }
        }
        return $dbrec;
    }

    /**
     * Given on courseid, fullname and shortname, calculate the correct fullname/shortname to avoid dupes
     */
    public static function calculate_course_names($courseid, $fullname, $shortname) {
        global $CFG, $DB;

        $currentfullname = '';
        $currentshortname = '';
        $counter = 0;
        // Iteratere while the name exists
        do {
            if ($counter) {
                $suffixfull  = ' ' . get_string('copyasnoun') . ' ' . $counter;
                $suffixshort = '_' . $counter;
            } else {
                $suffixfull  = '';
                $suffixshort = '';
            }
            $currentfullname = $fullname.$suffixfull;
            $currentshortname = substr($shortname, 0, 100 - strlen($suffixshort)).$suffixshort; // < 100cc
            $coursefull  = $DB->get_record_select('course', 'fullname = ? AND id != ?', array($currentfullname, $courseid));
            $courseshort = $DB->get_record_select('course', 'shortname = ? AND id != ?', array($currentshortname, $courseid));
            $counter++;
        } while ($coursefull || $courseshort);

        // Return results
        return array($currentfullname, $currentshortname);
    }

    /**
     * For the target course context, put as many custom role names as possible
     */
    public static function set_course_role_names($restoreid, $courseid) {
        global $DB;

        // Get the course context
        $coursectx = get_context_instance(CONTEXT_COURSE, $courseid);
        // Get all the mapped roles we have
        $rs = $DB->get_recordset('backup_ids_temp', array('backupid' => $restoreid, 'itemname' => 'role'), '', 'itemid');
        foreach ($rs as $recrole) {
            // Get the complete temp_ids record
            $role = (object)self::get_backup_ids_record($restoreid, 'role', $recrole->itemid);
            // If it's one mapped role and we have one name for it
            if (!empty($role->newitemid) && !empty($role->info['nameincourse'])) {
                // If role name doesn't exist, add it
                $rolename = new stdclass();
                $rolename->roleid = $role->newitemid;
                $rolename->contextid = $coursectx->id;
                if (!$DB->record_exists('role_names', (array)$rolename)) {
                    $rolename->name = $role->info['nameincourse'];
                    $DB->insert_record('role_names', $rolename);
                }
            }
        }
        $rs->close();
    }

    /**
     * Creates a skeleton record within the database using the passed parameters
     * and returns the new course id.
     *
     * @global moodle_database $DB
     * @param string $fullname
     * @param string $shortname
     * @param int $categoryid
     * @return int The new course id
     */
    public static function create_new_course($fullname, $shortname, $categoryid) {
        global $DB;
        $category = $DB->get_record('course_categories', array('id'=>$categoryid), '*', MUST_EXIST);

        $course = new stdClass;
        $course->fullname = $fullname;
        $course->shortname = $shortname;
        $course->category = $category->id;
        $course->sortorder = 0;
        $course->timecreated  = time();
        $course->timemodified = $course->timecreated;
        // forcing skeleton courses to be hidden instead of going by $category->visible , until MDL-27790 is resolved.
        $course->visible = 0;

        $courseid = $DB->insert_record('course', $course);

        $category->coursecount++;
        $DB->update_record('course_categories', $category);

        return $courseid;
    }

    /**
     * Deletes all of the content associated with the given course (courseid)
     * @param int $courseid
     * @param array $options
     * @return bool True for success
     */
    public static function delete_course_content($courseid, array $options = null) {
        return remove_course_contents($courseid, false, $options);
    }
}

/*
 * Exception class used by all the @dbops stuff
 */
class restore_dbops_exception extends backup_exception {

    public function __construct($errorcode, $a=NULL, $debuginfo=null) {
        parent::__construct($errorcode, 'error', '', $a, null, $debuginfo);
    }
}
