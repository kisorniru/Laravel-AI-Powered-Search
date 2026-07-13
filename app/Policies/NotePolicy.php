<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotePolicy
{
    public function view(?User $user, Note $note): Response|bool
    {
        if ($note->is_public || $user?->id === $note->user_id) {
            return true;
        }

        return Response::denyAsNotFound();
    }

    public function update(User $user, Note $note): bool
    {
        return $user->id === $note->user_id;
    }

    public function delete(User $user, Note $note): bool
    {
        return $user->id === $note->user_id;
    }
}
