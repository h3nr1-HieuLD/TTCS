<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deployment extends Model
{
    protected $fillable = [
        'user_id',
        'project_name',
        'repository_url',
        'branch',
        'environment',
        'domain',
        'environment_variables',
        'status',
        'build_logs',
        'container_id',
        'deployed_at'
    ];

    protected $casts = [
        'environment_variables' => 'array',
        'deployed_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'building', 'deploying']);
    }

    public function markAsDeployed(): void
    {
        $this->update([
            'status' => 'deployed',
            'deployed_at' => now()
        ]);
    }

    public function markAsFailed(string $logs = null): void
    {
        $this->update([
            'status' => 'failed',
            'build_logs' => $logs
        ]);
    }
}