<?php

declare(strict_types=1);

namespace App\Modules\Activities;

use App\Models\Activity;
use App\Modules\Activities\Dto\CreateActivityDto;
use App\Modules\Activities\Dto\UpdateActivityDto;
use Lattice\Auth\Principal;
use Lattice\Observability\Log;

final class ActivityService
{
    /**
     * Create a new activity.
     */
    public function create(CreateActivityDto $dto, Principal $user): Activity
    {
        $activity = Activity::create([
            'type' => $dto->type,
            'subject' => $dto->title,
            'description' => $dto->description,
            'due_date' => $dto->due_date,
            'contact_id' => $dto->contact_id,
            'deal_id' => $dto->deal_id,
            'owner_id' => (int) $user->getId(),
        ]);

        Log::info('Activity created', [
            'id' => $activity->id,
            'type' => $activity->type,
            'user_id' => $user->getId(),
        ]);

        return $activity;
    }

    /**
     * Update an existing activity.
     */
    public function update(int $id, UpdateActivityDto $dto): Activity
    {
        $activity = Activity::findOrFail($id);

        $data = array_filter([
            'type' => $dto->type,
            'subject' => $dto->title,
            'description' => $dto->description,
            'due_date' => $dto->due_date,
            'contact_id' => $dto->contact_id,
            'deal_id' => $dto->deal_id,
        ], fn (mixed $value): bool => $value !== null);

        // Handle completion toggle
        if ($dto->completed === true && $activity->completed_at === null) {
            $data['completed_at'] = now()->format('Y-m-d H:i:s');
            Log::info('Activity completed', ['id' => $activity->id]);
        } elseif ($dto->completed === false && $activity->completed_at !== null) {
            $data['completed_at'] = null;
            Log::info('Activity uncompleted', ['id' => $activity->id]);
        }

        $activity->update($data);

        Log::info('Activity updated', ['id' => $activity->id]);

        return $activity->fresh();
    }

    /**
     * Delete an activity (soft delete).
     */
    public function delete(int $id): void
    {
        $activity = Activity::findOrFail($id);
        $activity->delete();

        Log::info('Activity deleted', ['id' => $id]);
    }

    /**
     * Mark an activity as completed.
     */
    public function complete(int $id): Activity
    {
        $activity = Activity::findOrFail($id);
        $activity->update(['completed_at' => now()->format('Y-m-d H:i:s')]);

        Log::info('Activity completed', ['id' => $id]);

        return $activity->fresh();
    }
}
