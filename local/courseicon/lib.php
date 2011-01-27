<?php

/**
 *

 * @param $mform MoodleQuickForm
 * @param $course stdClass
 * @return void
 */
function local_courseicon_course_form_definition($mform, $course){
    global $CFG, $PAGE;
    $mform->addElement('header','',get_string('courseicon','local_courseicon'));
    $mform->addElement('static','currenticon',get_string('currenticon','local_courseicon'), local_courseicon_course_icon_tag($course, 'large', true));
    $mform->addElement('select','icon',get_string('icon','local_courseicon'), local_courseicon_get_stock_icons('course'));

    $iconel = $mform->getElement('icon');
    $iconel->updateAttributes(array('onchange'=>'previewCourseIcon('.$course->id.'); '));
    $PAGE->requires->js('/local/courseicon/icons.js.php');

    $filemanager_options = array();
    // 3 == FILE_EXTERNAL & FILE_INTERNAL
    // These two constant names are defined in repository/lib.php
    $filemanager_options['return_types'] = 3;
    $filemanager_options['accepted_types'] = 'web_image';
    $filemanager_options['maxbytes'] = get_max_upload_file_size($CFG->maxbytes);
    $mform->addElement('filepicker', 'uploadicon', get_string('uploadicon', 'local_courseicon'), null, $filemanager_options);

    // They don't seem to have implemented "disabledIf" for the filepicker form element yet,
    // but if they do this should come into effect.
    $mform->disabledIf('uploadicon','icon','neq','custom');
}

/**
 *

 * @param $mform MoodleQuickForm
 * @param $category stdClass
 * @return void
 */
function local_courseicon_category_form_definition($mform, $category){
    global $CFG, $PAGE;
    $mform->addElement('static','currenticon',get_string('currenticon','local_courseicon'), local_courseicon_category_icon_tag($category));
    $mform->addElement('select','icon',get_string('icon','local_courseicon'), local_courseicon_get_stock_icons('coursecategory'));

// todo: Javascript to make the icon on the form update automatically so the user can preview it
//    $iconel = $mform->getElement('icon');
//    $iconel->updateAttributes(array('onchange'=>'previewCourseIcon(); '));
//    $PAGE->requires->js('/local/courseicon/icons.js.php');

// todo: picker for custom icon
//    $filemanager_options = array();
//    // 3 == FILE_EXTERNAL & FILE_INTERNAL
//    // These two constant names are defined in repository/lib.php
//    $filemanager_options['return_types'] = 3;
//    $filemanager_options['accepted_types'] = 'web_image';
//    $filemanager_options['maxbytes'] = get_max_upload_file_size($CFG->maxbytes);
//    $mform->addElement('filepicker', 'uploadicon', get_string('uploadicon', 'local_courseicon'), null, $filemanager_options);
//

//    $mform->disabledIf('uploadicon','icon','neq','custom');
}

/**
 * Return img tag to a course icon
 *
 * @param object $course Course object
 * @param string $size Size of icon set to use
 * @global $CFG
 * @global $COURSE
 * @return string img tag
 */
function local_courseicon_course_icon_tag($course=null, $size='large', $addrev=false) {
    global $CFG, $COURSE, $DB;
    if (!isset($course)) {
        $course = $COURSE;
    }
    // Get the course icon if it's not set
    if (!isset($course->icon)) {
        if (isset($course->id)){
            $course->icon = $DB->get_field('course','icon',array('id'=>$course->id));
        } else {
            $course->icon = '';
        }
    }
    $ret = '<img id="iconpreview" src="'.$CFG->wwwroot.'/local/courseicon/icon.php?id='.$course->id.'&amp;icon='.$course->icon.'&amp;size='.$size.'&amp;type=course';
    if ( $addrev ){
        $ret .= '&amp;rev=' . time();
    }
    $ret .='" alt="'.$course->shortname.'" class="class_icon" />';
    return $ret;
}

/**
 * Return img tag to a course category icon
 *
 * @param object $coursecat Course category object
 * @param string $size Size of icon set to use
 * @global $CFG
 * @global $COURSE
 * @return string img tag
 */
function local_courseicon_category_icon_tag($coursecat, $size='large') {
    global $CFG;
    return '<img src="'.$CFG->wwwroot.'/local/courseicon/icon.php?icon='.$coursecat->icon.'&amp;size='.$size.'&type=coursecategory" alt="'.$coursecat->name.'" class="class_icon" />';
}

/**
 * Get list of stock course icons for use in a MoodleQuickForm select
 * @param $type string
 * @return array
 */
function local_courseicon_get_stock_icons($type) {
    global $CFG;
    $icons = array('custom' => get_string('customicon', 'local_courseicon'),
                   'none' => get_string('noicons', 'local_courseicon'));

    if ($path = local_courseicon_get_stock_icon_dir($type)) {
        $d = dir($path.'/large');
        while(($icon = $d->read()) !== false) {
            if (is_file($path.'/large/'.$icon)) {

                $icons[$icon] = ucwords(strtr($icon, array('_' => ' ', '-' => ' ', '.png' => '')));
            }
        }
        $d->close();
    }
    return($icons);
}

/**
 * Return the path to the proper icon directory
 * @param $type string The type of icon
 * @return string
 */
function local_courseicon_get_stock_icon_dir($type) {
    global $CFG;

    if ($type == 'course') {
        $dir = 'courseicons';
    } elseif ($type == 'coursecategory') {
        $dir = 'coursecategoryicons';
    } else {
        return(false);
    }

    if (is_dir($CFG->dirroot.'/theme/'.$CFG->theme.'/'.$dir)) {
        return($CFG->dirroot.'/theme/'.$CFG->theme.'/'.$dir);
    } else {
        return $CFG->dirroot.'/local/courseicon/'.$dir;
    }
}

/**
 * Send icon data
 *
 * @param int $courseid Course id
 * @param string $courseicon Name of icon to send
 * @param string $size Icon size to send
 * @global $CFG
 */
function local_courseicon_output_course_icon($courseid, $courseicon, $size='large') {
    global $CFG;

    $icondir = local_courseicon_get_stock_icon_dir('course');
    $iconname = 'default.png';
    $icon = $icondir.'/'.$size.'/default.png';

    // Todo: rewrite this to work with the new file API
    if ($courseicon == 'custom') {
        $fs = get_file_storage();
        $context = get_context_instance(CONTEXT_COURSE,$courseid);
        $files = $fs->get_area_files($context->id, 'local_courseicon', "customicon-{$size}");
        if (count($files)){
            $file = array_pop($files);
            send_stored_file($file);
            return;
        }
    } else {
        if (is_file($icondir.'/'.$size.'/'.$courseicon)) {
            $icon = $icondir.'/'.$size.'/'.$courseicon;
            $iconname = $courseicon;
        }
    }
    send_file($icon, $iconname);
}

/**
 * Send course cateogry icon data
 *
 * @param string $courseicon Name of icon to send
 * @param string $size Icon size to send
 * @global $CFG
 */
function local_courseicon_output_category_icon($iconname, $size='large') {
    global $CFG;

    $site = get_site();

    $icondir = local_courseicon_get_stock_icon_dir('coursecategory');
    $iconpath = 'default.png';
    $icon = $icondir.'/'.$size.'/default.png';

    // Todo: rewrite this to work with the new file API
    if ($iconname == 'custom') {
        if (is_file($CFG->dataroot.'/'.$site->id.'/icons/coursecategory/'.$size.'.png')) {
            $icon = $CFG->dataroot.'/'.$site->id.'/icons/coursecategory/'.$size.'.png';
            $iconpath = 'customicon.png';
        }
    } else {
        if (is_file($icondir.'/'.$size.'/'.$iconname)) {
            $icon = $icondir.'/'.$size.'/'.$iconname;
        }
        $iconpath = $iconname;
    }
    send_file($icon, $iconname);
}

/**
 * Update course icon
 *
 * @param object $course Course object
 * @param object $data Formslib form data
 * @param MoodleQuickForm $mform Moodle form
 * @global $CFG
 */
function local_courseicon_update_course_icon($course, $data, &$mform) {
    global $CFG, $DB;
    $updatecourse = new stdClass();
    $updatecourse->id = $course->id;

    if ($data->icon == 'custom') {
        $updatecourse->icon = 'custom';

        $tempfilepath = $mform->save_temp_file('uploadicon');

        if ($tempfilepath){
            $fs = get_file_storage();
            $context = get_context_instance(CONTEXT_COURSE, $course->id);
            $fs->delete_area_files($context->id, 'local_courseicon');
            local_courseicon_resize_image($tempfilepath, 'icon-large', $context, 'local_courseicon', 'customicon-large', 0, '/', 50, 50, 'png');
            local_courseicon_resize_image($tempfilepath, 'icon-small', $context, 'local_courseicon', 'customicon-small', 0, '/', 25, 25, 'png');

            @unlink($tempfilepath);
        }

    } elseif ($data->icon == 'none') {
        $updatecourse->icon = '';
    } elseif ( $data->icon != 'custom' ) {
        $updatecourse->icon = $data->icon;
    }

    $DB->update_record('course', $updatecourse);
}

/**
 * Update course category icon
 *
 * @param object $coursecategory Course object
 * @param object $data Formslib form data
 * @param MoodleQuickForm $mform Moodle form
 * @global $CFG
 */
function local_courseicon_update_category_icon($coursecategory, $data, &$mform) {
    global $CFG, $DB;

    $site = get_site();

    $updatecoursecat = new stdClass;
    $updatecoursecat->id = $coursecategory->id;

    if ($data->icon == 'custom') {
        // todo: make this work with new File API
//        //Move icon to course
//        $updatecoursecat->icon = 'custom';
//
//        $dest = $CFG->dataroot.'/'.$site->id.'/icons/coursecategory/';
//        make_upload_directory($dest);
//        $mform->save_files($dest);
//
//        if ($filename =  $mform->get_new_filename()) {
//            resize_image($dest.$filename, $dest.'large', 50, 50, 'png');
//            resize_image($dest.$filename, $dest.'small', 25, 25, 'png');
//            unlink($dest.$filename);
//        }
//
    } elseif ($data->icon == 'none') {
        $updatecoursecat->icon = '';
    } else {
        $updatecoursecat->icon = $data->icon;
    }
    $DB->update_record('course_categories', $updatecoursecat);
}

/**
 * Resize an image to fit within the given rectangle, maintaining aspect ratio
 * @param $originalfile Path to the original file (as a temp file)
 * @param $destname Filename to use for final file
 * @param $context Context to save file in
 * @param $component Component to save file in
 * @param $filearea Filearea to save file in
 * @param $itemid ID to save file with
 * @param $filepath Path to save file at
 * @param int $newwidth Width to resize to
 * @param int $newheight Height to resize to
 * @param string $forcetype If provided, force conversion to this format (should be png or jpeg)
 *
 * @global $CFG
 * @return string Final filename
 */
function local_courseicon_resize_image($originalfile, $destname, $context, $component, $filearea, $itemid, $filepath, $newwidth, $newheight, $forcetype = false) {
    global $CFG;

    require_once($CFG->libdir.'/gdlib.php');

    if(!(is_file($originalfile))) {
        return false;
    }

    if (empty($CFG->gdversion)) {
        return false;
    }

    $imageinfo = GetImageSize($originalfile);
    if (empty($imageinfo)) {
        return false;
    }

    $image = new stdClass;

    $image->width  = $imageinfo[0];
    $image->height = $imageinfo[1];
    $image->type   = $imageinfo[2];

    $ratiosrc = $image->width / $image->height;

    if ($newwidth/$newheight > $ratiosrc) {
        $newwidth = $newheight * $ratiosrc;
    } else {
        $newheight = $newwidth / $ratiosrc;
    }

    switch ($image->type) {
        case IMAGETYPE_GIF:
            if (function_exists('ImageCreateFromGIF')) {
                $im = ImageCreateFromGIF($originalfile);
                $outputformat = 'png';
            } else {
                notice('GIF not supported on this server');
                return false;
            }
            break;
        case IMAGETYPE_JPEG:
            if (function_exists('ImageCreateFromJPEG')) {
                $im = ImageCreateFromJPEG($originalfile);
                $outputformat = 'jpeg';
            } else {
                notice('JPEG not supported on this server');
                return false;
            }
            break;
        case IMAGETYPE_PNG:
            if (function_exists('ImageCreateFromPNG')) {
                $im = ImageCreateFromPNG($originalfile);
                $outputformat = 'png';
            } else {
                notice('PNG not supported on this server');
                return false;
            }
            break;
        default:
            return false;
    }

    if ($forcetype) {
        $outputformat = $forcetype;
    }

    if (function_exists('ImageCreateTrueColor') and $CFG->gdversion >= 2) {
        $im1 = ImageCreateTrueColor($newwidth,$newheight);
    } else {
        $im1 = ImageCreate($newwidth, $newheight);
    }
    if ($outputformat == 'png') {

        // Turn off transparency blending (temporarily)
        imagealphablending($im1, false);

        // Create a new transparent color for image
        $color = imagecolorallocatealpha($im1, 0, 0, 0, 127);

        // Completely fill the background of the new image with allocated color.
        imagefill($im1, 0, 0, $color);

        // Restore transparency blending
        imagesavealpha($im1, true);
    }
    ImageCopyBicubic($im1, $im, 0, 0, 0, 0, $newwidth, $newheight, $image->width, $image->height);

    $fs = get_file_storage();

    $logo = array(
    	'contextid'=>$context->id,
    	'component'=>$component,
    	'filearea'=>$filearea,
    	'itemid'=>$itemid,
    	'filepath'=>$filepath
    );

    switch($outputformat) {
        case 'png':
            if ( function_exists('ImagePng') ){
                $imagefnc = 'ImagePng';
                $imageext = '.png';
                $filters = PNG_NO_FILTER;
                $quality = 1;
            } else {
                debugging('PNG not supported on this server, please fix server configuration.');
            }
            break;
        case 'jpeg':
            if ( function_exists('ImageJpeg') ){
                $imagefnc = 'ImageJpeg';
                $imageext = '.jpg';
                $filters = null;
                $quality = 90;
            } else {
                debugging( 'JPEG not supported on this server, please fix server configuration.');
            }
            break;
        default:
            return false;
    }

    ob_start();
    if (!$imagefnc($im1, null, $quality, $filters)){
        ob_end_clean();
        return false;
    }
    $data = ob_get_clean();
    ImageDestroy($im1);
    $destname .= $imageext;
    $logo['filename'] = $destname;
    $result = $fs->create_file_from_string($logo, $data);

    return $result->get_filepath() . $result->get_filename();
}
