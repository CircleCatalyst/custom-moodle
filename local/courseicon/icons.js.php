<?php 

require('../../config.php');

?>

var $ = YAHOO.util.Dom.get;

function previewCourseIcon() {
    var iconimg = $('iconpreview');
    var iconselect = $('id_icon');

    switch( iconselect.value ){
    	case 'custom':
    		break;
    	default:
    		iconimg.src = '<?php echo $CFG->wwwroot ?>/local/courseicon/icon.php?id=<?php $COURSE->id ?>&size=large&type=course&icon='+iconselect.value;
    }
    return true;
}
