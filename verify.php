<?php
/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Landing page of Organization Manager View (Approvels)
 *
 * @package    enrol
 * @subpackage payping
 * @copyright  2020 payping<payping.ir>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once("lib.php");
global $CFG, $_SESSION, $USER, $DB, $OUTPUT;
// require_once($CFG->libdir . '/eventslib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');
$systemcontext = context_system::instance();
$plugininstance = new enrol_payping_plugin();
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/enrol/moodle-gateway-payping/verify.php');
echo $OUTPUT->header();
$token = $plugininstance->get_config('token');
$testing = $plugininstance->get_config('checkproductionmode');
$Price = $_SESSION['totalcost'];
$NPPrice = $Price/10;
$NPPrice = (int) $NPPrice;
$trackId = $_GET['trans_id'];
$data = new stdClass();
$plugin = enrol_get_plugin('payping');
$today = date('Y-m-d');
if($_POST['Status'] == -1 ){
    echo '<h3 style="text-align:center; color: red;">عدم پرداخت توسط پرداخت کننده</h3>';
}else{
    if( isset( $_POST['PaymentRefId'] ) ){
        $PaymentRefid = $_POST['PaymentRefId'];
    }else{
        $PaymentRefid = 0;
    }

    $data_verify = array( 'Amount' => $NPPrice, 'PaymentRefId'  => $PaymentRefid );
    $curl = curl_init();
    curl_setopt_array( $curl, array(
        CURLOPT_URL => "https://api.payping.ir/v3/pay/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data_verify),
        CURLOPT_HTTPHEADER => array(
            "accept: application/json",
            "authorization: Bearer " . $token,
            "cache-control: no-cache",
            "content-type: application/json",
        ),
    ) );
    $response = curl_exec( $curl );
    $err = curl_error( $curl );
    $header = curl_getinfo( $curl );
    $response = json_decode( $response, true );
    
    curl_close( $curl );
    if($err){
        echo('خطا در ارتباط به پی‌پینگ :شرح خطا' . $err);
    }else{
        if($header['http_code'] == 200){
            $amount = $response['amount'];
            $cardNumber = $response['cardNumber'];
            $cardHashPan = $response['cardHashPan'];
            $clientRefId = $response['clientRefId'];
            $paymentRefId = $response['paymentRefId'];
            if( isset($PaymentRefid) && $PaymentRefid == $paymentRefId ){
                $coursename = $DB->get_field('course', 'fullname', ['id' => $_SESSION['courseid']]);
                $data->userid = $_SESSION['userid']; 
                $data->courseid = $_SESSION['courseid'];
                $data->instanceid = $_SESSION['instanceid'];
                $coursecost = $DB->get_record('enrol', ['enrol' => 'payping', 'courseid' => $data->courseid]);
                $time = strtotime($today);
                $paidprice = $coursecost->cost;
                $data->amount = $paidprice;
                $data->refnumber = $Refnumber;
                $data->orderid = $Resnumber;
                $data->payment_status = $Status;
                $data->timeupdated = time();
                $data->item_name = $coursename;
                $data->receiver_email = $USER->email;
                $data->receiver_id = $_SESSION['userid'];
                
                if (!$user = $DB->get_record("user", ["id" => $data->userid])) {
                    message_payping_error_to_admin("Not a valid user id", $data);
                    die;
                }
                if (!$course = $DB->get_record("course", ["id" => $data->courseid])) {
                    message_payping_error_to_admin("Not a valid course id", $data);
                    die;
                }
                if (!$context = context_course::instance($course->id, IGNORE_MISSING)) {
                    message_payping_error_to_admin("Not a valid context id", $data);
                    die;
                }
                if (!$plugin_instance = $DB->get_record("enrol", ["id" => $data->instanceid, "status" => 0])) {
                    message_payping_error_to_admin("Not a valid instance id", $data);
                    die;
                }

                $coursecontext = context_course::instance($course->id, IGNORE_MISSING);

                if ( (float) $plugin_instance->cost <= 0 ) {
                    $cost = (float) $plugin->get_config('cost');
                } else {
                    $cost = (float) $plugin_instance->cost;
                }

                $cost = format_float($cost, 2, false);

                $data->item_name = $course->fullname;

                //$DB->insert_record("enrol_payping", $data); 

                if ($plugin_instance->enrolperiod) {
                    $timestart = time();
                    $timeend   = $timestart + $plugin_instance->enrolperiod;
                } else {
                    $timestart = 0;
                    $timeend   = 0;
                }

                $plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);

                if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                    '', '', '', '', false, true)) {
                    $users = sort_by_roleassignment_authority($users, $context);
                    $teacher = array_shift($users);
                } else {
                    $teacher = false;
                }

                $mailstudents = $plugin->get_config('mailstudents');
                $mailteachers = $plugin->get_config('mailteachers');
                $mailadmins   = $plugin->get_config('mailadmins');
                $shortname = format_string($course->shortname, true, array('context' => $context));


                if (!empty($mailstudents)) {
                    $a = new stdClass();
                    $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
                    $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

                    $eventdata = new \core\message\message();
                    $eventdata->courseid          = $course->id;
                    $eventdata->modulename        = 'moodle';
                    $eventdata->component         = 'enrol_payping';
                    $eventdata->name              = 'payping_enrolment';
                    $eventdata->userfrom          = empty($teacher) ? core_user::get_noreply_user() : $teacher;
                    $eventdata->userto            = $user;
                    $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                    $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
                    $eventdata->fullmessageformat = FORMAT_PLAIN;
                    $eventdata->fullmessagehtml   = '';
                    $eventdata->smallmessage      = '';
                    message_send($eventdata);

                }

                if (!empty($mailteachers) && !empty($teacher)) {
                    $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
                    $a->user = fullname($user);

                    $eventdata = new \core\message\message();
                    $eventdata->courseid          = $course->id;
                    $eventdata->modulename        = 'moodle';
                    $eventdata->component         = 'enrol_payping';
                    $eventdata->name              = 'payping_enrolment';
                    $eventdata->userfrom          = $user;
                    $eventdata->userto            = $teacher;
                    $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                    $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
                    $eventdata->fullmessageformat = FORMAT_PLAIN;
                    $eventdata->fullmessagehtml   = '';
                    $eventdata->smallmessage      = '';
                    message_send($eventdata);
                }

                if (!empty($mailadmins)) {
                    $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
                    $a->user = fullname($user);
                    $admins = get_admins();
                    foreach ($admins as $admin) {
                        $eventdata = new \core\message\message();
                        $eventdata->courseid          = $course->id;
                        $eventdata->modulename        = 'moodle';
                        $eventdata->component         = 'enrol_payping';
                        $eventdata->name              = 'payping_enrolment';
                        $eventdata->userfrom          = $user;
                        $eventdata->userto            = $admin;
                        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
                        $eventdata->fullmessageformat = FORMAT_PLAIN;
                        $eventdata->fullmessagehtml   = '';
                        $eventdata->smallmessage      = '';
                        message_send($eventdata);
                    }
                }
                echo '<h3 style="text-align:center; color: green;">با تشکر از شما، پرداخت شما با موفقیت انجام شد و به  درس انتخاب شده افزوده شدید.</h3>';
                echo '<div class="single_button" style="text-align:center;"><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '"><button>ورود به درس خریداری شده</button></a></div>';
            }else{
                echo('متاسانه سامانه قادر به دریافت کد پیگیری نمی‌باشد! نتیجه درخواست: ' . $header['http_code']);
            }
        }elseif($header['http_code'] == 400){
            echo(' تراکنش ناموفق بود، شرح خطا: ' . $response);
        }else{
            echo(' تراکنش ناموفق بود، شرح خطا: ' . $header['http_code']);
        }
    }
}

//----------------------------------------------------- HELPER FUNCTIONS --------------------------------------------------------------------------

function message_payping_error_to_admin($subject, $data)
{
    echo $subject;
    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    foreach ($data as $key => $value) {
        $message .= "$key => $value\n";
    }

    $eventdata = new \core\message\message();
    $eventdata->modulename = 'moodle';
    $eventdata->component = 'enrol_payping';
    $eventdata->name = 'payping_enrolment';
    $eventdata->userfrom = $admin;
    $eventdata->userto = $admin;
    $eventdata->subject = "payping ERROR: " . $subject;
    $eventdata->fullmessage = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = '';
    $eventdata->smallmessage = '';
    message_send($eventdata);
}

echo $OUTPUT->footer();
