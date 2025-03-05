<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

class DeploymentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'repository_url' => 'required|url',
            'branch' => 'string|max:255',
            'environment' => 'string|in:production,staging,development',
            'domain' => 'nullable|string|max:255',
            'environment_variables' => 'nullable|array'
        ]);

        $deployment = Deployment::create([
            'user_id' => Auth::id(),
            'project_name' => $validated['project_name'],
            'repository_url' => $validated['repository_url'],
            'branch' => $validated['branch'] ?? 'main',
            'environment' => $validated['environment'] ?? 'production',
            'domain' => $validated['domain'],
            'environment_variables' => $validated['environment_variables'],
            'status' => 'pending'
        ]);

        // Dispatch deployment job to queue
        Queue::push(new ProcessDeployment($deployment));

        return response()->json([
            'message' => 'Deployment initiated successfully',
            'deployment' => $deployment
        ], 201);
    }

    public function index(): JsonResponse
    {
        $deployments = Auth::user()->deployments()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($deployments);
    }

    public function show(Deployment $deployment): JsonResponse
    {
        $this->authorize('view', $deployment);

        return response()->json($deployment);
    }

    public function logs(Deployment $deployment): JsonResponse
    {
        $this->authorize('view', $deployment);

        return response()->json([
            'logs' => $deployment->build_logs
        ]);
    }

    public function destroy(Deployment $deployment): JsonResponse
    {
        $this->authorize('delete', $deployment);

        // Dispatch job to cleanup deployment resources
        Queue::push(new CleanupDeployment($deployment));

        $deployment->delete();

        return response()->json([
            'message' => 'Deployment deletion initiated'
        ]);
    }
}