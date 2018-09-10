<?php

namespace App\Exceptions;

use Exception;

class ErrorMsgException extends Exception
{
/**
     * The underlying response instance.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    protected $message;

    /**
     * Create a new HTTP response exception instance.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     * Get the underlying response instance.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponse()
    {
        return response()->json(['status' => false, 'message' => $this->message]);
    }
}
