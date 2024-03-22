<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiHomeController extends Controller
{
    public function HomeScreen(Request $request)
    {
        $action = $request->input('action');
        $output = array();
        switch ($action) {
            case 'country_code':
                $userData = DB::table('country_code')->where('is_delete', '=', 0)->select('country', 'code')->get();
                if ($userData->isNotEmpty()) {
                    $MsgArray = $userData;
                }
                $output = $MsgArray;
                return $output;
                break;
            case 'get_otp':
                $mobile = $request->input('mobile');
                $table_data = $request->type == 1 ? 'seller_table' : 'buyer_table';
                if ($mobile == '9987654321') {
                    $otp = '6543';
                } else {
                    $otp = mt_rand(1111, 9999);
                    $msg = $otp . " is your OTP to verify your mobile number on Nithra app/website.";
                }
                if ($mobile) {
                    if (DB::table($table_data)->where('mobile', $mobile)->exists()) {

                        DB::table($table_data)->where('mobile', $mobile)->update(['otp' => $otp,'country' => $request->country,
                            'country_code' => $request->code]);
                    } else {
                        DB::table($table_data)->insertOrIgnore(['mobile' => $mobile, 'otp' => $otp,'country' => $request->country,
                            'country_code' => $request->code, 'otptime' => now()]);
                    }
                    if ($mobile != '9987654321') {
//                        send_message($mobile, $msg, "1307160853199181365");
                    }

                    $output = ['status' => 'success', 'otp' => $otp];
                } else {
                    $output = ['status' => 'failure'];
                }
                return $output;
                break;
            case 'check_otp':
//                return '[{"key":"action","value":"check_otp","type":"text","enabled":true}]';
                $table_data = $request->type == 1 ? 'seller_table' : 'buyer_table';
                $mobile = $request->input('mobile');
                $fetch_data = DB::table($table_data)
                    ->select('id')
                    ->where('mobile', $mobile)
                    ->where('otp', $request->otp)
                    ->first();
                if ($fetch_data) {
                    $output[] = ['status' => 'success', 'user_id' => $fetch_data->id];
                } else {
                    $output[] = ['status' => 'failure'];
                }
                return $output;
                break;
            case 'state_spinner':
                $output = [
                    'state' => DB::table('state')->select('id', 'english as state_name')->get(),
                    'district' => DB::table('district')->select('id', 'state as state_id', 'english as district_name')->get(),
                    'city' => DB::table('city')->select('id', 'state as state_id', 'district', 'english as city_name')->get(),
                ];
                return $output;
                break;
            case 'save_form':
                $type = $request->type;
                return $type;
               $update_array = ['is_allocated' => 1, 'allocated_id' => $nuser_id,];
                $output = [
                    'state' => DB::table('state')->select('id', 'english as state_name')->get(),
                    'district' => DB::table('district')->select('id', 'state as state_id', 'english as district_name')->get(),
                    'city' => DB::table('city')->select('id', 'state as state_id', 'district', 'english as city_name')->get(),
                ];
                return $output;
                break;
            default:
                // Handle the default case if necessary
                break;
        }
    }

    // Define your OTP generation method here
    // Assuming this method sends an OTP to a given mobile number
    private function otp($mobile, $otp)
    {
        // Your OTP generation logic here
        // This method might send an OTP to the provided mobile number
    }
}