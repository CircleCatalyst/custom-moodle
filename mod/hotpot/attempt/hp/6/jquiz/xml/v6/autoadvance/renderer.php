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
 * Render an attempt at a HotPot quiz
 * Output format: hp_6_jquiz_xml_v6_autoadvance
 *
 * @package   mod-hotpot
 * @copyright 2010 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// get parent class
require_once($CFG->dirroot.'/mod/hotpot/attempt/hp/6/jquiz/xml/v6/renderer.php');

/**
 * mod_hotpot_attempt_hp_6_jquiz_xml_v6_autoadvance_renderer
 *
 * @copyright 2010 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class mod_hotpot_attempt_hp_6_jquiz_xml_v6_autoadvance_renderer extends mod_hotpot_attempt_hp_6_jquiz_xml_v6_renderer {

    /**
     * init
     *
     * @param xxx $hotpot
     */
    function init($hotpot)  {
        parent::init($hotpot);
    }

    /**
     * List of source types which this renderer can handle
     *
     * @return array of strings
     */
    public static function sourcetypes()  {
        return array('hp_6_jquiz_xml');
    }

    /**
     * get_js_functionnames
     *
     * @return xxx
     */
    function get_js_functionnames()  {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'CompleteEmptyFeedback';
        return $names;
    }

    /**
     * fix_js_WriteToInstructions
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_WriteToInstructions(&$str, $start, $length)  {
        $substr = substr($str, $start, $length);

        $search = "/(\s*)document\.getElementById\('InstructionsDiv'\)\.innerHTML = Feedback;/";
        $replace = "\\1"
            ."var AllDone = true;\\1"
            ."for (var QNum=0; QNum<State.length; QNum++){\\1"
            ."	if (State[QNum]){\\1"
            ."		if (State[QNum][0] < 0){\\1"
            ."			AllDone = false;\\1"
            ."		}\\1"
            ."	}\\1"
            ."}\\1"
            ."if (AllDone) {\\1"
            ."	var obj = document.getElementById('InstructionsDiv');\\1"
            ."	if (obj) {\\1"
            ."		obj.innerHTML = Feedback;\\1"
            ."		obj.style.display = '';\\1"
            ."	}\\1"
            ."	Finished = true;\\1"
            ."	ShowMessage(Feedback);\\1"
            ."}"
        ;
        $substr = preg_replace($search, $replace, $substr, 1);

        parent::fix_js_WriteToInstructions($substr, 0, strlen($substr));
        $str = substr_replace($str, $substr, $start, $length);
    }

    /**
     * fix_js_StartUp
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_StartUp(&$str, $start, $length)  {
        $substr = substr($str, $start, $length);

        // hide instructions, if they are not required
        $search = '/(\s*)strInstructions = document.getElementById[^;]*;/s';
        $replace = ''
            .'\\1'."var obj = document.getElementById('Instructions');"
            .'\\1'."if (obj==null || obj.innerHTML=='') {"
            .'\\1'."	var obj = document.getElementById('InstructionsDiv');"
            .'\\1'."	if (obj) {"
            .'\\1'."		obj.style.display = 'none';"
            .'\\1'."	}"
            .'\\1'."}"
        ;
        //$substr = preg_replace($search, $replace, $substr, 1);

        $insert = '';
        if ($this->expand_UserDefined1()) {
            $insert .= "	AA_SetProgressBar();\n";
        }
        if ($this->expand_UserDefined2()) {
            $insert .= "	setTimeout('AA_PlaySound(0,0)', 500);";
        }
        if ($insert) {
            $pos = strrpos($substr, '}');
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        // call the fix_js_StartUp() method on the parent object
        parent::fix_js_StartUp($substr, 0, strlen($substr));

        $str = substr_replace($str, $substr, $start, $length);
    }

    /**
     * fix_js_SetUpQuestions
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     * @return xxx
     */
    function fix_js_SetUpQuestions(&$str, $start, $length)  {
        global $CFG;
        $substr = substr($str, $start, $length);

        parent::fix_js_SetUpQuestions($substr, 0, strlen($substr));

        $dots = 'squares'; // default
        if ($param = clean_param($this->expand_UserDefined1(), PARAM_ALPHANUM)) {
            if (is_dir($CFG->dirroot."/mod/hotpot/attempt/hp/6/jquiz/xml/v6/autoadvance/$param")) {
                $dots = $param;
            }
        }

        // add progress bar
        if ($dots) {
            $search = '/\s*'.'SetQNumReadout\(\);/';
            $replace = ''
                ."	var ProgressBar = document.createElement('div');\n"
                ."	ProgressBar.setAttribute('id', 'ProgressBar');\n"
                ."	ProgressBar.setAttribute(AA_className(), 'ProgressBar');\n"
                ."\n"
                ."	// add feedback boxes and progess dots for each question\n"
                ."	for (var i=0; i<QArray.length; i++){\n"
                ."\n"
                .'		// remove bottom border (override class="QuizQuestion")'."\n"
                ."		if (QArray[i]) {\n"
                ."			QArray[i].style.borderWidth = '0px';\n"
                ."		}\n"
                ."\n"
                ."		if (ProgressBar.childNodes.length) {\n"
                ."			// add arrow between progress dots\n"
                ."			ProgressBar.appendChild(document.createTextNode(' '));\n"
                ."			ProgressBar.appendChild(AA_ProgressArrow());\n"
                ."			ProgressBar.appendChild(document.createTextNode(' '));\n"
                ."		}\n"
                ."		ProgressBar.appendChild(AA_ProgressDot(i));\n"
                ."\n"
                ."		// AA_Add_FeedbackBox(i);\n"
                ."	}\n"
                ."	var OneByOneReadout = document.getElementById('OneByOneReadout');\n"
                ."	if (OneByOneReadout) {\n"
                ."		OneByOneReadout.parentNode.insertBefore(ProgressBar, OneByOneReadout);\n"
                ."		OneByOneReadout.parentNode.removeChild(OneByOneReadout);\n"
                ."	}\n"
                ."	OneByOneReadout = null;\n"
                ."	// hide the div containing ShowMethodButton, PrevQButton and NextQButton\n"
                ."	var btn = document.getElementById('ShowMethodButton');\n"
                ."	if (btn) {\n"
                ."		btn.parentNode.style.display = 'none';\n"
                ."	}\n"
                ."\t"
            ;
            $substr = preg_replace($search, $replace, $substr, 1);

            // add functions required for progress bar
            $substr .= "\n"
                ."function AA_isNonStandardIE() {\n"
                ."	if (typeof(window.isNonStandardIE)=='undefined') {\n"
                ."		if (navigator.appName=='Microsoft Internet Explorer' && (document.documentMode==null || document.documentMode<8)) {\n"
                ."			// either IE8+ (in compatability mode) or IE7, IE6, IE5 ...\n"
                ."			window.isNonStandardIE = true;\n"
                ."		} else {\n"
                ."			// Firefox, Safari, Opera, IE8+\n"
                ."			window.isNonStandardIE = false;\n"
                ."		}\n"
                ."	}\n"
                ."	return window.isNonStandardIE;\n"
                ."}\n"
                ."function AA_className() {\n"
                ."	if (AA_isNonStandardIE()){\n"
                ."		return 'className';\n"
                ."	} else {\n"
                ."		return 'class';\n"
                ."	}\n"
                ."}\n"
                ."function AA_onclickAttribute(fn) {\n"
                ."	if (AA_isNonStandardIE()){\n"
                ."		return new Function(fn);\n"
                ."	} else {\n"
                ."		return fn; // just return the string\n"
                ."	}\n"
                ."}\n"
                ."function AA_images() {\n"
                ."	return 'output/hp/6/jquiz/xml/v6/autoadvance/$dots';\n"
                ."}\n"
                ."function AA_ProgressArrow() {\n"
                ."	var img = document.createElement('img');\n"
                ."	var src = 'ProgressDotArrow.gif';\n"
                ."	img.setAttribute('src', AA_images() + '/' + src);\n"
                ."	img.setAttribute('alt', src);\n"
                ."	img.setAttribute('title', src);\n"
                ."	//img.setAttribute('height', 18);\n"
                ."	//img.setAttribute('width', 18);\n"
                ."	img.setAttribute(AA_className(), 'ProgressDotArrow');\n"
                ."	return img;\n"
                ."}\n"
                ."function AA_ProgressDot(i) {\n"
                ."	// i is either an index on QArray \n"
                ."	// or a string to be used as an id for an HTML element\n"
                ."	if (typeof(i)=='string') {\n"
                ."		var id = i;\n"
                ."		var add_link = false;\n"
                ."	} else if (QArray[i]) {\n"
                ."		var id = QArray[i].id;\n"
                ."		var add_link = true;\n"
                ."	} else {\n"
                ."		return false;\n"
                ."	}\n"
                ."	// id should now be: 'Q_' + q ...\n"
                ."	// where q is an index on the State array\n"
                ."	var src = 'ProgressDotEmpty.gif';\n"
                ."	var img = document.createElement('img');\n"
                ."	img.setAttribute('id', id + '_ProgressDotImg');\n"
                ."	img.setAttribute('src', AA_images() + '/' + src);\n"
                ."	img.setAttribute('alt', src);\n"
                ."	img.setAttribute('title', src);\n"
                ."	//img.setAttribute('height', 18);\n"
                ."	//img.setAttribute('width', 18);\n"
                ."	img.setAttribute(AA_className(), 'ProgressDotEmpty');\n"
                ."	if (add_link) {\n"
                ."		var link = document.createElement('a');\n"
                ."		link.setAttribute('id', id + '_ProgressDotLink');\n"
                ."		link.setAttribute(AA_className(), 'ProgressDotLink');\n"
                ."		link.setAttribute('title', 'go to question '+(i+1));\n"
                ."		var fn = 'ChangeQ('+i+'-CurrQNum);return false;';\n"
                ."		link.setAttribute('onclick', AA_onclickAttribute(fn));\n"
                ."		link.appendChild(img);\n"
                ."	}\n"
                ."	var span = document.createElement('span');\n"
                ."	span.setAttribute('id', id + '_ProgressDot');\n"
                ."	span.setAttribute(AA_className(), 'ProgressDot');\n"
                ."	if (add_link) {\n"
                ."		span.appendChild(link);\n"
                ."	} else {\n"
                ."		span.appendChild(img);\n"
                ."	}\n"
                ."	return span;\n"
                ."}\n"
                ."function AA_JQuiz_GetQ(i) {\n"
               ."	if (! QArray[i]) {\n"
                ."		return -1;\n"
                ."	}\n"
                ."	if (! QArray[i].id) {\n"
                ."		return -1;\n"
                ."	}\n"
                ."	var matches = QArray[i].id.match(new RegExp('\\\\d+$'));\n"
                ."	if (! matches) {\n"
                ."		return -1;\n"
                ."	}\n"
                ."	var q = matches[0];\n"
                ."	if (! State[q]) {\n"
                ."		return -1;\n"
                ."	}\n"
                ."	return parseInt(q);\n"
                ."}\n"
                ."function AA_SetProgressDot(q, next_q) {\n"
                ."	var img = document.getElementById('Q_'+q+'_ProgressDotImg');\n"
                ."	if (! img) {\n"
                ."		return;\n"
                ."	}\n"
                ."	var src = '';\n"
                ."	// State[q][0] : the score (as a decimal fraction of 1)  for this question (initially -1)\n"
                ."	// State[q][2] : no of checks for this question (initially 0)\n"
                ."	if (State[q][0]>=0) {\n"
                ."		var score = Math.max(0, I[q][0] * State[q][0]);\n"
                ."		// Note that if there are only two options on a multiple-choice question, then \n"
                ."		// even if the wrong answer is chosen, the question will be considered finished\n"
                ."		if (score >= 99) {\n"
                ."			src = 'ProgressDotCorrect99Plus'+'.gif';\n"
                ."		} else if (score >= 80) {\n"
                ."			src = 'ProgressDotCorrect80Plus'+'.gif';\n"
                ."		} else if (score >= 60) {\n"
                ."			src = 'ProgressDotCorrect60Plus'+'.gif';\n"
                ."		} else if (score >= 40) {\n"
                ."			src = 'ProgressDotCorrect40Plus'+'.gif';\n"
                ."		} else if (score >= 20) {\n"
                ."			src = 'ProgressDotCorrect20Plus'+'.gif';\n"
                ."		} else if (score >= 0) {\n"
                ."			src = 'ProgressDotCorrect00Plus'+'.gif';\n"
                ."		} else {\n"
                ."			// this question has negative score, which means it has not yet been correctly answered\n"
                ."			src = 'ProgressDotWrong'+'.gif';\n"
                ."		}\n"
                ."	} else {\n"
                ."		// this question has not been completed\n"
                ."		if (typeof(next_q)=='number' && q==next_q) {\n"
                ."			// this question will be attempted next\n"
                ."			src = 'ProgressDotCurrent'+'.gif';\n"
                ."		} else {\n"
                ."			src = 'ProgressDotEmpty'+'.gif';\n"
                ."		}\n"
                ."	}\n"
                ."	var full_src = AA_images() + '/' + src;\n"
                ."	if (img.src != full_src) {\n"
                ."		img.setAttribute('src', full_src);\n"
                ."	}\n"
                ."}\n"
                ."function AA_SetProgressBar(next_q) {\n"
                ."	// next_q is an index on State array\n"
                ."	// CurrQNum is an index on QArray\n"
                ."	if (typeof(next_q)=='undefined') {\n"
                ."		next_q = AA_JQuiz_GetQ(window.CurrQNum || 0);\n"
                ."	}\n"
                ."	for (var i=0; i<QArray.length; i++) {\n"
                ."		var q = AA_JQuiz_GetQ(i);\n"
                ."		if (q>=0) {\n"
                ."			AA_SetProgressDot(q, next_q);\n"
                ."		}\n"
                ."	}\n"
                ."}\n"
            ;
        }

        // append the PlaySound() and StopSound() functions
        if ($this->expand_UserDefined2()) {
            $substr .= "\n"
                ."function PlaySound(i, count) {\n"
                .'	// li (id="Q_99") -> p (class="questionText") -> span (class="mediaplugin_mp3") -> object'."\n"
                ."	var li = QArray[i];\n"
                ."	try {\n"
                ."		var SoundLoaded = li.childNodes[0].childNodes[0].childNodes[0].isSoundLoadedFromJS();\n"
                ."	} catch (err) {\n"
                ."		var SoundLoaded = false;\n"
                ."	}\n"
                ."	if (SoundLoaded) {\n"
                ."		try {\n"
                ."			li.childNodes[0].childNodes[0].childNodes[0].playSoundFromJS();\n"
                ."			var SoundPlayed = true;\n"
                ."		} catch (err) {\n"
                ."			var SoundPlayed = false;\n"
                ."		}\n"
                ."	}\n"
                ."	if (SoundLoaded && SoundPlayed) {\n"
                ."		// sound was successfully played\n"
                ."	} else {\n"
                ."		// sound could not be loaded or played\n"
                ."		if (count<=100) {\n"
                ."			// try again in 1/10th of a second\n"
                ."			setTimeout('PlaySound('+i+','+(count+1)+')', 100);\n"
                ."		}\n"
                ."	}\n"
                ."}\n"
                ."function StopSound(i) {\n"
                .'	// li (id="Q_99") -> p (class="questionText") -> span (class="mediaplugin_mp3") -> object'."\n"
                ."	var li = QArray[i];\n"
                ."	try {\n"
                ."		var SoundLoaded = li.childNodes[0].childNodes[0].childNodes[0].isSoundLoadedFromJS();\n"
                ."	} catch (err) {\n"
                ."		var SoundLoaded = false;\n"
                ."	}\n"
                ."	if (SoundLoaded) {\n"
                ."		try {\n"
                ."			li.childNodes[0].childNodes[0].childNodes[0].stopSoundFromJS();\n"
                ."			var SoundStopped = true;\n"
                ."		} catch (err) {\n"
                ."			var SoundStopped = false;\n"
                ."		}\n"
                ."	}\n"
                ."}"
            ;
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    /**
     * fix_js_ShowMessage
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     * @return xxx
     */
    function fix_js_ShowMessage(&$str, $start, $length)  {
        $substr = substr($str, $start, $length);

        // do standard fix for this function
        parent::fix_js_ShowMessage($substr, 0, strlen($substr));

        // add extra argument, QNum, to this function
        $substr = preg_replace('/(?<=ShowMessage)\('.'(.*)'.'\)/', '(\\1, QNum)', $substr, 1);

        if ($this->expand_UserDefined2()) {
            $StopSound = "\t\t\t".'StopSound(CurrQNum);'."\n";
        } else {
            $StopSound = '';
        }
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	if (typeof(QNum)!='undefined' && State[QNum] && State[QNum][0]>=0) {\n"
                ."		// this question is finished\n"
                ."		if (ShowingAllQuestions) {\n"
                ."			CurrQNum = QNum;\n"
                ."		} else {\n"
                ."			// move to next question, if there is one\n"
                ."			var i_max = QArray.length;\n"
                ."			for (var i=1; i<i_max; i++) {\n"
                ."				// calculate the next index for QArray\n"
                ."				var next_i = (i + CurrQNum) % i_max;\n"
                ."				if (QArray[next_i] && QArray[next_i].id) {\n"
                ."					var matches = QArray[next_i].id.match(new RegExp('\\\\d+$'));\n"
                ."					if (matches) {\n"
                ."						var next_q = parseInt(matches[0]);\n"
                ."						if (State[next_q] && State[next_q][0]<0) {\n"
                ."							// change to unanswered question\n"
                ."							ChangeQ(next_i - CurrQNum);\n"
                ."							break;\n"
                ."						}\n"
                ."					}\n"
                ."				}\n"
                ."			}\n"
                ."		}\n"
                ."		var q = AA_JQuiz_GetQ(CurrQNum);\n"
                ."		if (q==QNum) {\n"
                ."			// this was the last question\n"
                ."			AA_SetProgressDot(q, q);\n"
                ."		}\n"
                ."	}\n"
                ."	// only show feedback if there is any\n"
                ."	if (! Feedback) return false;"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }
        $str = substr_replace($str, $substr, $start, $length);
    }

    /**
     * fix_js_CompleteEmptyFeedback
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_CompleteEmptyFeedback(&$str, $start, $length)  {
        $substr = substr($str, $start, $length);

        // set empty feedback to blank string
        $substr = str_replace('DefaultWrong', "''", $substr);
        $substr = str_replace('DefaultRight', "''", $substr);

        $str = substr_replace($str, $substr, $start, $length);
    }

    /**
     * fix_js_ChangeQ
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_ChangeQ(&$str, $start, $length)  {
        $substr = substr($str, $start, $length);

        $search = '/(\s*)SetQNumReadout\('.'(.*?)'.'\);/s';
        $replace = ''
            .'\\1'.'var q = AA_JQuiz_GetQ(CurrQNum - ChangeBy);'
            .'\\1'.'AA_SetProgressDot(q);'
            .'\\1'.'var q = AA_JQuiz_GetQ(CurrQNum);'
            .'\\1'.'AA_SetProgressDot(q, q);'
        ;
        $substr = preg_replace($search, $replace, $substr);

        $str = substr_replace($str, $substr, $start, $length);
    }

    // utility function to search and replace and, if required, call the fix_js_xxx() method of the parent class

    /**
     * fix_js_search_replace
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     * @param xxx $thisfunction
     * @param xxx $parentfunction (optional, default='')
     */
    function fix_js_search_replace(&$str, $start, $length, $thisfunction, $parentfunction='') {
        $substr = substr($str, $start, $length);

        $search = '/(?<=ShowMessage)\('.'([^)]*)'.'\)/';
        $replace = '(\\1, QNum)';
        $substr = preg_replace($search, $replace, $substr);

        if ($parentfunction) {
            $parentmethod = 'fix_js_'.$parentfunction;
            parent::$parentmethod($substr, 0, strlen($substr));
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    /**
     * fix_js_ShowAnswers
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_ShowAnswers(&$str, $start, $length)  {
        $this->fix_js_search_replace($str, $start, $length, 'ShowMessage');
    }

    /**
     * fix_js_ShowHint
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_ShowHint(&$str, $start, $length)  {
        $this->fix_js_search_replace($str, $start, $length, 'ShowMessage');
    }

    /**
     * fix_js_CheckMCAnswer
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_CheckMCAnswer(&$str, $start, $length)  {
        $this->fix_js_search_replace($str, $start, $length, 'ShowMessage', 'CheckMCAnswer');
    }

    /**
     * fix_js_CheckMultiSelAnswer
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_CheckMultiSelAnswer(&$str, $start, $length)  {
        $this->fix_js_search_replace($str, $start, $length, 'ShowMessage', 'CheckMultiSelAnswer');
    }

    /**
     * fix_js_CheckShortAnswer
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_CheckShortAnswer(&$str, $start, $length)  {
        $this->fix_js_search_replace($str, $start, $length, 'ShowMessage', 'CheckShortAnswer');
    }

    /**
     * fix_js_ShowHideQuestions
     *
     * @param xxx $str (passed by reference)
     * @param xxx $start
     * @param xxx $length
     */
    function fix_js_ShowHideQuestions(&$str, $start, $length)  {
        $substr = substr($str, $start, $length);

        $search = "/\s*document\.getElementById\('OneByOneReadout'\)\.style.display = '[^']*';/s";
        $substr = preg_replace($search, '', $substr);

        // do standard fix for this function
        parent::fix_js_ShowHideQuestions($substr, 0, strlen($substr));
    }
}
