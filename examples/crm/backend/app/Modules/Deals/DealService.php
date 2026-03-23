<?php

declare(strict_types=1);

namespace App\Modules\Deals;

use App\Models\Deal;
use Illuminate\Database\Eloquent\Model;
use Lattice\Database\Crud\CrudService;
use Lattice\Observability\Log;

/**
 * @extends CrudService<Deal>
 */
final class DealService extends CrudService
{
    protected function model(): string
    {
        return Deal::class;
    }

    /**
     * @param array<string, mixed> &$data
     */
    protected function beforeUpdate(Model $model, array &$data): void
    {
        /** @var Deal $model */
        $oldStage = $model->stage;

        // Auto-set actual_close_date on stage transitions to closed
        if (isset($data['stage']) && in_array($data['stage'], Deal::CLOSED_STAGES, true)) {
            $data['actual_close_date'] ??= date('Y-m-d');
        }

        // Auto-set probability based on stage
        if (isset($data['stage']) && !isset($data['probability'])) {
            $data['probability'] = Deal::probabilityForStage($data['stage']);
        }

        if (isset($data['stage']) && $data['stage'] !== $oldStage) {
            Log::info('Deal stage transitioned', [
                'id' => $model->id,
                'from' => $oldStage,
                'to' => $data['stage'],
            ]);
        }
    }

    /**
     * Move a deal to a new pipeline stage.
     */
    public function moveStage(int $id, string $stage, ?string $lostReason = null): Deal
    {
        $deal = Deal::findOrFail($id);
        $oldStage = $deal->stage;

        abort_if(
            !in_array($stage, Deal::STAGES, true),
            422,
            "Invalid stage: {$stage}",
        );

        $data = [
            'stage' => $stage,
            'probability' => Deal::probabilityForStage($stage),
        ];

        if (in_array($stage, Deal::CLOSED_STAGES, true)) {
            $data['actual_close_date'] = date('Y-m-d');
        }

        if ($stage === 'closed_lost' && $lostReason !== null) {
            $data['lost_reason'] = $lostReason;
        }

        $deal->update($data);

        Log::info('Deal stage moved', [
            'id' => $deal->id,
            'from' => $oldStage,
            'to' => $stage,
        ]);

        return $deal->fresh();
    }
}
