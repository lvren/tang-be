<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Model\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Log;
use Qcloud\Cos\Client as QcloudClient;

// use App\Model\Country;
// use App\Model\School;

class CosController extends Controller
{
    private $cosClient;
    private $bucket;

    public function __construct()
    {
        $this->middleware('auth');
        $this->cosClient = new QcloudClient(array(
            'region' => 'ap-chengdu', #地域，如ap-guangzhou,ap-beijing-1
            'credentials' => array(
                'secretId' => env('TEC_SECRET_ID'),
                'secretKey' => env('TEC_SECRET_KEY'),
            ),
        ));
        $this->bucket = 'talk-' . env('TEC_APP_ID');
    }

    public function upload(Request $request)
    {
        if (!$request->hasFile('file')) {
            throw new Exception('没有找到上传的文件');
        }
        $file = $request->file('file');
        $key = (string) Str::uuid();
        $originalName = $file->getClientOriginalName();
        try {
            $result = $this->cosClient->putObject(array(
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => fopen($file, 'rb'),
            ));

            $image = new Images();
            $image->title = $originalName;
            $image->key = $key;
            $image->save();

            $signedUrl = $this->cosClient->getObjectUrl($this->bucket, $key, '+10 minutes');
            return $this->successResponse([
                'url' => $signedUrl,
                'key' => $key,
                'id' => $image->id,
            ]);
        } catch (\Exception $e) {
            Log::error('上传文件失败');
            throw new Exception('上传文件失败');
        }
    }

    public function download(Request $request)
    {
        $key = $request->input('key');

        try {
            $signedUrl = $this->cosClient->getObjectUrl($this->bucket, $key, '+10 minutes');
            return $this->successResponse($signedUrl);
        } catch (\Exception $e) {
            throw new Exception('获取文件地址失败');
        }
    }
}
