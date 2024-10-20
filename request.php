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
 * @package    enrol_payping
 * @copyright  2024 payping<payping.ir>
 * @author     Mahdi Sarani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once("lib.php");
global $CFG, $_SESSION, $USER, $DB;

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$plugininstance = new enrol_payping_plugin();
if (!empty($_POST['multi'])) {
    $instance_array = unserialize($_POST['instances']);
    $ids_array = unserialize($_POST['ids']);
    $_SESSION['idlist']  =implode(',', $ids_array);
    $_SESSION['inslist']  =implode(',', $instance_array);
    $_SESSION['multi'] = $_POST['multi'];
 } else {
    $_SESSION['courseid'] = $_POST['course_id'];
    $_SESSION['instanceid'] = $_POST['instance_id'];
}

$_SESSION['totalcost']= $_POST['amount'];
$_SESSION['userid'] = $USER->id;
$Price = $_POST['amount'];

$token = $plugininstance->get_config('token');
$testing = $plugininstance->get_config('checkproductionmode');
$use_payping = $plugininstance->get_config('usepayping');
$ReturnPath = $CFG->wwwroot.'/enrol/moodle-gateway-payping/verify.php';
$ResNumber = date('YmdHis');// Order Id In Your System
$Description = 'پرداخت شهریه ' . $_POST['item_name'];
$Paymenter = $USER->firstname. ' ' .$USER->lastname;
$Email = $USER->email;
$Mobile = $USER->phone1;

if (isset($Mobile) && !empty($Mobile)) {
    $payerIdentity = $Mobile;
} else {
    $payerIdentity = $Email;
}

if ($testing == 0) {
    $token = "payping";
}

$intPrice = (int) $Price;
$NPrice = $intPrice/10;
$NPrice = (int)$NPrice;

$data_array = array (
    "Amount" => $NPrice,
    "ReturnUrl" => $ReturnPath,
    'payerIdentity' => $payerIdentity,
    "PayerName" => $Paymenter,
    'Description'   => $Description,
    'clientRefId'   => $ResNumber,
    //'NationalCode'  => $nationalCode
);

try{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.payping.ir/v3/pay",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data_array),
        CURLOPT_HTTPHEADER => array(
            "accept: application/json",
            "authorization: Bearer " . $token,
            "X-Platform': moodle",
            "X-Platform-Version: 1.0.0",
            "cache-control: no-cache",
            "content-type: application/json"
        ),
            )
    );
    $response = curl_exec( $curl );
    
    $header = curl_getinfo( $curl );
    $err = curl_error( $curl );
    curl_close( $curl );
    
    if( $err ){
        $msg = 'کد خطا: CURL#' . $er;
        $erro = 'در اتصال به درگاه مشکلی پیش آمد.';
        return false;
    }else{
        $response = json_decode( $response, true );
        if( $header['http_code'] == 200 ){
            if( isset( $response ) and $response != '' ){
                /* ارسال به درگاه پرداخت با استفاده از کد ارجاع */
                header( 'Location: ' . $response['url'] );
            }else{
                $msg = 'تراکنش ناموفق بود - شرح خطا: عدم وجود کد ارجاع';
            }
        }elseif($header['http_code'] == 400){
            $msg = 'تراکنش ناموفق بود، شرح خطا: ' . $response['message'];
        }else{
            $msg = 'تراکنش ناموفق بود، شرح خطا: ' . $header['http_code'];
        }
    }
}catch(Exception $e){
    $msg = 'تراکنش ناموفق بود، شرح خطا سمت برنامه شما: ' . $e->getMessage();
}

echo $msg;