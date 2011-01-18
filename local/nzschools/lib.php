<?php

/**
 * Resize and save path to logo used in theme
 *
 * @param string Path to image file
 *
 * @global $CFG
 * @return New filename of image
 */
function process_logo($filename) {
    global $CFG;

    $filename = resize_image($CFG->dataroot.'/theme/'.$filename, $CFG->dataroot.'/theme/logo', 400, 75);
    $filename = basename($filename);;
    set_config('logofile', $filename);

    return($filename);
}



/**
 * Resize an image to fit within the given rectange, maintaing aspect ratio
 *
 * @param string Path to image
 * @param string Destination file - without file extention
 * @param int Width to resize to
 * @param int Height to resize to
 * @param string Force image to this format
 *
 * @global $CFG
 * @return string Path to new file else false
 */
function resize_image($originalfile, $destination, $newwidth, $newheight, $forcetype = false) {
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

    $destname = $destination.'.'.$outputformat;

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

    switch($outputformat) {
        case 'jpeg':
           imagejpeg($im1, $destname, 90);
           break;
        case 'png':
           imagepng($im1, $destname, 9);
           break;
        default:
            return false;
    }
    return $destname;
}


/**
 * Create course categories
 *
 * @param int $fromyear starting year of school
 * @param int $toyear   ending year of school
 * @return bool
 */
function local_createcats($fromyear, $toyear) {

    $cats = array(array('name'      => 'courses',
                        'visible'   => 1),
                  array('name'      => 'sandpits',
                        'visible'   => 0),
                  array('name'      => 'templates',
                        'visible'   => 0));


    $newcategory = new stdClass();
    $newcategory->description = '';
    $newcategory->parent = 0;

    // Delete the default cat moodle creates if it's empty
    if ($misccat = get_record('course_categories', 'name', 'Miscellaneous')) {
        if(!record_exists('course', 'category', $misccat->id)) {
            delete_records('course_categories', 'id', $misccat->id);
        }
    }

    $i = 1;
    foreach($cats as $cat) {
        $newcategory->name = get_string($cat['name'], 'local_nzschools');
        $newcategory->visible = $cat['visible'];
        $sortorder = $i++;

        if (!record_exists('course_categories', 'name', $newcategory->name)) {
            if (!$newcategory->id = insert_record('course_categories', $newcategory)) {
                error("Could not insert the new category '$newcategory->name' ");
            }
            $newcategory->context = get_context_instance(CONTEXT_COURSECAT, $newcategory->id);
            mark_context_dirty($newcategory->context->path);

            if ($name = 'templates') {
                set_config('templatecat', $newcategory->id);
            }
        }

    }

    $coursecatid = get_field('course_categories', 'id', 'name', get_string('courses', 'local_nzschools'));


    for($year = $fromyear;$year <= $toyear;$year++) {
        $name = get_string('catyear', 'local_nzschools', $year);
        $newcategory->name = $name;
        $newcategory->sortorder = $i++;
        $newcategory->parent = $coursecatid;
        $newcategory->visible = 1;
        $newcategory->icon = 'year'.$year.'.png';

        if (!record_exists('course_categories', 'name', $name)) {
            if (!$newcategory->id = insert_record('course_categories', $newcategory)) {
                error("Could not insert the new category '$newcategory->name' ");
            }
            $newcategory->context = get_context_instance(CONTEXT_COURSECAT, $newcategory->id);
            mark_context_dirty($newcategory->context->path);

        }
    }

    fix_course_sortorder(); // Required to build course_categories.depth and .path.
}


/**
 * Restore course templates silently
 *
 * @param string $dir Directory containing moodle course backups
 * @global $CFG
 * @global $SESSION
 */
function local_restoretemplates($dir) {
    global $CFG, $SESSION;

    require_once($CFG->dirroot.'/backup/restorelib.php');
    if (!is_dir($dir)) {
        print_error('templatedirnotfound', 'local_nzschools');
    }

    if (!record_exists('course_categories', 'id', $CFG->templatecat)) {
        print_error('templatecatnotfound', 'local_nzschools');
    }

    $d = dir($dir);
    while (($entry = $d->read()) !== false) {
        if (!is_file($dir.'/'.$entry))
            continue;

        $course = new stdClass();
        $course->category = $CFG->templatecat;
        $course->fullname = $entry;
        $course->shortname = $entry;
        $course->idnumber = $entry;
        $course->format = 'weeks';
        $course->numsections = 20;

        if ($destcourse = create_course($course)) {
            $origdebug = $CFG->debug;
            $CFG->debug = DEBUG_MINIMAL;
            error_reporting($CFG->debug);
            import_backup_file_silently($dir.'/'.$entry, $destcourse->id, true, true);
            error_reporting($origdebug);
            $CFG->debug = $origdebug;
        }

        // Sync the course to the backup file
        // HACK - peek at the info moodle backup restore keeps in the session

        $course = $destcourse;
        $course->fullname       = $SESSION->course_header->course_fullname;
        $course->summary        = $SESSION->course_header->course_summary;
        $course->shortname      = $SESSION->course_header->course_shortname;
        $course->numsections    = $SESSION->course_header->course_numsections;
        $course->format         = $SESSION->course_header->course_format;

        update_course(addslashes_object($course));
    }
}


/**
 * Get list of stock course icons
 *
 * @return array
 */
function local_get_stock_icons($type) {
    global $CFG;
    $icons = array('custom' => get_string('customicon', 'local_nzschools'),
                   'none' => get_string('none', 'local_nzschools'));

    if ($path = local_get_stock_icon_dir($type)) {
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
 * Return the path to the course icon directory
 *
 * @global $CFG
 * @return string
 */
function local_get_stock_icon_dir($type) {
    global $CFG;

    if ($type == 'course') {
        $dir = 'courseicons';
    } elseif ($type == 'coursecategory') {
        $dir = 'coursecategoryicons';
    } else {
        return(false);
    }

    if (is_dir($CFG->themedir.'/'.$CFG->theme.'/'.$dir)) {
        return($CFG->themedir.'/'.$CFG->theme.'/'.$dir);
    } else if (is_dir($CFG->themedir.'/standard/'.$dir)) {
        return($CFG->themedir.'/standard/'.$dir);
    }
    else {
        return(false);
    }
}




/**
 * Update course icon
 *
 * @param object $course Course object
 * @param object $data Formslib form data
 * @param object $mform Moodle form
 * @global $CFG
 */
function local_update_course_icon($course, $data, &$mform) {
    global $CFG;

    $updatecourse = new stdClass;
    $updatecourse->id = $course->id;

    if ($data->icon == 'custom') {
        //Move icon to course
        $updatecourse->icon = 'custom';

        $dest = $CFG->dataroot.'/'.$course->id.'/icons/';
        make_upload_directory($dest);
        $mform->save_files($dest);

        if ($filename =  $mform->get_new_filename()) {
            resize_image($dest.$filename, $dest.'large', 50, 50, 'png');
            resize_image($dest.$filename, $dest.'small', 25, 25, 'png');
            unlink($dest.$filename);
        }

    } elseif ($data->icon == 'none') {
        $updatecourse->icon = '';
    } else {
        $updatecourse->icon = $data->icon;
    }

    update_record('course', $updatecourse);
}


/**
 * Update course icon
 *
 * @param object $course Course object
 * @param object $data Formslib form data
 * @param object $mform Moodle form
 * @global $CFG
 */
function local_update_coursecategory_icon($coursecategory, $data, &$mform) {
    global $CFG;

    $site = get_site();

    $updatecoursecat = new stdClass;
    $updatecoursecat->id = $coursecategory->id;

    if ($data->icon == 'custom') {
        //Move icon to course
        $updatecoursecat->icon = 'custom';

        $dest = $CFG->dataroot.'/'.$site->id.'/icons/coursecategory/';
        make_upload_directory($dest);
        $mform->save_files($dest);

        if ($filename =  $mform->get_new_filename()) {
            resize_image($dest.$filename, $dest.'large', 50, 50, 'png');
            resize_image($dest.$filename, $dest.'small', 25, 25, 'png');
            unlink($dest.$filename);
        }

    } elseif ($data->icon == 'none') {
        $updatecoursecat->icon = '';
    } else {
        $updatecoursecat->icon = $data->icon;
    }
    update_record('course_categories', $updatecoursecat);
}



/**
 * Send icon data
 *
 * @param int $courseid Course id
 * @param string $courseicon Name of icon to send
 * @param string $size Icon size to send
 * @global $CFG
 */
function local_output_course_icon($courseid, $courseicon, $size='large') {
    global $CFG;

    $icondir = local_get_stock_icon_dir('course');
    $iconname = 'default.png';
    $icon = $icondir.'/'.$size.'/default.png';

    if ($courseicon == 'custom') {
        if (is_file($CFG->dataroot.'/'.$courseid.'/icons/'.$size.'.png')) {
            $icon = $CFG->dataroot.'/'.$courseid.'/icons/'.$size.'.png';
            $iconname = 'customicon.png';
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
function local_output_coursecategory_icon($iconname, $size='large') {
    global $CFG;

    $site = get_site();

    $icondir = local_get_stock_icon_dir('coursecategory');
    $iconpath = 'default.png';
    $icon = $icondir.'/'.$size.'/default.png';

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
 * Return img tag to a course icon
 *
 * @param object $course Course object
 * @param string $size Size of icon set to use
 * @global $CFG
 * @global $COURSE
 * @return string img tag
 */
function local_course_icon_tag($course=null, $size='large') {
    global $CFG, $COURSE;
    if (!isset($course)) {
        $course = $COURSE;
    }
    return '<img src="'.$CFG->wwwroot.'/local/icon.php?id='.$course->id.'&amp;icon='.$course->icon.'&amp;size='.$size.'&type=course" alt="'.$course->shortname.'" class="class_icon" />';
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
function local_coursecategory_icon_tag($coursecat, $size='large') {
    global $CFG;
    return '<img src="'.$CFG->wwwroot.'/local/icon.php?icon='.$coursecat->icon.'&amp;size='.$size.'&type=coursecategory" alt="'.$coursecat->name.'" class="class_icon" />';
}


/**
 * Select a forground colour based on the background colour
 *
 * @param string $bg background colour
 * @param string $light_option colour to return if background is dark
 * @param string $dark_option colour to return if background is light
 * @return string forground colour
 */
function fg_colour($bg, $light_option = 'FFFFFF', $dark_option = '333333') {
    $bg = hexdec($bg);

    //rgb conversion
    $r = 0xFF & $bg >> 0x10;
    $g = 0xFF & $bg >> 0x08;
    $b = 0xFF & $bg;

    // Calculate brightness using a weighted distance between colours
    $brightness = sqrt( pow($r,2) * .241 + pow($g,2) * .691 + pow($b,2) *.068);
    if ($brightness < 165) { // an arbitrary cutoff point for choosing a fg colour
        return ($light_option);
    } else {
        return ($dark_option);
    }

}


/**
 * Return a URL to the site logo
 *
 * @return string logo url
 */
 function local_logo_url() {
    global $CFG;

    // Add a timestamp to prevent caching when logo changes
    if (isset($CFG->logofile) && is_file($CFG->dataroot.'/theme/'.$CFG->logofile)) {
        $timestamp = filemtime($CFG->dataroot.'/theme/'.$CFG->logofile);
    } else {
       $timestamp = 0;
    }

    return $CFG->wwwroot.'/local/logo.php?timestamp='.$timestamp;
 }

?>
