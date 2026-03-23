<?php

declare(strict_types=1);

namespace App\Modules\Activities;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Lattice\Auth\Principal;
use Lattice\Database\Crud\CrudService;
use Lattice\Observability\Log;

/**
 * @extends CrudService<Activity>
 */
final class ActivityService extends CrudService
{
    protected function model(): string
    {
        return Activity::class;
    }

    /**
     * Map DTO field 'title' to model field 'subject'.
     *
     * @param array<string, mixed> &$data
     */
    protected function beforeCreate(array &$data, Principal $user): void
    {
        if (array_key_exists('title', $data)) {
            $data['subject'] = $data['title'];
            unset($data['title']);
        }
    }

    /**
     * Map 'title' -> 'subject' and handle completion toggle.
     *
     * @param array<string, mixed> &$data
     */
    protected function beforeUpdate(Model $model, array &$data): void
    {
        /** @var Activity $model */
        if (array_key_exists('title', $data)) {
            $data['subject'] = $data['title'];
            unset($data['title']);
        }

        // Handle completion toggle
        if (array_key_exists('completed', $data)) {
            if ($data['completed'] === true && $model->completed_at === null) {
                $data['completed_at'] = now()->format('Y-m-d H:i:s');
                Log::info('Activity completed', ['id' => $model->id]);
            } elseif ($data['completed'] === false && $model->completed_at !== null) {
                $data['completed_at'] = null;
                Log::info('Activity uncompleted', ['id' => $model->id]);
            }
            unset($data['completed']);
        }
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
