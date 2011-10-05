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
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class mod_forumanon_post_form extends moodleform {

    function definition() {

        global $CFG, $DB;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
        $coursecontext = $this->_customdata['coursecontext'];
        $modcontext    = $this->_customdata['modcontext'];
        $forum         = $this->_customdata['forum'];
        $post          = $this->_customdata['post'];

        // if $forum->maxbytes == '0' means we should use $course->maxbytes
        if ($forum->maxbytes == '0') {
            $forum->maxbytes = $course->maxbytes;
        }
        // TODO: add max files and max size support
        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext'=>true, 'context'=>$modcontext);

        $mform->addElement('header', 'general', '');//fill in the data depending on page params later using set_data
        $mform->addElement('text', 'subject', get_string('subject', 'forumanon'), 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('editor', 'message', get_string('message', 'forumanon'), null, $editoroptions);
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');

        if (isset($forum->id) && forumanon_is_forcesubscribed($forum)) {

            $mform->addElement('static', 'subscribemessage', get_string('subscription', 'forumanon'), get_string('everyoneissubscribed', 'forumanon'));
            $mform->addElement('hidden', 'subscribe');
            $mform->setType('subscribe', PARAM_INT);
            $mform->addHelpButton('subscribemessage', 'subscription', 'forumanon');

        } else if (isset($forum->forcesubscribe)&& $forum->forcesubscribe != FORUMANON_DISALLOWSUBSCRIBE ||
                   has_capability('moodle/course:manageactivities', $coursecontext)) {

                $options = array();
                $options[0] = get_string('subscribestop', 'forumanon');
                $options[1] = get_string('subscribestart', 'forumanon');

                $mform->addElement('select', 'subscribe', get_string('subscription', 'forumanon'), $options);
                $mform->addHelpButton('subscribe', 'subscription', 'forumanon');
            } else if ($forum->forcesubscribe == FORUMANON_DISALLOWSUBSCRIBE) {
                $mform->addElement('static', 'subscribemessage', get_string('subscription', 'forumanon'), get_string('disallowsubscribe', 'forumanon'));
                $mform->addElement('hidden', 'subscribe');
                $mform->setType('subscribe', PARAM_INT);
                $mform->addHelpButton('subscribemessage', 'subscription', 'forumanon');
            }

        if (!empty($forum->maxattachments) && $forum->maxbytes != 1 && has_capability('mod/forumanon:createattachment', $modcontext))  {  //  1 = No attachments at all
            $mform->addElement('filemanager', 'attachments', get_string('attachment', 'forumanon'), null,
                array('subdirs'=>0,
                      'maxbytes'=>$forum->maxbytes,
                      'maxfiles'=>$forum->maxattachments,
                      'accepted_types'=>'*',
                      'return_types'=>FILE_INTERNAL));
            $mform->addHelpButton('attachments', 'attachment', 'forumanon');
        }

        if (empty($post->id) && has_capability('moodle/course:manageactivities', $coursecontext)) { // hack alert
            $mform->addElement('checkbox', 'mailnow', get_string('mailnow', 'forumanon'));
        }

        if (!empty($CFG->forumanon_enabletimedposts) && !$post->parent && has_capability('mod/forumanon:viewhiddentimedposts', $coursecontext)) { // hack alert
            $mform->addElement('header', '', get_string('displayperiod', 'forumanon'));

            $mform->addElement('date_selector', 'timestart', get_string('displaystart', 'forumanon'), array('optional'=>true));
            $mform->addHelpButton('timestart', 'displaystart', 'forumanon');

            $mform->addElement('date_selector', 'timeend', get_string('displayend', 'forumanon'), array('optional'=>true));
            $mform->addHelpButton('timeend', 'displayend', 'forumanon');

        } else {
            $mform->addElement('hidden', 'timestart');
            $mform->setType('timestart', PARAM_INT);
            $mform->addElement('hidden', 'timeend');
            $mform->setType('timeend', PARAM_INT);
            $mform->setConstants(array('timestart'=> 0, 'timeend'=>0));
        }

        if (groups_get_activity_groupmode($cm, $course)) { // hack alert
            if (empty($post->groupid)) {
                $groupname = get_string('allparticipants');
            } else {
                $group = groups_get_group($post->groupid);
                $groupname = format_string($group->name);
            }
            $mform->addElement('static', 'groupinfo', get_string('group'), $groupname);
        }
        
        //-------------------------------------------------------------------------------
        //  forumanon checkbox
        $forumanonon = TRUE;
        if ($forumanonon) {
            $mform->addElement('advcheckbox', 'anonymize', get_string('anonymize', 'forumanon'), null, array('onchange' => "if(this.checked == true) document.getElementById('anon_con_box').style.display = 'block'; else document.getElementById('anon_con_box').style.display = 'none'; "), array(0, 1));
            $mform->addHelpButton('anonymize', 'anonymize', 'forumanon');
			$anon_display = $this->_customdata['post']->anonymize ? 'block' : 'none';
            $mform->addElement('html', '<div id="anon_con_box" style="display:'.$anon_display.';">');
            $mform->addElement('html', '<div class="fitem">');
            $mform->addElement('html', '<div class="fitemtitle">'.get_string('anonconditiontitle', 'forumanon').'</div>');
            $mform->addElement('html', '<div class="felement">'.get_string('anonconditiontext', 'forumanon').'</div>');
            $mform->addElement('html', '</div>');
            //$mform->addElement('checkbox', 'anon_confirm', get_string('anonconfirm', 'forumanon'));
            $mform->addElement('advcheckbox', 'anon_confirm', get_string('anonconfirm', 'forumanon'), null, null, array(0, 1));
            $mform->addElement('html', '</div>');
        }
        //-------------------------------------------------------------------------------
        // buttons
        if (isset($post->edit)) { // hack alert
            $submit_string = get_string('savechanges');
        } else {
            $submit_string = get_string('posttoforum', 'forumanon');
        }
        $this->add_action_buttons(false, $submit_string);

        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'forum');
        $mform->setType('forum', PARAM_INT);

        $mform->addElement('hidden', 'discussion');
        $mform->setType('discussion', PARAM_INT);

        $mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'reply');
        $mform->setType('reply', PARAM_INT);
    }

    function validation($data, $files) {
		global $DB, $USER;
        $errors = parent::validation($data, $files);
        if (($data['timeend']!=0) && ($data['timestart']!=0) && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('timestartenderror', 'forumanon');
        }
        if (empty($data['message']['text'])) {
            $errors['message'] = get_string('erroremptymessage', 'forumanon');
        }
        if (empty($data['subject'])) {
            $errors['subject'] = get_string('erroremptysubject', 'forumanon');
        }
        if (($data['anonymize']==1) && ($data['anon_confirm']==0)) {
        	$errors['anon_confirm'] = get_string('erroremptyanonconfirm', 'forumanon');
        } else {
			if (0 == $DB->count_records('forumanon_statement',array('userid'=>$USER->id))) {
				$statement = new stdClass();
				$statement->userid = $USER->id;
				$DB->insert_record('forumanon_statement',$statement, FALSE);
			}
		}
        return $errors;
    }
}

