<?php

namespace Webkul\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use ACME\Mpesa\Models\Mpesa;
use App\Models\Availability;
use App\Models\RoomBooking;
use App\Http\Controllers\RoomBookingController;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Auth;

class MpesaController extends Controller
{
    public function __construct()
    {

        $this->ConsumerKey = env('ConsumerKey');
        $this->ConsumerSecret = env('ConsumerSecret');
        $this->Passkey = env('Passkey');
        $this->BusinessShortcode = env('BusinessShortcode');
        $this->Till = env('Till');
        $this->baseUri = 'https://api.safaricom.co.ke/';
        $this->lnmo_callback = env('mpesa_callbackurl');
    }


    private function submit_request($url, $data)
    {


        $credentials = base64_encode($this->ConsumerKey . ':' . $this->ConsumerSecret);
        $client = new Client();
        try {
            $request = $client->get(
                'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
                array(
                    'headers' => array('content-type' => 'application/json', 'Authorization' => 'Basic ' . $credentials)
                )
            );
            if ($body = $request->getBody()) {
                $response = $body->getContents();
            } else {
                return false;
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();

            } else {
                return false;
            }
        }

        $response = @json_decode($response);
        $access_token = @$response->access_token;


        if (!$access_token) {

            return false;
        }

        if ($access_token == '' || $access_token == FALSE) {
            $response = false;
        } else {
            $client = new Client();
            try {
                $request = $client->post(
                    $url,
                    array(
                        'headers' => array('content-type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
                        'body' => $data
                    )
                );
                if ($body = $request->getBody()) {
                    $response = $body->getContents();

                } else {
                    $response = false;
                }
            } catch (RequestException $e) {


                if ($e->hasResponse()) {
                    $response = $e->getResponse()->getBody()->getContents();

                } else {
                    $response = false;
                }
            }
        }


        return $response;
    }


    public function lnmo_request($amount, $phone, $cart_id)
    {

        // \Log::info("amount" . $amount);
        // \Log::info("phone" . $phone);
        // \Log::info("cart_id" . $cart_id);

        try {
            $phone = $this->sanitize_phone($phone);

            $timestamp = date('YmdHis');
            $passwd = base64_encode($this->BusinessShortcode . $this->Passkey . $timestamp);
            $data = array(
                'BusinessShortCode' => $this->BusinessShortcode,
                'Password' => $passwd,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $this->Till,
                'PartyB' => $this->Till,
                'PhoneNumber' => $phone,
                'CallBackURL' => $this->lnmo_callback,
                'AccountReference' => 'Any',
                'TransactionDesc' => 'testing too',
            );
            $data = json_encode($data);
            $url = $this->baseUri . 'mpesa/stkpush/v1/processrequest';
            $data = $response = $this->submit_request($url, $data);



            $response = json_decode($data);
            $MerchantRequestID = $response->MerchantRequestID;
            $CheckoutRequestID = $response->CheckoutRequestID;


            $savedata = Mpesa::create([
                'MerchantRequestID' => $response->MerchantRequestID,
                'CheckoutRequestID' => $response->CheckoutRequestID,
                'TransAmount' => $amount,
                'Phone' => $phone,
                'cart_id' => $cart_id,

            ]);

            //  $update_booking_status=RoomBooking::where('id',$room_booking_id)->update([
            //      'status'=>'pending'
            //  ]);
            \Log::info(response()->json($response));
            return $response;

        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json($th, 200);
        }




    }

    public function lnmo_callback()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $ResultCode = $data['Body']['stkCallback']['ResultCode'];
        $ResultDesc = $data['Body']['stkCallback']['ResultDesc'];
        $MerchantRequestID = $data['Body']['stkCallback']['MerchantRequestID'];

        $get_booking_id = Mpesa::where('MerchantRequestID', $MerchantRequestID)->first();
        $booking_id = $get_booking_id->booking_id;

        if ($ResultCode == 0) {
            $MpesaReceiptNumber = $data['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
            $phone = $data['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
            $amount = $data['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
            $ResultDesc = $data['Body']['stkCallback']['ResultDesc'];
            $Requestid = $data['Body']['stkCallback']['MerchantRequestID'];
            $date = $data['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];

            $savedata = Mpesa::where('MerchantRequestID', $MerchantRequestID)
                ->update([
                    'TransID' => $MpesaReceiptNumber,
                    'resultcode' => $ResultCode,
                    'ResultDesc' => $ResultDesc,

                ]);

            $update_booking_status = RoomBooking::where('id', $get_booking_id->booking_id)->update(
                [
                    'status' => 'paid'
                ]
            );



            $get_room_details = RoomBooking::where('id', $get_booking_id->booking_id)->first();

            $Propertyid = $get_room_details->PropertyId;
            $RoomId = $get_room_details->RoomId;
            $UserId = $get_room_details->UserId;
            $url = $get_room_details->url;


            $update_availability = Availability::updateOrCreate(
                ['RoomId' => $RoomId],
                ['available' => 'N'],
            );


            //send confirmation email
            $send_email = new RoomBookingController;
            $send_email = $send_email->sendBookingConfirmationMail($Propertyid, $RoomId, $UserId, $booking_id, $url);

        } else {
            $savedata = Mpesa::where('MerchantRequestID', $MerchantRequestID)
                ->update([
                    'resultcode' => $ResultCode,
                    'ResultDesc' => $ResultDesc
                ]);

            $update_booking_status = RoomBooking::where('id', $get_booking_id->booking_id)->update(
                [
                    'status' => 'unpaid'
                ]
            );

            $get_room_details = RoomBooking::where('id', $get_booking_id->booking_id)->first();

            $Propertyid = $get_room_details->PropertyId;
            $RoomId = $get_room_details->RoomId;
            $UserId = $get_room_details->UserId;
            $url = $get_room_details->url;





            //send incomplete transaction email


            $send_email = new RoomBookingController;
            $send_email = $send_email->sendUnsuccessfulBookingMail($Propertyid, $RoomId, $UserId, $booking_id, $url);


        }

    }
    //c2b
    public function confirm()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data);

        $trans_id = $data->TransID;
        $trans_amount = $data->TransAmount;
        $org_balance = $data->OrgAccountBalance;
        $thirdparty_id = $data->ThirdPartyTransID;
        $phone = $data->MSISDN;
        $fname = $data->FirstName;
        $mname = $data->MiddleName;
        $lname = $data->LastName;
        $trans_time = $data->TransTime;





    }

    public function validation()
    {
        $response = [
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ];
        return json_encode($response);
    }


    public function sanitize_phone($phone)
    {

        $length = strlen($phone);
        $new_phone = $phone;
        if ($length == 13) {
            $new_phone = '254' . substr($phone, 4);
        }
        if ($length == 12) {
            $new_phone = '254' . substr($phone, 3);
        }
        if ($length == 10) {
            $new_phone = '254' . substr($phone, 1);
        }

        return $new_phone;
    }

}