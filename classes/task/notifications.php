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

namespace mod_facetoface\task;

global $CFG;
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

/**
 * Facetoface notifications task.
 */
class notifications extends \core\task\scheduled_task
{
    public function get_name() {
        return "Facetoface Notifications";
    }

    /**
     * Do housekeeping which only runs once per day.
     * Finds all facetoface notifications that have yet to be mailed out, and mails them.
     */
    public function execute() {
        global $CFG, $DB, $USER;

        $signupsdata = facetoface_get_unmailed_reminders();
        if (!$signupsdata) {
            echo "\n" . get_string('noremindersneedtobesent', 'facetoface') . "\n";
            return true;
        }

        $timenow = time();
        foreach ($signupsdata as $signupdata) {
            if (facetoface_has_session_started($signupdata, $timenow)) {
                // Too late, the session already started.
                // Mark the reminder as being sent already.
                $newsubmission = new \stdClass();
                $newsubmission->id = $signupdata->id;
                $newsubmission->mailedreminder = 1; // Magic number to show that it was not actually sent.
                if (!$DB->update_record('facetoface_signups', $newsubmission)) {
                    echo "ERROR: could not update mailedreminder for submission ID $signupdata->id";
                }
                continue;
            }

            $earlieststarttime = $signupdata->sessiondates[0]->timestart;
            foreach ($signupdata->sessiondates as $date) {
                if ($date->timestart < $earlieststarttime) {
                    $earlieststarttime = $date->timestart;
                }
            }

            $reminderperiod = $signupdata->reminderperiod;

            // Convert the period from business days (no weekends) to calendar days.
            for ($reminderday = 0; $reminderday < $reminderperiod + 1; $reminderday++) {
                $reminderdaytime = $earlieststarttime - ($reminderday * 24 * 3600);
                // Use %w instead of %u for Windows compatability.
                $reminderdaycheck = userdate($reminderdaytime, '%w');
                // Note w runs from Sun=0 to Sat=6.
                if ($reminderdaycheck == 0 || $reminderdaycheck == 6) {
                    // Saturdays and Sundays are not included in the
                    // reminder period as entered by the user, extend
                    // that period by 1.
                    $reminderperiod++;
                }
            }

            $remindertime = $earlieststarttime - ($reminderperiod * 24 * 3600);
            if ($timenow < $remindertime) {
                // Too early to send reminder.
                continue;
            }

            if (!$user = $DB->get_record('user', array('id' => $signupdata->userid))) {
                continue;
            }

            // Hack to make sure that the language is set properly in emails
            // (i.e. it uses the language of the recipient of the email).
            $USER->lang = $user->lang;

            if (!$course = $DB->get_record('course', array('id' => $signupdata->course))) {
                continue;
            }
            if (!$facetoface = $DB->get_record('facetoface', array('id' => $signupdata->facetofaceid))) {
                continue;
            }

            $postsubject = '';
            $posttext = '';
            $posttextmgrheading = '';

            if (empty($signupdata->mailedreminder)) {
                $postsubject = $facetoface->remindersubject;
                $posttext = $facetoface->remindermessage;
                $posttextmgrheading = $facetoface->reminderinstrmngr;
            }

            if (empty($posttext)) {
                // The reminder message is not set, don't send anything.
                continue;
            }

            $postsubject = facetoface_email_substitutions($postsubject, $signupdata->facetofacename, $signupdata->reminderperiod,
                                                          $user, $signupdata, $signupdata->sessionid);
            $posttext = facetoface_email_substitutions($posttext, $signupdata->facetofacename, $signupdata->reminderperiod,
                                                       $user, $signupdata, $signupdata->sessionid);

            $posttextmgrheading = facetoface_email_substitutions(
                $posttextmgrheading,
                $signupdata->facetofacename,
                $signupdata->reminderperiod,
                $user,
                $signupdata,
                $signupdata->sessionid
            );

            $posthtml = '';
            if ($fromaddress = get_config(null, 'facetoface_fromaddress')) {
                $from = new \stdClass();
                $from->maildisplay = true;
                $from->email = $fromaddress;
            } else {
                $from = null;
            }

            if (email_to_user($user, $from, $postsubject, $posttext, $posthtml)) {
                echo "\n".get_string('sentreminderuser', 'facetoface').": $user->firstname $user->lastname $user->email";

                $newsubmission = new \stdClass();
                $newsubmission->id = $signupdata->id;
                $newsubmission->mailedreminder = $timenow;
                if (!$DB->update_record('facetoface_signups', $newsubmission)) {
                    echo "ERROR: could not update mailedreminder for submission ID $signupdata->id";
                }

                if (empty($posttextmgrheading)) {
                    continue; // No manager message set.
                }

                $managertext = $posttextmgrheading.$posttext;
                $manager = $user;
                $manager->email = facetoface_get_manageremail($user->id);

                if (empty($manager->email)) {
                    continue; // Don't know who the manager is.
                }

                // Send email to manager.
                if (email_to_user($manager, $from, $postsubject, $managertext, $posthtml)) {
                    echo "\n".get_string('sentremindermanager', 'facetoface').": $user->firstname $user->lastname $manager->email";
                } else {
                    $errormsg = array();
                    $errormsg['submissionid'] = $signupdata->id;
                    $errormsg['userid'] = $user->id;
                    $errormsg['manageremail'] = $manager->email;
                    echo get_string('error:cronprefix', 'facetoface') . ' ';
                    echo get_string('error:cannotemailmanager', 'facetoface', $errormsg) . "\n";
                }
            } else {
                $errormsg = array();
                $errormsg['submissionid'] = $signupdata->id;
                $errormsg['userid'] = $user->id;
                $errormsg['useremail'] = $user->email;
                echo get_string('error:cronprefix', 'facetoface') . ' ';
                echo get_string('error:cannotemailuser', 'facetoface', $errormsg) . "\n";
            }
        }
    }
}