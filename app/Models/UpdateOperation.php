<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpdateOperation extends Model
{
    protected $fillable = [
        'public_id', 'initiated_by', 'confirmed_by', 'status', 'current_commit', 'target_commit',
        'target_message', 'changed_files_count', 'details', 'code_backup_path',
        'code_backup_checksum', 'database_backup_path', 'database_backup_checksum',
        'preflight_passed_at', 'backup_verified_at', 'confirmed_at', 'started_at',
        'finished_at', 'failure_message',
    ];

    protected $casts = [
        'details' => 'array',
        'preflight_passed_at' => 'datetime',
        'backup_verified_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
