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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

/**
 * Unit tests for mod/facetoface
 */
class facetoface_tests extends advanced_testcase
{
    public function test_facetoface_get_status() {
        $this->assertEquals(facetoface_get_status(10), 'user_cancelled');
        $this->assertEquals(facetoface_get_status(30), 'declined');
        $this->assertEquals(facetoface_get_status(40), 'requested');
        $this->assertEquals(facetoface_get_status(50), 'approved');
        $this->assertEquals(facetoface_get_status(60), 'waitlisted');
        $this->assertEquals(facetoface_get_status(70), 'booked');
        $this->assertEquals(facetoface_get_status(80), 'no_show');
        $this->assertEquals(facetoface_get_status(90), 'partially_attended');
        $this->assertEquals(facetoface_get_status(100), 'fully_attended');
    }


    public function test_format_cost() {
        // Test for a valid value.
        $this->assertEquals(format_cost(1000, true), '$1000');
        $this->assertEquals(format_cost(1000, false), '$1000');
        $this->assertEquals(format_cost(1000), '$1000');

        // Test for a large negative value, html true/ false/ null.
        $this->assertEquals(format_cost(-34000, true), '$-34000');
        $this->assertEquals(format_cost(-34000, false), '$-34000');
        $this->assertEquals(format_cost(-34000), '$-34000');

        // Test for a large positive value.
        $this->assertEquals(format_cost(100000000000, true), '$100000000000');
        $this->assertEquals(format_cost(100000000000, false), '$100000000000');
        $this->assertEquals(format_cost(100000000000), '$100000000000');

        // Test for a decimal value.
        $this->assertEquals(format_cost(32768.9045, true), '$32768.9045');
        $this->assertEquals(format_cost(32768.9045, false), '$32768.9045');
        $this->assertEquals(format_cost(32768.9045), '$32768.9045');

        // Test for a null value.
        $this->assertEquals(format_cost(null, true), '$');
        $this->assertEquals(format_cost(null, false), '$');
        $this->assertEquals(format_cost(null), '$');

        // Test for a text string value.
        $this->assertEquals(format_cost('string', true), '$string');
        $this->assertEquals(format_cost('string', false), '$string');
        $this->assertEquals(format_cost('string'), '$string');
    }

    public function test_format_duration() {
        // Test for positive single hour value.
        $this->assertEquals(format_duration('1:00'), '1 hour ');
        $this->assertEquals(format_duration('1.00'), '1 hour ');

        // Test for positive multiple hours value.
        $this->assertEquals(format_duration('3:00'), '3 hours ');
        $this->assertEquals(format_duration('3.00'), '3 hours ');

        // Test for positive single minute value.
        $this->assertEquals(format_duration('0:01'), '1 minute');
        $this->assertEquals(format_duration('0.1'), '6 minutes');

        // Test for positive minutes value.
        $this->assertEquals(format_duration('0:30'), '30 minutes');
        $this->assertEquals(format_duration('0.50'), '30 minutes');

        // Test for out of range minutes value.
        $this->assertEquals(format_duration('9:70'), '');

        // Test for zero value.
        $this->assertEquals(format_duration('0:00'), '');
        $this->assertEquals(format_duration('0.00'), '');

        // Test for negative hour value.
        $this->assertEquals(format_duration('-1:00'), '');
        $this->assertEquals(format_duration('-1.00'), '');

        // Test for negative multiple hours value.
        $this->assertEquals(format_duration('-7:00'), '');
        $this->assertEquals(format_duration('-7.00'), '');

        // Test for negative single minute value.
        $this->assertEquals(format_duration('-0:01'), '');
        $this->assertEquals(format_duration('-0.01'), '');

        // Test for negative multiple minutes value.
        $this->assertEquals(format_duration('-0:33'), '');
        $this->assertEquals(format_duration('-0.33'), '');

        // Test for negative hours & minutes value.
        $this->assertEquals(format_duration('-5:42'), '');
        $this->assertEquals(format_duration('-5.42'), '');

        // Test for invalid characters value.
        $this->assertEquals(format_duration('invalid_string'), '');
    }


    public function test_facetoface_minutes_to_hours() {
        // Test method - returns a string.

        // Test for positive minutes value.
        $this->assertEquals(facetoface_minutes_to_hours('11'), '0:11');

        // Test for positive hours & minutes value.
        $this->assertEquals(facetoface_minutes_to_hours('67'), '1:7');

        // Test for negative minutes value.
        $this->assertEquals(facetoface_minutes_to_hours('-42'), '-42');

        // Test for negative hours and minutes value.
        $this->assertEquals(facetoface_minutes_to_hours('-7:19'), '-7:19');

        // Test for invalid characters value.
        $this->assertEquals(facetoface_minutes_to_hours('invalid_string'), '0');
    }

    public function test_facetoface_hours_to_minutes() {
        // Test method - returns a float.
        // Should negative values return 0 or a negative value?

        // Test for positive hours value.
        $this->assertEquals(facetoface_hours_to_minutes('10'), '600');

        // Test for positive minutes and hours value.
        $this->assertEquals(facetoface_hours_to_minutes('11:17'), '677');

        // Test for negative hours value.
        $this->assertEquals(facetoface_hours_to_minutes('-3'), '-180');

        // Test for negative hours & minutes value.
        $this->assertEquals(facetoface_hours_to_minutes('-2:1'), '-119');

        // Test for invalid characters value.
        $this->assertEquals(facetoface_hours_to_minutes('invalid_string'), '0.0');
    }
}