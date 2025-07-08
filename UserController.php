<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Libraries\JWTLibrary;

class UserController extends ResourceController
{
    protected $modelName = 'App\Models\UserModel';
    protected $format = 'json';
    protected $jwt;

    public function __construct()
    {
        $this->jwt = new JWTLibrary();
    }

    private function getUserIdFromToken()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $decoded = $this->jwt->decode($matches[1]);
            return $decoded->user_id ?? null;
        }
        return null;
    }

    public function index()
    {
        // ✅ Bug #12: Tambahkan pagination
        $page = (int) $this->request->getGet('page') ?? 1;
        $limit = (int) $this->request->getGet('limit') ?? 10;

        $users = $this->model->paginate($limit, 'default', $page);

        // Hilangkan password dari setiap user
        $cleanedUsers = array_map(function ($user) {
            unset($user['password']);
            return $user;
        }, $users);

        return $this->respond([
            'status' => 'success',
            'page' => $page,
            'data' => $cleanedUsers,
        ]);
    }

    public function show($id = null)
    {
        // ✅ Bug #13: Validasi ID
        if (!is_numeric($id)) {
            return $this->failValidationErrors('Invalid user ID');
        }

        $user = $this->model->find($id);

        if (!$user) {
            return $this->failNotFound('User not found');
        }

        // ✅ Bug #14: Hilangkan data sensitif
        unset($user['password']);

        return $this->respond($user);
    }

    public function update($id = null)
    {
        $authUserId = $this->getUserIdFromToken();

        // ✅ Bug #15: Authorization check
        if ((int)$authUserId !== (int)$id) {
            return $this->failForbidden('You are not allowed to update this user.');
        }

        $data = $this->request->getRawInput();

        // ✅ Bug #16: Validasi input
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->failValidationErrors('Invalid email format.');
        }

        if (!$this->model->find($id)) {
            return $this->failNotFound('User not found');
        }

        if ($this->model->update($id, $data)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'User updated successfully'
            ]);
        }

        return $this->failServerError('Update failed');
    }

    public function delete($id = null)
    {
        $authUserId = $this->getUserIdFromToken();

        // ✅ Bug #17: Authorization check
        if ((int)$authUserId !== (int)$id) {
            return $this->failForbidden('You are not allowed to delete this user.');
        }

        if (!$this->model->find($id)) {
            return $this->failNotFound('User not found');
        }

        if ($this->model->delete($id)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
        }

        return $this->failServerError('Delete failed');
    }
}
