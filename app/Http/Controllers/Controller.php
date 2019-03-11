<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function successResponse($data, $message = 'success')
    {
        return [
            'status' => true,
            'message' => $message,
            'data' => $data,
        ];
    }
}
