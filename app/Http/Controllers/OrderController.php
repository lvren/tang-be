<?php

namespace App\Http\Controllers;

use App\Model\Order;
use Illuminate\Http\Request;
use Log;
use SoapBox\Formatter\Formatter;

class OrderController extends Controller
{
    public function orderNotify(Request $request)
    {
        $responseCode = 'SUCCESS';
        $xmlData = file_get_contents('php://input');
        Log::info('支付回调信息:');
        Log::info($xmlData);
        if (!$xmlData) {
            Log::error('支付回调失败：没有接收到返回信息');
            $responseCode === 'FAIL';
        }
        $formatter = Formatter::make($xmlData, Formatter::XML);
        $arrayData = $formatter->toArray();
        Log::info('支付回调信息：');
        Log::info($arrayData);
        if ($arrayData['return_code'] === 'FAIL') {
            $returnMsg = $arrayData['return_msg'];
            Log::error('支付回调失败：' . $returnMsg);
            $responseCode === 'FAIL';
        }

        $resultCode = $arrayData['result_code'];
        $orderId = $arrayData['out_trade_no'];
        $order = Order::where('order_id', $orderId)->first();
        if (!$order) {
            Log::error('支付回调失败：回调的订单' . $orderId . '不存在');
        } else {
            if ($resultCode === 'SUCCESS') {
                $order->isPay = true;
                $order->transaction_id = $arrayData['transaction_id'];
                $order->save();
            } else {
                $order->error_msg = $arrayData['err_code'] . ':' . $arrayData['err_code_des'];
                $order->delete();
                Log::error('支付回调失败：回调的订单' . $orderId . '支付失败');
                Log::error('失败原因' . $arrayData['err_code'] . ':' . $arrayData['err_code_des']);
            }
        }

        echo "<xml>
              <return_code><![CDATA[{$responseCode}]]></return_code>
              <return_msg><![CDATA[OK]]></return_msg>
          </xml>";
        exit();
    }

    public function checkOrder(Request $request)
    {
        $orderId = $request->input('order');
        $order = Order::where('order_id', $orderId)->first();
        if ($order) {
            return [
                'status' => $order->isPay === 1 ? true : false,
                'message' => $order->isPay === 1 ? '订单已付款' : '订单未付款',
            ];
        } else {
            return ['status' => false, 'message' => '不存在的订单信息或者订单已经关闭'];
        }
    }
}
