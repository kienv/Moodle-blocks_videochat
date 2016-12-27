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
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings->add(new admin_setting_configselect('block_videochat_conferenceurl',
        new lang_string('conferenceurl', 'block_videochat'),
        new lang_string('conferenceurldesc', 'block_videochat'), 'https://openandtalk.com/',
        array('https://openandtalk.com/' => 'https://openandtalk.com/', 'http://appear.in/' => 'http://appear.in/')));

    $settings->add(new admin_setting_configtext('block_videochat_defaultactivetime',
        new lang_string('defaultactivetime', 'block_videochat'),
        new lang_string('defaultactivetimedesc', 'block_videochat'), 300, PARAM_INT));
}
