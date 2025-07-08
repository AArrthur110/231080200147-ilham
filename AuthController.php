<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Libraries\JWTLibrary;
use CodeIgniter\API\ResponseTrait;

class AuthController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = 'App\Models\UserModel';
    protected $format = 'json';
    protected $jwt;

    public function __construct()
    {
        $this->jwt = new JWTLibrary();
    }

    public function register()
    {
        $data = $this->request->getPost();

        // ✅ Bug #6: Input validation
        if (!isset($data['name'], $data['email'], $data['password'])) {
            return $this->failValidationErrors('Name, email, and password are required.');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->failValidationErrors('Email format is invalid.');
        }

        if (strlen($data['password']) < 6) {
            return $this->failValidationErrors('Password must be at least 6 characters.');
        }

        $userModel = new UserModel();

        // ✅ Bug #7: Password hashed
        $userData = [
            'name' => htmlspecialchars($data['name']),
            'email' => strtolower($data['email']),
            'password' => password_hash($data['password'], PASSWORD_DEFAULT)
        ];

        if ($userModel->where('email', $userData['email'])->first()) {
            return $this->failResourceExists('Email already registered.');
        }

        $userId = $userModel->insert($userData);

        if ($userId) {
            // ✅ Bug #8: Jangan kembalikan password
            unset($userData['password']);
            return $this->respondCreated([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => $userData
            ]);
        }

        return $this->failServerError('Registration failed');
    }

    public function login()
    {
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        // ✅ Bug #9: Validasi input login
        if (empty($email) || empty($password)) {
            return $this->failValidationErrors('Email and password are required.');
        }

        $userModel = new UserModel();
        $user = $userModel->where('email', $email)->first();

        // ✅ Bug #10: Gunakan password_verify
        if ($user && password_verify($password, $user['password'])) {
            $payload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'exp' => time() + 3600 // token 1 jam
            ];

            $token = $this->jwt->encode($payload);

            unset($user['password']); // jangan kirim password ke response

            return $this->respond([
                'status' => 'success',
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ]);
        }

        return $this->failUnauthorized('Invalid credentials');
    }

    public function refresh()
    {
        // ✅ Bug #11: Implement refresh token
        $authHeader = $this->request->getHeaderLine('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->failUnauthorized('Token not provided');
        }

        $oldToken = $matches[1];
        $decoded = $this->jwt->decode($oldToken);

        if (!$decoded || !isset($decoded->user_id)) {
            return $this->failUnauthorized('Invalid token');
        }

        $newPayload = [
            'user_id' => $decoded->user_id,
            'email'   => $decoded->email ?? '',
            'exp'     => time() + 3600
        ];

        $newToken = $this->jwt->encode($newPayload);

        return $this->respond([
            'status' => 'success',
            'message' => 'Token refreshed successfully',
            'token' => $newToken
        ]);
    }
}
