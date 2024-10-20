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
 * payping enrolments plugin settings and presets.
 * @package    enrol_payping
 * @copyright  2024 payping<payping.ir>
 * @author     Mahdi Sarani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_payping_settings', '', get_string('pluginname_desc', 'enrol_payping')));

    $settings->add(new admin_setting_configtext('enrol_payping/token',
                   get_string('token', 'enrol_payping'),
                   'Copy payping token from token account & paste here', '', PARAM_RAW));;
    $settings->add(new admin_setting_configcheckbox('enrol_payping/checkproductionmode',
                   get_string('checkproductionmode', 'enrol_payping'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_payping/mailstudents', get_string('mailstudents', 'enrol_payping'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_payping/mailteachers', get_string('mailteachers', 'enrol_payping'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_payping/mailadmins', get_string('mailadmins', 'enrol_payping'), '', 0));

    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_payping/expiredaction', get_string('expiredaction', 'enrol_payping'), get_string('expiredaction_help', 'enrol_payping'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_payping_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_payping/status',
        get_string('status', 'enrol_payping'), get_string('status_desc', 'enrol_payping'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_payping/cost', get_string('cost', 'enrol_payping'), '', 0, PARAM_FLOAT, 4));

    $paypingcurrencies = enrol_get_plugin('payping')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_payping/currency', get_string('currency', 'enrol_payping'), '', 'USD', $paypingcurrencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_payping/roleid',
            get_string('defaultrole', 'enrol_payping'), get_string('defaultrole_desc', 'enrol_payping'), $student->id, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_payping/enrolperiod',
        get_string('enrolperiod', 'enrol_payping'), get_string('enrolperiod_desc', 'enrol_payping'), 0));
}