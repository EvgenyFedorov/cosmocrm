<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Carbon\Carbon;
use App\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Route;

class RegisterController extends Controller
{
    /**
     * Login user and create token
     *
     * @param  [string] login
     * @param  [string] password
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    public function login(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'login' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => [
                    'areas' => $validator->errors()
                ],
                'type_error' => 'validator_fails',
                'status_code' => 422
            ], 422);
        }

        $user = User::where('login', $request['login'])->first();

        if(!isset($user)) {
            return response()
                ->json([
                    'message' => [
                        'areas' => [
                            'password' => [ trans('auth.invalid_login') ],
                        ]
                    ],
                    'type_error' => 'invalid_login',
                    'status_code' => 404
                ], 404);
        }

        if(isset($user) && !$user->active) {
            return response()
                ->json([
                    'message' => [
                        'areas' => [
                            'password' => [ trans('auth.account_blocked', [
                                'user' => $user->name
                            ]) ],
                        ]
                    ],
                    'type_error' => 'account_blocked',
                    'status_code' => 403
                ], 403);
        }

        // if(!$user->isDeleted()) {
        //     return response()
        //         ->json([
        //             'message' => 'Пользователь удален'
        //         ], 401);
        // }

        if(!($user->admin() && $user->login === User::LOGIN_ADMIN) && !Hash::check($request['password'], $user->password)) {
            if(!$user->loginAttempts()) {
                return response()
                    ->json([
                        'message' => [
                            'areas' => [
                                'password' => [ trans('auth.account_blocked', [
                                    'user' => $user->name
                                ]) ],
                            ]
                        ],
                        'type_error' => 'account_blocked',
                        'status_code' => 403
                    ], 403);
            }

            return response()
                ->json([
                    'message' => [
                        'areas' => [
                            'password' => [ trans('auth.invalid_password', [
                                'user' => $user->name
                            ]) ],
                        ]
                    ],
                    'type_error' => 'invalid_password',
                    'status_code' => 403
                ], 403);
        }

        if(!($user->admin() && $user->login === User::LOGIN_ADMIN) && $user->password_invalid_at < Carbon::now()) {
            if(!$user->loginAttempts()) {
                return response()
                    ->json([
                        'message' => [
                            'areas' => [
                                'password' => [ trans('auth.account_blocked', [
                                    'user' => $user->name
                                ]) ],
                            ]
                        ],
                        'type_error' => 'account_blocked',
                        'status_code' => 403
                    ], 403);
            }

            return response()
                ->json([
                    'message' => [
                        'areas' => [
                            'password' => [ trans('auth.pass_outdated', [
                                'user' => $user->name
                            ]) ],
                        ]
                    ],
                    'type_error' => 'pass_outdated',
                    'status_code' => 403
                ], 403);
        }

        $request->request->add([
            'grant_type'    => 'password',
            'client_id'     => config('passport.client_id'),
            'client_secret' => config('passport.client_secret'),
            'username'      => $user->email,
            'password'      => $request['password'],
            'scope'         => ''
        ]);

        $tokenRequest = Request::create('/oauth/token','post');
        $resp = Route::dispatch($tokenRequest);

        if($resp->getStatusCode() == 200) {

            if (!($user->admin() && $user->login === User::LOGIN_ADMIN)) {
                $password = random_int(10000, 99999);

                $user->timestamps = false;
                $user->password = bcrypt($password);
                $user->login_attempts = 0;
                $user->save();
            }

            return $resp;
        } else {
            if(!$user->loginAttempts()) {
                return response()
                    ->json([
                        'message' => [
                            'areas' => [
                                'password' => [ trans('auth.account_blocked', [
                                    'user' => $user->name
                                ]) ],
                            ]
                        ],
                        'type_error' => 'account_blocked',
                        'status_code' => 403
                    ], 403);
            }

            return response()
                ->json([
                    'message' => [
                        'areas' => [
                            'password' => [ trans('auth.invalid_auth') ],
                        ]
                    ],
                    'type_error' => 'invalid_auth',
                    'status_code' => 403
                ], 403);
        }
    }

    /**
     * Refresh token
     *
     * @return [string] refresh_token
     */
    public function refresh(Request $request)
    {
        $request->request->add([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $request['refresh_token'],
            'client_id'     => config('passport.client_id'),
            'client_secret' => config('passport.client_secret'),
            'scope'         => ''
        ]);

        $tokenRequest = Request::create('/oauth/token','post');
        $resp = Route::dispatch($tokenRequest);

        if($resp->getStatusCode() == 200) {
            return $resp;
        } else {
            return response()
                ->json([
                    'message' => [ trans('auth.invalid_auth') ],
                    'type_error' => 'invalid_auth',
                    'status_code' => 403
                ], 403);
        }
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if(!isset($user)) {
            return response()->json([
                'message' => [ trans('auth.logout_error') ],
                'type_error' => 'server_error',
                'status_code' => 400
            ], 400);
        }

        $user->token()->revoke();

        $user->put_to_log([ [ 'code' => 'logout'] ]);

        return response()->json([
            'message' => [ trans('auth.logout_success') ],
            'status_code' => 200
        ]);
    }

    /**
     * Add log info to file
     *
     * @return [json] message
     */
    public function putToLog(Request $request)
    {
        $user = $request->user();

        if(!isset($user)) {
            return response()->json([
                'message' => [ trans('auth.unauthenticated') ],
                'type_error' => 'unauthenticated',
                'status_code' => 400
            ], 400);
        }

        $user->put_to_log($request['to_log']);

        return response()->json([
            'message' => [ trans('common.success') ],
            'status_code' => 201
        ], 201);
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if(!isset($user)) {
            return response()->json([
                'message' => [ trans('auth.unauthenticated') ],
                'type_error' => 'unauthenticated',
                'status_code' => 403
            ], 403);
        }

        return response()->json($user->load(['role']));
    }
}
