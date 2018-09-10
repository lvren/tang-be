<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use Cache;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function sendSmsCode(Request $request)
    {
        if (!$request->has('mobile')) {
            throw new Exception('没有指定手机号码');
        }
        $mobile = $request->input('mobile');
        $randomCode = random_int(1000, 9999);
        if (Cache::has($mobile)) {
            $oldCode = Cache::get($mobile);
            return ['status' => true, 'message' => $mobile . '已发送过验证码，1分钟内验证码有效，无需重复发送，验证码为：' . $oldCode];
        } else {
            // $ssender = new SmsSingleSender(env('SMS_ID'), env('SMS_KEY'));
            // $result = $ssender->send(0, "86", $phoneNumbers[0],
            //     "【腾讯云】您的验证码是: 5678", "", "");
            // $rsp = json_decode($result);
            Cache::put($mobile, $randomCode, 60);
        }
        return ['status' => true, 'message' => '验证码已发送，1分钟内验证码有效，请不要轻易告诉他人，验证码为：' . $randomCode];
    }

    public static function validateSmsCode(string $code, string $mobile)
    {
        if (Cache::has($mobile)) {
            $oldCode = Cache::get($mobile);
            if ($oldCode != $code) {
                throw new Exception('验证码填写错误');
            }
            return true;
        } else {
            throw new Exception('验证码已经过期，请重新获取验证码');
        }
    }
}
