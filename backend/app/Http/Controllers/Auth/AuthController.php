<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function redirectToGithub()
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'read:user', 'user:email'])
            ->redirect();
    }

    public function handleGithubCallback()
    {
        try {
            $githubUser = Socialite::driver('github')->user();

            $user = User::updateOrCreate(
                ['github_id' => $githubUser->id],
                [
                    'name' => $githubUser->name ?? $githubUser->nickname,
                    'email' => $githubUser->email,
                    'github_token' => $githubUser->token,
                    'github_refresh_token' => $githubUser->refreshToken,
                    'avatar' => $githubUser->avatar
                ]
            );

            Auth::login($user);

            return response()->json([
                'user' => $user,
                'token' => $user->createToken('github-token')->plainTextToken
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed'], 401);
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-Hub-Signature-256');
        
        if (!$this->verifySignature($payload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Process the webhook event
        $event = $request->header('X-GitHub-Event');
        switch ($event) {
            case 'push':
                // Handle push event
                break;
            case 'pull_request':
                // Handle pull request event
                break;
            // Add more event handlers as needed
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    private function verifySignature($payload, $signature)
    {
        $secret = config('services.github.webhook_secret');
        $computedSignature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($computedSignature, $signature);
    }
}