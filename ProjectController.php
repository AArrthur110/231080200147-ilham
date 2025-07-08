<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\ProjectModel;
use App\Libraries\JWTLibrary;

class ProjectController extends ResourceController
{
    protected $modelName = 'App\Models\ProjectModel';
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
        // ✅ Bug #18: Hanya tampilkan project milik user
        $userId = $this->getUserIdFromToken();
        $projects = $this->model->where('user_id', $userId)->findAll();

        return $this->respond([
            'status' => 'success',
            'data' => $projects
        ]);
    }

    public function create()
    {
        $userId = $this->getUserIdFromToken();
        $data = $this->request->getPost();

        // ✅ Bug #19: Validasi input
        if (!isset($data['title']) || strlen($data['title']) < 3) {
            return $this->failValidationErrors('Title is required and must be at least 3 characters.');
        }

        // ✅ Bug #20: Tambahkan user_id dari JWT
        $data['user_id'] = $userId;

        $projectId = $this->model->insert($data);

        if ($projectId) {
            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Project created successfully',
                'id' => $projectId
            ]);
        }

        return $this->failServerError('Creation failed');
    }

    public function show($id = null)
    {
        $userId = $this->getUserIdFromToken();
        $project = $this->model->find($id);

        if (!$project) {
            return $this->failNotFound('Project not found');
        }

        // ✅ Bug #21: Validasi kepemilikan
        if ($project['user_id'] != $userId) {
            return $this->failForbidden('You are not authorized to view this project');
        }

        return $this->respond($project);
    }

    public function update($id = null)
    {
        $userId = $this->getUserIdFromToken();
        $project = $this->model->find($id);

        if (!$project) {
            return $this->failNotFound('Project not found');
        }

        // ✅ Bug #22: Cek apakah user pemilik project
        if ($project['user_id'] != $userId) {
            return $this->failForbidden('You are not authorized to update this project');
        }

        $data = $this->request->getRawInput();

        if (isset($data['title']) && strlen($data['title']) < 3) {
            return $this->failValidationErrors('Title must be at least 3 characters.');
        }

        if ($this->model->update($id, $data)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Project updated successfully'
            ]);
        }

        return $this->failServerError('Update failed');
    }

    public function delete($id = null)
    {
        $userId = $this->getUserIdFromToken();
        $project = $this->model->find($id);

        if (!$project) {
            return $this->failNotFound('Project not found');
        }

        // ✅ Bug #23: Cek apakah user pemilik project
        if ($project['user_id'] != $userId) {
            return $this->failForbidden('You are not authorized to delete this project');
        }

        if ($this->model->delete($id)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Project deleted successfully'
            ]);
        }

        return $this->failServerError('Delete failed');
    }
}
