<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Model\Country;
use App\Model\Order;
use App\Model\Product;
use App\Model\Sharer;
use App\Model\User;
use Illuminate\Http\Request;

class MppBaseInfoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // 获取所有国家列表
    public function getCountryList(Request $request)
    {
        $country = Country::all();
        return $this->successResponse($country);
    }
    // 获取所有校友列表
    public function getSharerList(Request $request)
    {
        $sharer = Sharer::with('school')->get();
        return $this->successResponse($sharer);
    }
    // 获取校友的详细信息
    public function getSharerInfo(Request $request)
    {
        $sharerId = $request->input('sharerId');
        if (!$sharerId) {
            throw new Exception('没有接受到校友信息参数ID');
        }
        $sharer = Sharer::with(['school', 'product'])->where('id', $sharerId)->first();
        if (!$sharer) {
            throw new Exception('没有接受到校友' . $sharerId . '的信息');
        }
        $sharer->has_refer = false;
        if ($sharer->product) {
            $products = $sharer->product;
            foreach ($products as $product) {
                if ($product->type === 'refer') {
                    $sharer->has_refer = true;
                }
            }
        }
        return $this->successResponse($sharer);
    }
    // 获取用户的所有订单
    public function getUserProductList(Request $request)
    {
        $user = $request->user();
        $orderList = Order::with(['product'])
            ->where('user_id', $user->id)
            ->get();

        $orderInfo = [];
        foreach ($orderList as $key => $value) {
            $info = [
                'id' => $value->id,
                'orderId' => $value->order_id,
                'isPay' => $value->is_pay,
                'price' => $value->price,
                'productTitle' => $value->product->title,
                'productDesc' => $value->product->desc,
                'productName' => $value->product->name,
                'sharer' => $value->product->sharer->name,
                'sharerId' => $value->product->sharer->id,
                'createdAt' => $value->created_at->format('Y-m-d H:i'),
            ];
            array_push($orderInfo, $info);
        }
        return $this->successResponse($orderInfo);
    }
    // 根据ID过去订单信息
    public function getOrderInfo(Request $request)
    {
        $orderId = $request->input('orderId');

        $order = Order::with(['user', 'product'])->where('id', $orderId)->first();
        if ($order) {
            $order->sharer = $order->product->sharer;
        }
        return $this->successResponse($order);
    }
    // 获取用户信息
    public function getUserInfo(Request $request)
    {
        $user = $request->user();
        return ['status' => true, 'data' => $user];
    }
    // 绑定手机
    public function saveUserMobile(Request $request)
    {
        $user = $request->user();
        $mobile = $request->input('mobile');
        $code = $request->input('code');

        if (!$mobile) {
            throw new Exception('没有指定手机号码');
        }
        if (!$code) {
            throw new Exception('没有填写验证码');
        }

        SmsController::validateSmsCode($code, $mobile);
        $user->mobile = $mobile;
        $status = $user->save();
        return ['status' => true, 'message' => '绑定手机成功'];
    }
    // 绑定微信号
    public function saveUserWeixin(Request $request)
    {
        $user = $request->user();
        $weixin = $request->input('weixin');

        if (!$weixin) {
            throw new Exception('没有指定微信号');
        }

        $user->weixin = $weixin;
        $user->save();
        return ['status' => true, 'message' => '绑定用户微信成功'];
    }
    // 修改用户名
    public function saveUserNickname(Request $request)
    {
        $user = $request->user();
        $nickname = $request->input('nickname');

        if (!$nickname) {
            throw new Exception('没有输入用户名');
        }

        $user->nickname = $nickname;
        $user->save();
        return ['status' => true, 'message' => '修改用户名成功'];
    }

    public function getReferSharer(Request $request)
    {
        $sharers = Sharer::with(['school', 'product'])->get();
        $filterSharers = [];
        foreach ($sharers as $sharer) {
            if ($sharer->product) {
                $products = $sharer->product;
                foreach ($products as $product) {
                    if ($product->type === 'refer') {
                        array_push($filterSharers, $sharer);
                    }
                }
            }
        }
        return $this->successResponse($filterSharers);
    }

    public function getReferBySharer(Request $request)
    {
        $sharerId = $request->input('sharerId');
        $product = Product::where('type', 'refer')->where('sharer_id', $sharerId)->first();

        return $this->successResponse($product);
    }
}
