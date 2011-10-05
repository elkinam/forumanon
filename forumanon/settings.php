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
 * @package mod-forum
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/forumanon/lib.php');

    $settings->add(new admin_setting_configselect('forumanon_displaymode', get_string('displaymode', 'forumanon'),
                       get_string('configdisplaymode', 'forumanon'), FORUMANON_MODE_NESTED, forumanon_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('forumanon_replytouser', get_string('replytouser', 'forumanon'),
                       get_string('configreplytouser', 'forumanon'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('forumanon_shortpost', get_string('shortpost', 'forumanon'),
                       get_string('configshortpost', 'forumanon'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('forumanon_longpost', get_string('longpost', 'forumanon'),
                       get_string('configlongpost', 'forumanon'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('forumanon_manydiscussions', get_string('manydiscussions', 'forumanon'),
                       get_string('configmanydiscussions', 'forumanon'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $settings->add(new admin_setting_configselect('forumanon_maxbytes', get_string('maxattachmentsize', 'forumanon'),
                           get_string('configmaxbytes', 'forumanon'), 512000, get_max_upload_sizes($CFG->maxbytes)));
    }

    // Default number of attachments allowed per post in all forums
    $settings->add(new admin_setting_configtext('forumanon_maxattachments', get_string('maxattachments', 'forumanon'),
                       get_string('configmaxattachments', 'forumanon'), 9, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('forumanon_trackreadposts', get_string('trackforum', 'forumanon'),
                       get_string('configtrackreadposts', 'forumanon'), 1));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('forumanon_oldpostdays', get_string('oldpostdays', 'forumanon'),
                       get_string('configoldpostdays', 'forumanon'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('forumanon_usermarksread', get_string('usermarksread', 'forumanon'),
                       get_string('configusermarksread', 'forumanon'), 0));

    // Default time (hour) to execute 'clean_read_records' cron
    $options = array();
    for ($i=0; $i<24; $i++) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('forumanon_cleanreadtime', get_string('cleanreadtime', 'forumanon'),
                       get_string('configcleanreadtime', 'forumanon'), 2, $options));


    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'forumanon').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'forumanon');
    }
    $settings->add(new admin_setting_configselect('forumanon_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    $settings->add(new admin_setting_configcheckbox('forumanon_enabletimedposts', get_string('timedposts', 'forumanon'),
                       get_string('configenabletimedposts', 'forumanon'), 0));
}

