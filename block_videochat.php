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
defined('MOODLE_INTERNAL') || die();

class block_videochat extends block_base
{
    /**
     * Block initialization.
     */
    public function init() {
        $this->title = get_string('videochat', 'block_videochat');
    }

    /**
     * Used to generate the content for the block.
     *
     * @return string
     */
    public function get_content() {
        global $USER, $CFG, $DB, $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }
        $this->content->text .= '
			<link rel="stylesheet" href="'.$CFG->wwwroot.'/blocks/videochat/styles.css"/>
			<div id="videochat-message"></div>
			<div id="videochat-call"></div>
			<div id="videochat-content"></div>
		';

        $this->content->text .= "
			<script>
			var vcurl='{$CFG->wwwroot}/blocks/videochat/ajax.php';
			var courseid={$this->page->course->id};
			var vcwindow;
			var conurl='".(isset($CFG->block_videochat_conferenceurl) ? $CFG->block_videochat_conferenceurl : 'https://openandtalk.com/')."';
			callAction(courseid,'');
			window.setInterval(function(){
				callAction(courseid,'');
			}, 2000);
			function ring(touser){
				callAction(courseid,'&action=ring&touser='+touser);
			}
			function pickup(fromuser){
				callAction(courseid,'&action=pickup&fromuser='+fromuser);
			}
			function hangup(){
				callAction(courseid,'&action=hangup');
				if(vcwindow!=null) {
					vcwindow.close();
				}
			}
			function opentalk(room){
				vcwindow=window.open(conurl+room);
			}
			function close_mess(e){
				 e.parentNode.parentNode.removeChild(e.parentNode);
			}
			function callAction(cid,query){
				var xhttp = new XMLHttpRequest();
				xhttp.onreadystatechange = function() {
				  if (xhttp.readyState == 4 && xhttp.status == 200) {
					if(xhttp.responseText!=''){
						 var data = JSON.parse(xhttp.responseText);
						 var callstatus=document.getElementById('vc-call-status');
						 if(data.status==1 && callstatus !=null && callstatus.value!=1 && data.room !=''){
							 vcwindow=window.open(conurl+data.room);
						 }
						 var vcmessage=document.getElementById('videochat-message');
						 vcmessage.innerHTML = vcmessage.innerHTML + data.message;
						 document.getElementById('videochat-call').innerHTML = data.call;
						 document.getElementById('videochat-content').innerHTML = data.userlist;
					}
				  }
				};
				if (document.getElementById('vc-call-status') !=null){
					query='&currentstatus='+document.getElementById('vc-call-status').value+query;
				}
				xhttp.open('GET', vcurl+'?cid='+cid+query, true);
				xhttp.send();
			}
			</script>
		";

        return $this->content;
    }

    public function build_block_content() {
    }

    /**
     * Core function, specifies where the block can be used.
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'course-view' => true,
        );
    }

    /**
     * Allow the block to have a configuration page.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Allow the block to be uninstall.
     *
     * @return bool
     */
    public function can_uninstall_plugin() {
        return true;
    }

    /**
     * Allows the block to be added multiple times to a single page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }
}
