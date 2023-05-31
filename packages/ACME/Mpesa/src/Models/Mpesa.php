<?php

namespace ACME\Mpesa\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mpesa extends Model
{
    use HasFactory;
    protected $table = "mpesa";
    protected $fillable =[
        'MerchantRequestID',
         'CheckoutRequestID',
         'TransID',
         'TransAmount',
         'BillRefNumber',
        ' OrgAccountBalance',
         'ThirdPartyTransId',
         'Phone',
         'FName',
         'mname',
         'lname',
         'trans_time',
         'cart_id',
         'resultcode',
         'ResultDesc',
         'balance',
            ];
}
