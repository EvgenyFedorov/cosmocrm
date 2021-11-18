<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organizations;
use App\Models\UsersInOrganizations;
use App\Models\UsersInRoles;
use App\Models\UsersPhoneCodes;
use Illuminate\Http\Request;

use Carbon\Carbon;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    public function registration(Request $request){

        $data = $request->all();

        $validator = Validator::make($data, [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => [
                    'areas' => $validator->errors()
                ],
                'type_error' => 'validator_fails',
                'status_code' => 500
            ], 500);
        }

        $user = User::where('phone', $request['phone'])->first();

        if(isset($user)) {
            return response()
                ->json([
                    'message' => [
                        'areas' => [
                            'password' => [ trans('auth.account_exists') ],
                        ]
                    ],
                    'type_error' => 'account_exists',
                    'status_code' => 500
                ], 500);
        }else{

            DB::beginTransaction();

            $create_user = User::create([
                'name' => null,
                'email' => null,
                'phone' => $request['phone'],
                'enable' => 0,
                'password' => bcrypt('0000'),
                'updated_at' => null,
                'deleted_at' => null,
            ]);

            $create_organization = Organizations::create([
                'user_id' => $create_user->id,
                'name' => null,
            ]);

            $create_user_in_role = UsersInRoles::create([
                'user_id' => $create_user->id,
                'organization_id' => $create_organization->id,
                'role_id' => 4,
            ]);

            $create_user_in_organization = UsersInOrganizations::create([
                'user_id' => $create_user->id,
                'organization_id' => $create_organization->id,
            ]);

            DB::commit();

            try{

                $request->request->add([
                    'grant_type'    => 'password',
                    'client_id'     => config('passport.client_id'),
                    'client_secret' => config('passport.client_secret'),
                    'username'      => $create_user->phone,
                    'password'      => '0000'
                ]);

                $tokenRequest = Request::create('/oauth/token','post');
                $result = Route::dispatch($tokenRequest);

                if($result->getStatusCode() == 200) {

                    //$token = $create_user->createToken('PoweredByFedorovEvgeny');
                    $token = $result->getContent();
                    return response()
                        ->json([
                            'message' => [$token],
                        ], 200);

                }

            }catch (\Exception $exception){
                return response()
                    ->json([
                        'message' => [$exception->getMessage()],
                        'type_error' => 'auth_failed',
                        'status_code' => 500
                    ], 500);
            }

        }

    }

    public function authorization(Request $request){

        $post = $request->all();

        if(isset($post['phone']) && !empty($post['phone']) && isset($post['code']) && !empty($post['code'])){

            return $this->loginSetCode($request);

        }elseif(isset($post['phone']) && !empty($post['phone'])){

            return $this->loginGetCode($request);

        }else{

            return response()
                ->json([
                    'message' => [ trans('auth.required_parameters') ],
                    'status_code' => 500
                ], 500);

        }

    }

    public function loginGetCode(Request $request){

        $post = $request->all();

        $user = User::where('phone', $post['phone'])->first();

        if(isset($user)) {

            /// 111

            DB::beginTransaction();

            //$code = rand(1000, 9999);
            $code = '0000';

            $create_phone_codes = UsersPhoneCodes::create([
                'user_id' => $user->id,
                'code' => $code,
            ]);

            $update_user = User::where('id', $user->id)->first();

            $update_user->password = bcrypt($code);
            $update_user->save();

            # TODO: отправка кода смс или пушом
            // $create_phone_codes

            DB::commit();

            return response()
                ->json([
                    'message' => [ trans('auth.code_send') ],
                ], 200);

        }else{

            return response()
                ->json([
                    'message' => [ trans('auth.account_dont_exists') ],
                    'type_error' => 'account_exists',
                    'status_code' => 500
                ], 500);

        }

    }

    public function loginSetCode(Request $request)
    {

        $post = $request->all();

        $user = User::where('phone', $post['phone'])->first();

        if(isset($user)) {

            $request->request->add([
                'grant_type' => 'password',
                'client_id' => config('passport.client_id'),
                'client_secret' => config('passport.client_secret'),
                'username' => $post['phone'],
                'password' => $post['code']
            ]);

            $tokenRequest = Request::create('/oauth/token', 'post');
            $result = Route::dispatch($tokenRequest);

            if ($result->getStatusCode() == 200) {

                return response()
                    ->json([
                        'message' => [$result->getContent()],
                    ], 200);

            }else{

                return response()
                    ->json([
                        'message' => [$result->getContent()],
                    ], $result->getStatusCode());

            }

        }else{

            return response()
                ->json([
                    'message' => [ trans('auth.account_dont_exists') ],
                    'type_error' => 'account_exists',
                    'status_code' => 500
                ], 500);

        }

    }

}
