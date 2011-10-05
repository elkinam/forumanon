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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package mod-forum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');

    $d      = required_param('d', PARAM_INT);                // Discussion ID
    $parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
    $mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
    $move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another forum
    $mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
    $postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

    $url = new moodle_url('/mod/forumanon/discuss.php', array('d'=>$d));
    if ($parent !== 0) {
        $url->param('parent', $parent);
    }
    $PAGE->set_url($url);

    $discussion = $DB->get_record('forumanon_discussions', array('id' => $d), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
    $forum = $DB->get_record('forumanon', array('id' => $discussion->forum), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forumanon', $forum->id, $course->id, false, MUST_EXIST);

    require_course_login($course, true, $cm);

/// Add ajax-related libs
    $PAGE->requires->yui2_lib('event');
    $PAGE->requires->yui2_lib('connection');
    $PAGE->requires->yui2_lib('json');

    // move this down fix for MDL-6926
    require_once($CFG->dirroot.'/mod/forumanon/lib.php');

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/forumanon:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'forumanon');

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->forumanon_enablerssfeeds) && $forum->rsstype && $forum->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname) . ': %fullname%';
        rss_add_http_header($modcontext, 'mod_forumanon', $forum, $rsstitle);
    }

    if ($forum->type == 'news') {
        if (!($USER->id == $discussion->userid || (($discussion->timestart == 0
            || $discussion->timestart <= time())
            && ($discussion->timeend == 0 || $discussion->timeend > time())))) {
            print_error('invaliddiscussionid', 'forumanon', "$CFG->wwwroot/mod/forumanon/view.php?f=$forum->id");
        }
    }

/// move discussion if requested
    if ($move > 0 and confirm_sesskey()) {
        $return = $CFG->wwwroot.'/mod/forumanon/discuss.php?d='.$discussion->id;

        require_capability('mod/forumanon:movediscussions', $modcontext);

        if ($forum->type == 'single') {
            print_error('cannotmovefromsingleforum', 'forumanon', $return);
        }

        if (!$forumto = $DB->get_record('forumanon', array('id' => $move))) {
            print_error('cannotmovetonotexist', 'forumanon', $return);
        }

        if (!$cmto = get_coursemodule_from_instance('forumanon', $forumto->id, $course->id)) {
            print_error('cannotmovetonotfound', 'forumanon', $return);
        }

        if (!coursemodule_visible_for_user($cmto)) {
            print_error('cannotmovenotvisible', 'forumanon', $return);
        }

        require_capability('mod/forumanon:startdiscussion', get_context_instance(CONTEXT_MODULE,$cmto->id));

        if (!forumanon_move_attachments($discussion, $forum->id, $forumto->id)) {
            echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
        }
        $DB->set_field('forumanon_discussions', 'forum', $forumto->id, array('id' => $discussion->id));
        $DB->set_field('forumanon_read', 'forumid', $forumto->id, array('discussionid' => $discussion->id));
        add_to_log($course->id, 'forumanon', 'move discussion', "discuss.php?d=$discussion->id", $discussion->id, $cmto->id);

        require_once($CFG->libdir.'/rsslib.php');
        require_once($CFG->dirroot.'/mod/forumanon/rsslib.php');

        // Delete the RSS files for the 2 forums to force regeneration of the feeds
        forumanon_rss_delete_file($forum);
        forumanon_rss_delete_file($forumto);

        redirect($return.'&moved=-1&sesskey='.sesskey());
    }

    add_to_log($course->id, 'forumanon', 'view discussion', $PAGE->url->out(false), $discussion->id, $cm->id);

    unset($SESSION->fromdiscussion);

    if ($mode) {
        set_user_preference('forumanon_displaymode', $mode);
    }

    $displaymode = get_user_preferences('forumanon_displaymode', $CFG->forumanon_displaymode);

    if ($parent) {
        // If flat AND parent, then force nested display this time
        if ($displaymode == FORUMANON_MODE_FLATOLDEST or $displaymode == FORUMANON_MODE_FLATNEWEST) {
            $displaymode = FORUMANON_MODE_NESTED;
        }
    } else {
        $parent = $discussion->firstpost;
    }

    if (! $post = forumanon_get_post_full($parent)) {
        print_error("notexists", 'forumanon', "$CFG->wwwroot/mod/forumanon/view.php?f=$forum->id");
    }


    if (!forumanon_user_can_view_post($post, $course, $cm, $forum, $discussion)) {
        print_error('nopermissiontoview', 'forumanon', "$CFG->wwwroot/mod/forumanon/view.php?id=$forum->id");
    }

    if ($mark == 'read' or $mark == 'unread') {
        if ($CFG->forumanon_usermarksread && forumanon_tp_can_track_forums($forum) && forumanon_tp_is_tracked($forum)) {
            if ($mark == 'read') {
                forumanon_tp_add_read_record($USER->id, $postid);
            } else {
                // unread
                forumanon_tp_delete_read_records($USER->id, $postid);
            }
        }
    }

    $searchform = forumanon_search_form($course);

    $forumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($forumnode)) {
        $forumnode = $PAGE->navbar;
    } else {
        $forumnode->make_active();
    }
    $node = $forumnode->add(format_string($discussion->name), new moodle_url('/mod/forumanon/discuss.php', array('d'=>$discussion->id)));
    $node->display = false;
    if ($node && $post->id != $discussion->firstpost) {
        $node->add(format_string($post->subject), $PAGE->url);
    }

    $PAGE->set_title("$course->shortname: ".format_string($discussion->name));
    $PAGE->set_heading($course->fullname);
    $PAGE->set_button($searchform);
    echo $OUTPUT->header();

/// Check to see if groups are being used in this forum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

    $canreply = forumanon_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);
    if (!$canreply and $forum->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canreply = true;
        }
        if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
            // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this link too, they are asked to enrol instead
            $canreply = enrol_selfenrol_available($course->id);
        }
    }

/// Print the controls across the top
    echo '<div class="discussioncontrols clearfix">';

    if (has_capability('mod/forumanon:exportdiscussion', $modcontext) && (!empty($CFG->enableportfolios))) {
        require_once($CFG->libdir.'/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('forumanon_portfolio_caller', array('discussionid' => $discussion->id), '/mod/forumanon/locallib.php');
        $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_forumanon'));
        $buttonextraclass = '';
        if (empty($button)) {
            // no portfolio plugin available.
            $button = '&nbsp;';
            $buttonextraclass = ' noavailable';
        }
        echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
    } else {
        echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
    }

    // groups selector not needed here
    echo '<div class="discussioncontrol displaymode">';
    forumanon_print_mode_form($discussion->id, $displaymode);
    echo "</div>";

    if ($forum->type != 'single'
                && has_capability('mod/forumanon:movediscussions', $modcontext)) {

        echo '<div class="discussioncontrol movediscussion">';
        // Popup menu to move discussions to other forums. The discussion in a
        // single discussion forum can't be moved.
        $modinfo = get_fast_modinfo($course);
        if (isset($modinfo->instances['forumanon'])) {
            $forummenu = array();
            $sections = get_all_sections($course->id);
            foreach ($modinfo->instances['forumanon'] as $forumcm) {
                if (!$forumcm->uservisible || !has_capability('mod/forumanon:startdiscussion',
                    get_context_instance(CONTEXT_MODULE,$forumcm->id))) {
                    continue;
                }

                $section = $forumcm->sectionnum;
                $sectionname = get_section_name($course, $sections[$section]);
                if (empty($forummenu[$section])) {
                    $forummenu[$section] = array($sectionname => array());
                }
                if ($forumcm->instance != $forum->id) {
                    $url = "/mod/forumanon/discuss.php?d=$discussion->id&move=$forumcm->instance&sesskey=".sesskey();
                    $forummenu[$section][$sectionname][$url] = format_string($forumcm->name);
                }
            }
            if (!empty($forummenu)) {
                echo '<div class="movediscussionoption">';
                $select = new url_select($forummenu, '',
                        array(''=>get_string("movethisdiscussionto", "forumanon")),
                        'forummenu', get_string('move'));
                echo $OUTPUT->render($select);
                echo "</div>";
            }
        }
        echo "</div>";
    }
    echo '<div class="clearfloat">&nbsp;</div>';
    echo "</div>";

    if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
        $a = new stdClass();
        $a->blockafter  = $forum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
        echo $OUTPUT->notification(get_string('thisforumisthrottled','forumanon',$a));
    }

    if ($forum->type == 'qanda' && !has_capability('mod/forumanon:viewqandawithoutposting', $modcontext) &&
                !forumanon_user_has_posted($forum->id,$discussion->id,$USER->id)) {
        echo $OUTPUT->notification(get_string('qandanotify','forumanon'));
    }

    if ($move == -1 and confirm_sesskey()) {
        echo $OUTPUT->notification(get_string('discussionmoved', 'forumanon', format_string($forum->name,true)));
    }

    $canrate = has_capability('mod/forumanon:rate', $modcontext);
    forumanon_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate);

    echo $OUTPUT->footer();



