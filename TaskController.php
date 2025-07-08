<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\TaskModel;
use App\Models\ProjectModel;
use App\Libraries\JWTLibrary;

class TaskController extends ResourceController
{
    protected $modelName = 'App\Models\TaskModel';
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
        // ✅ Bug #24: Filter berdasarkan user
        $userId = $this->getUserIdFromToken();

        // Ambil project milik user
        $projectModel = new ProjectModel();
        $userProjects = $projectModel->where('user_id', $userId)->findAll();
        $projectIds = array_column($userProjects, 'id');

        $tasks = $this->model->whereIn('project_id', $projectIds)->findAll();

        return $this->respond([
            'status' => 'success',
            'data' => $tasks
        ]);
    }

    public function create()
    {
        $userId = $this->getUserIdFromToken();
        $data = $this->request->getPost();

        // ✅ Bug #25: Validasi field wajib
        if (!isset($data['project_id']) || !isset($data['title']) || strlen($data['title']) < 3) {
            return $this->failValidationErrors('project_id and title (min 3 chars) are required.');
        }

        // ✅ Bug #26: Validasi kepemilikan project
        $projectModel = new ProjectModel();
        $project = $projectModel->find($data['project_id']);

        if (!$project || $project['user_id'] != $userId) {
            return $this->failForbidden('You are not authorized to create tasks for this project.');
        }

        $taskId = $this->model->insert($data);

        if ($taskId) {
            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Task created successfully',
                'id' => $taskId
            ]);
        }

        return $this->failServerError('Creation failed');
    }

    public function show($id = null)
    {
        $userId = $this->getUserIdFromToken();
        $task = $this->model->find($id);

        if (!$task) {
            return $this->failNotFound('Task not found');
        }

        // ✅ Bug #27: Cek kepemilikan task melalui project
        $projectModel = new ProjectModel();
        $project = $projectModel->find($task['project_id']);

        if (!$project || $project['user_id'] != $userId) {
            return $this->failForbidden('You are not authorized to view this task.');
        }

        return $this->respond($task);
    }

    public function update($id = null)
    {
        $userId = $this->getUserIdFromToken();
        $task = $this->model->find($id);

        if (!$task) {
            return $this->failNotFound('Task not found');
        }

        // ✅ Bug #27 & #28: Cek ownership dan validasi status
        $projectModel = new ProjectModel();
        $project = $projectModel->find($task['project_id']);

        if (!$project || $project['user_id'] != $userId) {
            return $this->failForbidden('You are not authorized to update this task.');
        }

        $data = $this->request->getRawInput();

        if (isset($data['status']) && !in_array($data['status'], ['pending', 'in_progress', 'completed'])) {
            return $this->failValidationErrors('Invalid status. Must be: pending, in_progress, completed.');
        }

        if ($this->model->update($id, $data)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Task updated successfully'
            ]);
        }

        return $this->failServerError('Update failed');
    }

    public function delete($id = null)
    {
        $userId = $this->getUserIdFromToken();
        $task = $this->model->find($id);

        if (!$task) {
            return $this->failNotFound('Task not found');
        }

        // ✅ Bug #27 (lanjutan): Cek ownership
        $projectModel = new ProjectModel();
        $project = $projectModel->find($task['project_id']);

        if (!$project || $project['user_id'] != $userId) {
            return $this->failForbidden('You are not authorized to delete this task.');
        }

        if ($this->model->delete($id)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Task deleted successfully'
            ]);
        }

        return $this->failServerError('Delete failed');
    }
}
