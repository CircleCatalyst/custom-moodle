<?php
$id = optional_param('id', SITEID, PARAM_INT);
print_box_start();
?>

<table cellpadding="9" cellspacing="0" class="blockconfigtable">
<tr valign="top">
    <td class="label">
        <?php print_string('searchprovider_label', 'block_extsearch') ?>
    </td>
    <td class="value">
<?php

$options['google'] = get_string('google', 'block_extsearch');
$selected = 'google';

$digitalnzapikey = get_config(NULL, 'block_extsearch_digitalnz_api_key');
if (!empty($digitalnzapikey)) {
    $options['digitalnz'] = get_string('digitalnz', 'block_extsearch');
    $options['edna'] = get_string('edna', 'block_extsearch');
    $selected = 'digitalnz';
}

$esfsclienttoken = get_config(NULL, 'block_extsearch_esfs_client_token');
if (!empty($esfsclienttoken)) {
    $options['esfs'] = get_string('esfs', 'block_extsearch');
    $selected = 'esfs';
}

if (isset($options)) {
    if (isset($this->config) && !empty($this->config->search_provider)) {
        $selected = $this->config->search_provider;
    }
    choose_from_menu($options, 'search_provider', $selected);
}
else {
    print_string('noneavailable', 'block_extsearch');
    echo '<input type="hidden" name="search_provider" value="" />';
}

?>
    </td>
</tr>

<tr valign="top">
    <td class="label">
        <?php print_string('googlesafesearch_label', 'block_extsearch') ?>
    </td>
    <td class="value">
<?php

$safesearch['active'] = get_string('googlesafesearch_active', 'block_extsearch');
$safesearch['moderate'] = get_string('googlesafesearch_moderate', 'block_extsearch');
$safesearch['off'] = get_string('googlesafesearch_off', 'block_extsearch');

$selected = 'moderate';
if (isset($this->config) && !empty($this->config->google_safesearch)) {
    $selected = $this->config->google_safesearch;
}

choose_from_menu($safesearch, 'google_safesearch', $selected);

?>
    </td>
</tr>
<tr>
    <td colspan="2" class="submit">
<?php
    $checked = '';
    if (isset($this->config) && !empty($this->config->popup_links)) {
        $checked = 'checked="checked"';
    }
?>
        <label for="popup_links"><?php print_string('popuplinks', 'block_extsearch') ?></label>
        <input type="checkbox" name="popup_links" id="popup_links" value="1" <?php print $checked ?> />
    </td>
</tr>
<tr>
    <td colspan="2" class="submit">
        <input type="submit" value="<?php print_string('savechanges') ?>" />
    </td>
</tr>
</table>

<p><i><?php print get_string('noteaboutsitewideconfig', 'block_extsearch', $CFG->wwwroot .'/admin/settings.php?section=blocksettingextsearch'); ?></i></p>

<?php print_box_end(); ?>
