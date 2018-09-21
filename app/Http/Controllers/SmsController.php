<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use Cache;
use Illuminate\Http\Request;
use Qcloud\Sms\SmsSingleSender;

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
            return ['status' => true, 'message' => $mobile . '已发送过验证码，1分钟内验证码有效，无需重复发送'];
        } else {
            $ssender = new SmsSingleSender(env('SMS_ID'), env('SMS_KEY'));
            $params = [$randomCode, 1];
            $template = 190635;
            $result = $ssender->sendWithParam("86", $mobile, $template, $params);
            $rsp = json_decode($result);
            if (!$rsp || $rsp->result !== 0) {
                throw new Exception($rsp && $rsp->errmsg ? $rsp->errmsg : '短信发送失败');
            }
            Cache::put($mobile, $randomCode, 1);
        }
        return ['status' => true, 'message' => '验证码已发送，1分钟内验证码有效，请不要轻易告诉他人'];
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
