<?php

namespace App\Api;

use Illuminate\Http\JsonResponse;
use App\Api\Contracts\ApiInterface;

class ApiResponse implements ApiInterface
{
    const HTTP_OK = 200;
    const HTTP_NOT_FOUND = 404;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_BAD_REQUEST = 429;

    /**
     * Create API response.
     *
     * @param int    $status
     * @param string $message
     * @param array  $data
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function response($status = 200, $message = null, $data = [], ...$extraData)
    {
        $json = [
            'STATUS'  => intval(!empty($data) || $status === 200),
            'MESSAGE' => $message,
            'DATA'    => !empty($data) ? $data : null
        ];

        if ($extraData) {
            foreach ($extraData as $extra) {
                $json = array_merge($json, $extra);
            }
        }

        return response()->json($json, $status, []);
    }

    /**
     * Create API response for empty Array if developer ask.
     *
     * @param int    $status
     * @param string $message
     * @param array  $data
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function arrayResponse($status = 200, $message = null, $data = [], ...$extraData)
    {
        $json = [
            'STATUS'  => intval(!empty($data) || $status === 200),
            'MESSAGE' => $message,
            'DATA'    => !empty($data) ? $data : []
        ];

        if ($extraData) {
            foreach ($extraData as $extra) {
                $json = array_merge($json, $extra);
            }
        }

        return response()->json($json, $status, []);
    }

    /**
     * Create successful (200) API response.
     *
     * @param string $message
     * @param array  $data
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function ok($message = null, $data = [], ...$extraData)
    {
        if (is_null($message)) {
            $message = config('api.messages.success');
        }

        return static::response(static::HTTP_OK, $message, $data, ...$extraData);
    }

    public static function okNoData($message = 'No Data Found', $data = [], ...$extraData)
    {
        return response()->json([
            'STATUS'  => 0,
            'MESSAGE' => __($message),
            'DATA'    => null
        ], 200);
    }

    /**
     * Create Simple successful (200) API response.
     *
     * @param string $message
     * @param array  $data
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function simple($message = null,$status = 200)
    {
        if (is_null($message)) {
            $message = config('api.messages.success');
        }
        $json = [
            'STATUS'  => intval(!empty($data) || $status === 200),
            'MESSAGE' => $message
        ];
        return response()->json($json);
    }

    /**
     * Create successful (200) API response.
     *
     * @param string $message
     * @param array  $data
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function success($message = null, $data = [], ...$extraData)
    {
        return static::ok($message, $data, ...$extraData);
    }
    public static function successArray($message = null, $data = [], ...$extraData)
    {
        return static::arrayResponse(static::HTTP_OK, $message, $data, ...$extraData);
    }

    /**
     * Create Not found (404) API response.
     *
     * @param string $message
     *
     * @return JsonResponse
     */
    public static function notFound($message = null)
    {
        if (is_null($message)) {
            $message = config('api.messages.notfound');
        }

        return static::response(static::HTTP_NOT_FOUND, $message, []);
    }

    /**
     * Create Not found (404) API response. for Arrays
     *
     * @param string $message
     *
     * @return JsonResponse
     */
    public static function ArraynotFound($message = null)
    {
        if (is_null($message)) {
            $message = config('api.messages.notfound');
        }

        return static::arrayResponse(static::HTTP_NOT_FOUND, $message, []);
    }
    
    /**
     * Create Not found (404) API response. for Arrays
     *
     * @param string $message
     *
     * @return JsonResponse
     */
    public static function simpleArraynotFound($message = null)
    {
        if (is_null($message)) {
            $message = config('api.messages.notfound');
        }

        return static::arrayResponse(static::HTTP_OK, $message, []);
    }
    /**
     * Create Validation (422) API response.
     *
     * @param string $message
     * @param array  $errors
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function validation($message = null, $errors = [], ...$extraData)
    {
        if (is_null($message)) {
            $message = config('api.messages.validation');
        }

        return static::response(static::HTTP_UNPROCESSABLE_ENTITY, $message, $errors, ...$extraData);
    }

    /**
     * Create Validation (422) API response.
     *
     * @param string $message
     * @param array  $data
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function forbidden($message = null, $data = [], ...$extraData)
    {
        if (is_null($message)) {
            $message = config('api.messages.forbidden');
        }

        return static::response(static::HTTP_FORBIDDEN, $message, $data, ...$extraData);
    }

    public static function bad_request($message = null, $data = [], ...$extraData)
    {
        return static::response(static::HTTP_BAD_REQUEST, $message, $data, ...$extraData);
    }
    
    /**
     * Create Validation (401) API response.
     *
     * @param string $message
     * @param array  $data
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function unauthorized($message = null, $data = [], ...$extraData)
    {
        if (is_null($message)) {
            $message = config('api.messages.unauthorized');
        }

        return static::response(static::HTTP_UNAUTHORIZED, $message, $data, ...$extraData);
    }
    public static function Notverify($message = null, $data = [], ...$extraData)
    {
        if (is_null($message)) {
            $message = "Verify your account & Login";
        }

        return static::response(static::HTTP_UNAUTHORIZED, $message, $data, ...$extraData);
    }

    public static function Notverify2($message = null, $data = [], ...$extraData)
    {
        if (is_null($message)) {
            $message = "Verify your account & Login";
        }
        $json = [
            'STATUS'  => 0,
            'MESSAGE' => $message,
            'DATA'    => !empty($data) ? $data : null
        ];

        if ($extraData) {
            foreach ($extraData as $extra) {
                $json = array_merge($json, $extra);
            }
        }

        return response()->json($json, static::HTTP_OK, []);
        // return static::response(static::HTTP_OK, $message, $data, ...$extraData);
    }

    /**
     * Create Server error (500) API response.
     *
     * @param string $message
     * @param array  $data
     * @param array  $extraData
     *
     * @return JsonResponse
     */
    public static function error($message = null, $data = [], ...$extraData)
    {
        if (is_null($message)) {
            $message = config('api.messages.error');
        }

        return static::response(static::HTTP_INTERNAL_SERVER_ERROR, $message, $data, ...$extraData);
    }
}
