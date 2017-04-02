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
 * videochat block settings.
 *
 * @copyright 2016 Kien Vu <vuthekien@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
$callstatus = array('incall' => 1, 'ringing' => 2, 'rejected' => 3);

$courseid = optional_param('cid', 0, PARAM_INT);
if (!isloggedin() || $courseid == 0) {
    die();
}

$mess = '';
$validusers = array();
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);
$PAGE->set_context($coursecontext);
$html = '';

$timetoshowusers = (isset($CFG->block_videochat_defaultactivetime) ? $CFG->block_videochat_defaultactivetime : 300);
if (isset($CFG->block_online_users_timetosee)) {
    $timetoshowusers = $CFG->block_online_users_timetosee * 60;
}
$now = time();
$timefrom = 100 * floor(($now - $timetoshowusers) / 100); //  Round to nearest 100 seconds for better query cache.

// Calculate if we are in separate groups.
$isseparategroups = ($course->groupmode == SEPARATEGROUPS
                     && $course->groupmodeforce
                     && !has_capability('moodle/site:accessallgroups', $coursecontext));

// Get the user current group.
$currentgroup = $isseparategroups ? groups_get_course_group($course) : null;

$groupmembers = '';
$groupselect = '';
$params = array();

// Add this to the SQL to show only group users.
if ($currentgroup !== null) {
    $groupmembers = ', {groups_members} gm';
    $groupselect = 'AND u.id = gm.userid AND gm.groupid = :currentgroup';
    $params['currentgroup'] = $currentgroup;
}

$userfields = user_picture::fields('u', array('username'));
$params['now'] = $now;
$params['timefrom'] = $timefrom;
if ($course->id == SITEID or $coursecontext->contextlevel < CONTEXT_COURSE) {  //  Site-level.
    $sql = "SELECT $userfields, MAX(u.lastaccess) AS lastaccess
			  FROM {user} u $groupmembers
			 WHERE u.lastaccess > :timefrom
				   AND u.lastaccess <= :now
				   AND u.deleted = 0
				   $groupselect
		  GROUP BY $userfields
		  ORDER BY lastaccess DESC ";

    $csql = "SELECT COUNT(u.id)
			  FROM {user} u $groupmembers
			 WHERE u.lastaccess > :timefrom
				   AND u.lastaccess <= :now
				   AND u.deleted = 0
				   $groupselect";
} else {
    //  Course level - show only enrolled users for now.

    list($esqljoin, $eparams) = get_enrolled_sql($coursecontext);
    $params = array_merge($params, $eparams);

    $sql = "SELECT $userfields, MAX(ul.timeaccess) AS lastaccess
			  FROM {user_lastaccess} ul $groupmembers, {user} u
			  JOIN ($esqljoin) euj ON euj.id = u.id
			 WHERE ul.timeaccess > :timefrom
				   AND u.id = ul.userid
				   AND ul.courseid = :courseid
				   AND ul.timeaccess <= :now
				   AND u.deleted = 0
				   $groupselect
		  GROUP BY $userfields
		  ORDER BY lastaccess DESC";

    $csql = "SELECT COUNT(u.id)
			  FROM {user_lastaccess} ul $groupmembers, {user} u
			  JOIN ($esqljoin) euj ON euj.id = u.id
			 WHERE ul.timeaccess > :timefrom
				   AND u.id = ul.userid
				   AND ul.courseid = :courseid
				   AND ul.timeaccess <= :now
				   AND u.deleted = 0
				   $groupselect";

    $params['courseid'] = $course->id;
}

// Calculate minutes.
$minutes = floor($timetoshowusers / 60);

//  Verify if we can see the list of users, if not just print number of users.
if (!has_capability('block/online_users:viewlist', $coursecontext)) {
    if (!$usercount = $DB->count_records_sql($csql, $params)) {
        $usercount = get_string('none');
    }
    $html = '<div class="info">'.get_string('periodnminutes', 'block_videochat', $minutes).": $usercount</div>";

    return $this->content;
}

if ($users = $DB->get_records_sql($sql, $params, 0, 50)) {   //  We'll just take the most recent 50 maximum.
    foreach ($users as $user) {
        $users[$user->id]->fullname = fullname($user);
    }
} else {
    $users = array();
}

if (count($users) < 50) {
    $usercount = '';
} else {
    $usercount = $DB->count_records_sql($csql, $params);
    $usercount = ": $usercount";
}

$html = '<h4>'.get_string('activeuser', 'block_videochat').'</h4>';

$html .= '<div class="info">('.get_string('periodnminutes', 'block_videochat', $minutes)."$usercount)</div>";

if (!empty($users)) {
    $html .= "<div id='call-status'></div><ul class='list'>\n";
    if (isloggedin() && has_capability('moodle/site:sendmessage', $coursecontext)
                   && !empty($CFG->messaging) && !isguestuser()) {
        $canshowicon = true;
    } else {
        $canshowicon = false;
    }
    foreach ($users as $user) {
        if ($user->id == $USER->id) { // Ignore yourself.
            continue;
        }
        array_push($validusers, $user->id);
        $html .= '<li class="listentry">';
        $timeago = format_time($now - $user->lastaccess);

        $anchortag = '';
        if ($canshowicon and ($USER->id != $user->id) and !isguestuser($user)) {  //  Only when logged in and messaging active etc.
            $anchortag = '<a class="vc-mess" href="'.$CFG->wwwroot.'/message/index.php?id='.
                $user->id.'" title="'.get_string('messageselectadd').'"></a>';
        }
        if (!isguestuser($user)) {
            $vcall = '<span class="vc-ring" onclick="javascript: ring('.$user->id.')"></span>';
            $html .= '<div class="user">';

            $html .= $vcall.$anchortag.$OUTPUT->user_picture($user, array('size' => 40, 'alttext' => false, 'link' => false)).
                '<span class="vc-fullname">'.$user->fullname.'</span></div>';
        }
        $html .= "</li>\n";
    }
    $html .= '</ul><div class="clearer"><!-- --></div>';
} else {
    $html .= '<div class="info">'.get_string('none').'</div>';
}

$action = optional_param('action', '', PARAM_ALPHANUM);

if ($action == 'ring') {
    $touserid = optional_param('touser', 0, PARAM_INT);
    if ($touserid == 0 || !in_array($touserid, $validusers)) {
        $mess = '<div class="vc-error"><span onclick="close_mess(this);" class="vc-close-message"></span>'.
            get_string('invaliduser', 'block_videochat').'</div>';
    } else {
        if ($DB->record_exists_sql('SELECT * FROM {videochat}
                WHERE (fromuser= :fromuser OR
                touser= :touser) AND
                (status= :callstatusringing OR status= :callstatusincall)',
                array('fromuser' => $touserid,
                        'touser' => $touserid,
                        'callstatusringing' => $callstatus['ringing'],
                        'callstatusincall' => $callstatus['incall']))) {
            $touser = $DB->get_record('user', array('id' => $touserid));
            $mess = '<div class="vc-error"><span onclick="close_mess(this);" class="vc-close-message"></span>'.
                get_string('currentbusy', 'block_videochat', fullname($touser)).'</div>';
        } else if ($DB->record_exists_sql('SELECT * FROM {videochat}
                                                WHERE (fromuser= :fromuser OR touser= :touser) AND
                                                (status= :callstatusringing OR status= :callstatusincall)',
                                                 array('fromuser' => $USER->id,
                                                       'touser' => $USER->id,
                                                       'callstatusringing' => $callstatus['ringing'],
                                                       'callstatusincall' => $callstatus['incall']))) {

            $touser = $DB->get_record('user', array('id' => $touserid));
            $mess = '<div class="vc-error">
                        <span onclick="close_mess(this);" class="vc-close-message"></span>
                            '.get_string('youcurrentbusy', 'block_videochat').'
                     </div>';
        } else {
            $sql = 'SELECT * FROM {videochat} WHERE fromuser= :fromuser AND touser= :touser ORDER BY updatedtime DESC LIMIT 1';
            $curcall = $DB->get_record_sql($sql, array('fromuser' => $USER->id, 'touser' => $touserid));
            if ($curcall) {
                $curcall->status = $callstatus['ringing'];
                $curcall->updatedtime = time();
                $DB->update_record('videochat', $curcall);
            } else {
                $curcall = new StdClass();
                $curcall->fromuser = $USER->id;
                $curcall->touser = $touserid;
                $curcall->status = $callstatus['ringing'];
                $curcall->updatedtime = time();
                $DB->insert_record('videochat', $curcall);
            }
        }
    }
} else if ($action == 'pickup') {
    $fromuserid = optional_param('fromuser', 0, PARAM_INT);
    if ($fromuserid == 0 || !in_array($fromuserid, $validusers)) {
        $mess = '<div class="vc-error">
                    <span onclick="close_mess(this);" class="vc-close-message"></span>
                    '.get_string('invaliduser', 'block_videochat').'
                 </div>';
    } else {
        if ($DB->record_exists_sql('SELECT * FROM {videochat}
                                        WHERE (fromuser= :fromuser AND touser <> :touser)
                                                AND (status= :callstatusringing OR status= :callstatusincall)',
                                    array('fromuser' => $fromuserid,
                                            'touser' => $USER->id,
                                            'callstatusringing' => $callstatus['ringing'],
                                            'callstatusincall' => $callstatus['incall']))) {
            $touser = $DB->get_record('user', array('id' => $touserid));
            $mess = '<div class="vc-error">
                        <span onclick="close_mess(this);" class="vc-close-message"></span>
                        '.get_string('currentbusy', 'block_videochat', fullname($touser)).'
                     </div>';
        } else {
            $sql = 'SELECT * FROM {videochat}
                             WHERE fromuser= :fromuser AND touser= :touser
                             ORDER BY updatedtime DESC LIMIT 1';
            $curcall = $DB->get_record_sql($sql, array('fromuser' => $fromuserid, 'touser' => $USER->id));
            if ($curcall) {
                $curcall->status = $callstatus['incall'];
                $curcall->room = uniqid();
                $curcall->updatedtime = time();
                $DB->update_record('videochat', $curcall);
            } else {
                $mess = '<div class="vc-error">
                            <span onclick="close_mess(this);" class="vc-close-message"></span>
                            '.get_string('invalidcall', 'block_videochat').'
                         </div>';
            }
        }
    }
} else if ($action == 'hangup') {
    if (!$DB->delete_records_select('videochat', "fromuser={$USER->id} OR touser = {$USER->id}")) {
        $mess = '<div class="vc-error">
                    <span onclick="close_mess(this);" class="vc-close-message"></span>
                    '.get_string('errorhangup', 'block_videochat').'
                  </div>';
    }
}

$incallhtml = '';
$status = 0;
$room = '';
$sql = 'SELECT * FROM {videochat} WHERE (fromuser= :fromuser OR touser= :touser) AND status= :callstatusincall';

$incalls = $DB->get_records_sql($sql, array('fromuser' => $USER->id,
                                            'touser' => $USER->id,
                                            'callstatusincall' => $callstatus['incall']));
if ($incalls) {
    $incallhtml .= '<h4>'.get_string('incall', 'block_videochat').'</h4>';
    foreach ($incalls as $call) {
        $fromuser = $DB->get_record('user', array('id' => $call->fromuser));
        $fuserpicture = new user_picture($fromuser);
        $fsrc = $fuserpicture->get_url($PAGE)->out();
        $touser = $DB->get_record('user', array('id' => $call->touser));
        $tuserpicture = new user_picture($touser);
        $tsrc = $tuserpicture->get_url($PAGE)->out();

        $incallhtml .= '<div class="incall">
                            <div>
                                <span class="fromuser">
                                    <span class="profile-image" style="background-image: url(\''.$fsrc.'\')">
                                        <img src="'.$CFG->wwwroot.'/blocks/videochat/images/active.gif" class="cv-active"/>
                                     </span><br/>'.fullname($fromuser).'
                                </span>
                                <span class="incall-middle" onclick="javascript: opentalk(\''.$call->room.'\')">
                                    <img src="'.$CFG->wwwroot.'/blocks/videochat/images/active.gif" class="cv-active"/>
                                </span>
                                <span class="touser">
                                    <span class="profile-image" style="background-image: url(\''.$tsrc.'\')">
                                        <img src="'.$CFG->wwwroot.'/blocks/videochat/images/active.gif" class="cv-active"/>
                                    </span><br/>'.fullname($touser).'
                                 </span>
                              </div>
                              <div class="cv-action">
                                <span class="cv-hangup" onclick="javascript: hangup()"></span>
                              </div>
                          </div>
                          <input type="hidden" id="vc-call-status" value="'.$call->status.'" />';
        $status = $call->status;
        $room = $call->room;
    }
}

$comingcallhtml = '';
$sql = 'SELECT * FROM {videochat} WHERE touser= :touser AND status= :callstatusringing';
$comingcalls = $DB->get_records_sql($sql, array('touser' => $USER->id, 'callstatusringing' => $callstatus['ringing']));

if ($comingcalls) {
    $comingcallhtml .= '<h4>'.get_string('comingcall', 'block_videochat').'</h4>';
    foreach ($comingcalls as $call) {
        $fromuser = $DB->get_record('user', array('id' => $call->fromuser));
        $fuserpicture = new user_picture($fromuser);
        $fsrc = $fuserpicture->get_url($PAGE)->out();

        $comingcallhtml .= '
        <div class="comingcall">
            <div>
                <span class="fromuser">
                    <span class="profile-image" style="background-image: url(\''.$fsrc.'\')">
                        <img src="'.$CFG->wwwroot.'/blocks/videochat/images/active.gif" class="cv-active"/>
                    </span><br/>'.fullname($fromuser).'
                </span>
            </div>
            <div class="cv-action">
                <span class="cv-pickup" onclick="javascript: pickup('.$call->fromuser.','.$call->touser.')">
                    <img src="'.$CFG->wwwroot.'/blocks/videochat/images/active.gif" class="cv-active"/>
                </span>
                <span class="cv-hangup" onclick="javascript: hangup()"></span>
            </div>
        </div>
        <input type="hidden" id="vc-call-status" value="'.$call->status.'" />';
    }
}
$ringingcallhtml = '';
$sql = 'SELECT * FROM {videochat} WHERE fromuser= :fromuser AND status= :callstatusringing';

$ringingcalls = $DB->get_records_sql($sql, array('fromuser' => $USER->id, 'callstatusringing' => $callstatus['ringing']));

if ($ringingcalls) {
    $ringingcallhtml .= '<h4>'.get_string('outgoingcall', 'block_videochat').'</h4>';
    foreach ($ringingcalls as $call) {
        $touser = $DB->get_record('user', array('id' => $call->touser));
        $tuserpicture = new user_picture($touser);
        $tsrc = $tuserpicture->get_url($PAGE)->out();
        $ringingcallhtml .= '
        <div class="ringingcall">
            <div>
                <span class="touser">
                    <span class="profile-image" style="background-image: url(\''.$tsrc.'\')">
                        <img src="'.$CFG->wwwroot.'/blocks/videochat/images/active.gif" class="cv-active"/>
                    </span><br/>'.fullname($touser).'
                </span>
            </div>
            <div class="cv-action">
                <span class="cv-hangup" onclick="javascript: hangup()"></span>
            </div>
        </div>
        <input type="hidden" id="vc-call-status" value="'.$call->status.'" />';
    }
}

$return = new stdClass();
$return->message = $mess;
$return->call = $incallhtml.$comingcallhtml.$ringingcallhtml;
$return->userlist = $html;
$return->status = $status;
$return->room = $room;
header('Content-Type: application/json');
echo json_encode($return);
