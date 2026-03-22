<?php

declare(strict_types=1);

namespace App\Modules\Deals;

use App\Models\Deal;
use App\Modules\Deals\Dto\CreateDealDto;
use App\Modules\Deals\Dto\UpdateDealDto;
use Lattice\Auth\Principal;
use Lattice\Observability\Log;

final class DealService
{
    /**
     * Create a new deal.
     */
    public function create(CreateDealDto $dto, Principal $user): Deal
    {
        $deal = Deal::create([
            'title' => $dto->title,
            'value' => $dto->value,
            'currency' => $dto->currency,
            'stage' => $dto->stage,
            'probability' => $dto->probability,
            'expected_close_date' => $dto->expected_close_date,
            'contact_id' => $dto->contact_id,
            'company_id' => $dto->company_id,
            'owner_id' => (int) $user->getId(),
        ]);

        Log::info('Deal created', [
            'id' => $deal->id,
            'title' => $deal->title,
            'value' => $deal->value,
            'user_id' => $user->getId(),
        ]);

        return $deal;
    }

    /**
     * Update an existing deal, including stage transitions.
     */
    public function update(int $id, UpdateDealDto $dto): Deal
    {
        $deal = Deal::findOrFail($id);
        $oldStage = $deal->stage;

        $data = array_filter([
            'title' => $dto->title,
            'value' => $dto->value,
            'currency' => $dto->currency,
            'stage' => $dto->stage,
            'probability' => $dto->probability,
            'expected_close_date' => $dto->expected_close_date,
            'actual_close_date' => $dto->actual_close_date,
            'contact_id' => $dto->contact_id,
            'company_id' => $dto->company_id,
            'lost_reason' => $dto->lost_reason,
        ], fn (mixed $value): bool => $value !== null);

        // Auto-set actual_close_date on stage transitions to closed
        if (isset($data['stage']) && in_array($data['stage'], ['closed_won', 'closed_lost'], true)) {
            $data['actual_close_date'] ??= date('Y-m-d');
        }

        // Auto-set probability based on stage
        if (isset($data['stage']) && !isset($data['probability'])) {
            $data['probability'] = match ($data['stage']) {
                'lead' => 10,
                'qualified' => 25,
                'proposal' => 50,
                'negotiation' => 75,
                'closed_won' => 100,
                'closed_lost' => 0,
                default => $deal->probability,
            };
        }

        $deal->update($data);

        if (isset($data['stage']) && $data['stage'] !== $oldStage) {
            Log::info('Deal stage transitioned', [
                'id' => $deal->id,
                'from' => $oldStage,
                'to' => $data['stage'],
            ]);
        }

        Log::info('Deal updated', ['id' => $deal->id]);

        return $deal->fresh();
    }

    /**
     * Delete a deal (soft delete).
     */
    public function delete(int $id): void
    {
        $deal = Deal::findOrFail($id);
        $deal->delete();

        Log::info('Deal deleted', ['id' => $id]);
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
            'probability' => match ($stage) {
                'lead' => 10,
                'qualified' => 25,
                'proposal' => 50,
                'negotiation' => 75,
                'closed_won' => 100,
                'closed_lost' => 0,
            },
        ];

        if (in_array($stage, ['closed_won', 'closed_lost'], true)) {
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
