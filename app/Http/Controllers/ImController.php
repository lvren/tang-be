<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

require_once dirname(__FILE__) . "/../Lib/ImSdk/TimRestApi.php";

/**
 * ImController IM相关处理类
 */
class ImController extends Controller
{
    public function sendSmsCode(Request $request)
    {
        // 设置 REST API 调用基本参数
        $sdkappid = 1400000478;
        $identifier = "admin";
        $key_path = dirname(__FILE__) . "/../Lib/ImSdk/key/";

        // 初始化API
        $api = createRestAPI();
        $api->init($sdkappid, $identifier);

        // 生成签名，有效期一天
        // 对于FastCGI，可以一直复用同一个签名，但是必须在签名过期之前重新生成签名
        $ret = $api->generate_user_sig($identifier, $key_path, 86400);
        if ($ret == null) {
            // 签名生成失败
            return -10;
        }
    }

    public function validateSmsCode(string $code, string $mobile)
    {

    }
}
