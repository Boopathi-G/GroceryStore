<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Imports\UsersImport;
use Illuminate\Support\Facades\Redis;

class ApiHomeController extends Controller
{
    public function send_message($mob, $otp)
    {
        $otpmsg = $otp . ' is your OTP to verify your mobile number on Nithra app/website.';
        ob_start();
        $ch = curl_init();
        $msg = urlencode($otpmsg);
        $url = "http://api.msg91.com/api/sendhttp.php?sender=NITHRA&route=4&mobiles=" . $mob . "&authkey=221068AW6ROwfK5b2782c0&country=91&campaign=pooja_store&message=" . $msg . "&DLT_TE_ID=1307160853199181365";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        ob_end_clean();
    }

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

                        DB::table($table_data)->where('mobile', $mobile)->update(['otp' => $otp, 'country' => $request->country,
                            'country_code' => $request->code]);
                    } else {
                        DB::table($table_data)->insertOrIgnore(['mobile' => $mobile, 'otp' => $otp, 'country' => $request->country,
                            'country_code' => $request->code, 'otptime' => now()]);
                    }
                    if ($mobile != '9987654321') {
//                       send_message($mobile, $msg, "1307160853199181365");
                        $otpmsg = $otp . ' is your OTP to verify your mobile number on Nithra app/website.';
                        ob_start();
                        $ch = curl_init();
                        $msg = urlencode($otpmsg);
                        $url = "http://api.msg91.com/api/sendhttp.php?sender=NITHRA&route=4&mobiles=" . $mobile . "&authkey=221068AW6ROwfK5b2782c0&country=91&campaign=pooja_store&message=" . $msg . "&DLT_TE_ID=1307160853199181365";
//                        return $url;
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_exec($ch);
                        curl_close($ch);
                        ob_end_clean();
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
            case 'code_generate':
                $mobile = $request->input('mobile');
//                return $mobile;
                // Attempt to generate a code up to 20 times to avoid infinite loops
                for ($attempt = 0; $attempt < 20; $attempt++) {
                    $code = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)); // Generate a random
                    if (!DB::table('seller_table')->where('private_code', $code)->exists()) {
                        DB::table('seller_table')->where('mobile', $request->mobile)->update(['private_code' => $code]);
                        $output = ['status' => 'success', 'code' => $code];
                        return response()->json($output);
                    }
                }
                $output = ['success' => false, 'message' => 'Failed to generate a unique code.'];
                return response()->json($output);
                break;
            case 'save_form':
                $mobile = $request->input('mobile');
                $update_array = [
                    'name' => $request->name,
                    'state' => $request->state,
                    'district' => $request->district,
                    'city' => $request->city,
                    'pincode' => $request->pincode,
                    'place' => $request->place,
                    'house_no' => $request->house_no,
                    'street' => $request->street,
                    'cdate' => now(),
                ];
                if ($request->type == 1) {
                    unset($update_array['name']); // Unset the 'name' key
                    $shop_image = '';
                    $image_final = '';
                    $cloudFrontUrl = "https://d314xssrurywid.cloudfront.net";
                    if ($request->file('shop_image')) {
                        $file = $request->file('shop_image');
                        $shop_image = $request->file('shop_image')->store(
                            '',
                            's3'
                        );
                        $image_final = $cloudFrontUrl . '/' . $shop_image;
                    }
//                    return $image_final;
                    $update_array['private_code'] = $request->unicode;
                    $update_array['shop_name'] = $request->shop_name;
                    $update_array['owner_name'] = $request->owner_name;
                    $update_array['img1'] = $image_final;
                    $update_array['shop_mobile'] = $request->shop_mobile;
                }
                $table_data = $request->type == 1 ? 'seller_table' : 'buyer_table';
                $rowsAffected = DB::table($table_data)
                    ->where('mobile', $mobile)
                    ->update($update_array);
                $response = ($rowsAffected > 0) ? ['status' => 'success'] : ['status' => 'failure'];
                return $response;
                break;
            case 'home_screen':
                $table_data = $request->type == 1 ? 'seller_table' : 'buyer_table';
                $search_text = $request->search_text;
                if ($request->type == 2){
                    $req_id = DB::table('buyer_request')->where('bid', $request->bid)->pluck('sid')->implode(',');
                $sellers = DB::table('seller_table')
                    ->when($search_text, function ($query) use ($search_text) {
                        return $query->where('private_code', $search_text);
                    })
                    ->when(!$search_text, function ($query) use ($req_id) {
                        return $query->whereIn('id', explode(',', $req_id));
                    })
                    ->get();

                 }else{
//                    echo 'asdf';exit;
                    $sellers = DB::table($table_data)->select('id', 'mobile','private_code','shop_name','owner_name','shop_mobile','house_no','street')->where('id', $request->sid)->get();
                }
                $output = $sellers->isEmpty() ? [['status' => 'failure']] : [['status' => 'success', 'seller_data' => $sellers]];
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
