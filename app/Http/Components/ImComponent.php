<?php

namespace App\Http\Components;

use App\Http\Lib\ImSdk\TimRestApi;

class ImComponent
{
    private $sdkappid;
    // private $identifier;
    private $key_path;
    private $usersig;
    private $api;

    /**
     * 构造函数
     * @param $sessionKey string 用户在小程序登录后获取的会话密钥
     * @param $appid string 小程序的appid
     */
    public function __construct()
    {
        // 设置 REST API 调用基本参数
        $this->sdkappid = env('IM_ID');
        $this->identifier = env('IM_ADMIN');
        $this->key_path = dirname(__FILE__) . "/../Lib/ImSdk/key/";

    }

    public function createRestAPI()
    {
        $this->api = new TimRestApi();
        $this->api->init($this->sdkappid, $this->identifier, 24 * 3600);
        $this->api->generate_user_sig($this->identifier, $this->key_path);

        return $this->api;
    }

    public function generateUserSig($identifier)
    {
        return $this->api->generate_user_sig($identifier, $this->key_path);
    }
}
