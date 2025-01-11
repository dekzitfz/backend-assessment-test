<?php

namespace App\Traits;

use Symfony\Component\HttpFoundation\Response as HttpResponseCode;

trait ApiResponseTrait
{
    public function validateFailed(
        array $errors, 
        string $msg = "validation failed", 
        int $status_code = HttpResponseCode::HTTP_BAD_REQUEST
    ){
        $response = [
            'status_code' => $status_code,
            'message' => $msg,
            'errors' => [],
        ];

        if (!empty($errors)) {
            $response = array_merge($response, $errors);
        }

        return $response;
    }
}
