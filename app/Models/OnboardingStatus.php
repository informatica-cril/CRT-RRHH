<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingStatus extends Model
{
    protected $table = 'onboarding_statuses';

    protected $fillable = [
        'user_id', 'profile_id', 'current_step',
        'completed', 'started_at', 'completed_at', 'steps',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'steps' => 'array', // JSON array of {document_id, viewed_at, signed_at}
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profile()
    {
        return $this->belongsTo(OnboardingProfile::class, 'profile_id');
    }

    /**
     * Mark as completed and update user flag.
     */
    public function markCompleted(): void
    {
        $this->update([
            'completed' => true,
            'completed_at' => now(),
        ]);

        /* Assignació directa, no massiva: 'onboarding_completed' no és un camp
           assignable de User —i no ha de ser-ho, o qualsevol podria donar-se l'alta
           per fet des de la seva pròpia fitxa—, de manera que aquesta línia s'estava
           descartant en silenci i l'assistent tornava a sortir a cada entrada. */
        if ($this->user) {
            $this->user->onboarding_completed = true;
            $this->user->save();
        }
    }
}
