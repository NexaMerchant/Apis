<?php

namespace NexaMerchant\Apis\Docs\V1\Shop\Controllers\Customer;

class AuthController
{
    /**
     * @OA\Post(
     *      path="/api/v1/customer/login",
     *      operationId="customerLogin",
     *      tags={"Customers"},
     *      summary="Login customer",
     *      description="Login customer",
     *
     *      @OA\Parameter(
     *          name="accept_token",
     *          description="Accept Token Flag",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="bool"
     *          )
     *      ),
     *
     *      @OA\RequestBody(
     *
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *
     *              @OA\Schema(
     *
     *                  @OA\Property(
     *                      property="email",
     *                      type="string",
     *                      format="email",
     *                      example="shop@example.com"
     *                  ),
     *                  @OA\Property(
     *                      property="password",
     *                      type="string",
     *                      format="password",
     *                      example="demo123"
     *                  ),
     *                  @OA\Property(
     *                      property="device_name",
     *                      type="string",
     *                      example="android"
     *                  ),
     *                  required={"email", "password"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(
     * 					property="message",
     * 					type="string",
     * 					example="Logged in successfully."
     *				),
     *				@OA\Property(
     * 					property="data",
     * 					type="object",
     * 					ref="#/components/schemas/Customer"
     *				)
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(
     * 					property="message",
     * 					type="string",
     * 					example="Invalid Email or Password"
     * 				)
     *          )
     *      )
     * )
     */
    public function login()
    {
    }

    /**
     * @OA\Post(
     *      path="/api/v1/customer/register",
     *      operationId="registerCustomer",
     *      tags={"Customers"},
     *      summary="Register customer",
     *      description="Register customer",
     *
     *      @OA\RequestBody(
     *
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *
     *              @OA\Schema(
     *
     *                  @OA\Property(
     *                      property="first_name",
     *                      type="string",
     *                      example="John"
     *                  ),
     *                  @OA\Property(
     *                      property="last_name",
     *                      type="string",
     *                      example="Doe"
     *                  ),
     *                  @OA\Property(
     *                      property="email",
     *                      type="string",
     *                      format="email",
     *                      example="shop@example.com"
     *                  ),
     *                  @OA\Property(
     *                      property="password",
     *                      type="string",
     *                      format="password",
     *                      example="admin123"
     *                  ),
     *                  @OA\Property(
     *                      property="password_confirmation",
     *                      type="string",
     *                      format="password",
     *                      example="admin123"
     *                  ),
     *                  required={"first_name", "last_name", "email", "password", "password_confirmation"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="message", type="string", example="Customer registered successfully.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="message", type="string", example="Invalid Request Parameters")
     *          )
     *      )
     * )
     */
    public function register()
    {
    }

    /**
     * @OA\Post(
     *      path="/api/v1/customer/forgot-password",
     *      operationId="customerForgotPassword",
     *      tags={"Customers"},
     *      summary="Forgot Password customer",
     *      description="Forgot Password customer",
     *
     *      @OA\RequestBody(
     *
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *
     *              @OA\Schema(
     *
     *                  @OA\Property(
     *                      property="email",
     *                      type="string",
     *                      format="email",
     *                      example="example@example.com"
     *                  ),
     *                  required={"email"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="message", type="string", example="We have e-mailed your reset password link.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="message", type="string", example="We can't find a user with that e-mail address.")
     *          )
     *      )
     * )
     */
    public function forgotPassword()
    {
    }

    /**
     * @OA\Post(
     *      path="/api/v1/customer/login-code",
     *      operationId="LoginWithCode",
     *      tags={"Customers"},
     *      summary="Login customer use code",
     *      description="Login customer use code",
     *
     *      @OA\Parameter(
     *          name="accept_token",
     *          description="Accept Token Flag",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="bool"
     *          )
     *      ),
     *
     *      @OA\RequestBody(
     *
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *
     *              @OA\Schema(
     *
     *                  @OA\Property(
     *                      property="email",
     *                      type="string",
     *                      format="email",
     *                      example="shop@example.com"
     *                  ),
     *                  @OA\Property(
     *                      property="code",
     *                      type="string",
     *                      format="code",
     *                      example="654231"
     *                  ),
     *                  @OA\Property(
     *                      property="device_name",
     *                      type="string",
     *                      example="android"
     *                  ),
     *                  required={"email", "code","device_name"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(
     * 					property="message",
     * 					type="string",
     * 					example="Logged in successfully."
     *				),
     *				@OA\Property(
     * 					property="data",
     * 					type="object",
     * 					ref="#/components/schemas/Customer"
     *				)
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(
     * 					property="message",
     * 					type="string",
     * 					example="Invalid Email or Password"
     * 				)
     *          )
     *      )
     * )
     */
    public function LoginWithCode() {
        
    }

    /**
     * @OA\Post(
     *      path="/api/v1/customer/get-code",
     *      operationId="customerGetCode",
     *      tags={"Customers"},
     *      summary="Get Code",
     *      description="Get Code",
     *
     *      @OA\RequestBody(
     *
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *
     *              @OA\Schema(
     *
     *                  @OA\Property(
     *                      property="email",
     *                      type="string",
     *                      format="email",
     *                      example="example@example.com"
     *                  ),
     *                  required={"email"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="message", type="string", example="We have e-mailed your reset password link.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="message", type="string", example="We can't find a user with that e-mail address.")
     *          )
     *      )
     * )
     */
    public function getCode(){

    }

    /**
     * @OA\Post(
     *      path="/api/v1/customer/guest-token",
     *      operationId="getGuestToken",
     *      tags={"Customers"},
     *      summary="Get Guest Token",
     *      description="Get Guest Token",
     *
     *      @OA\RequestBody(
     *
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *
     *              @OA\Schema(
     *
     *                  @OA\Property(
     *                      property="device_name",
     *                      type="string",
     *                      example="android"
     *                  ),
     *                  required={"device_name"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="message", type="string", example="Guest Token generated successfully.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="message", type="string", example="Invalid Request Parameters")
     *          )
     *      )
     * )
     */
    public function getGuestToken(){
    }
}
