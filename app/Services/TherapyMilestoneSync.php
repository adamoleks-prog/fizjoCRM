<?php

namespace App\Services;

use App\Models\TherapyCycle;
use App\Models\TherapyMilestone;

class TherapyMilestoneSync
{
    /**
     * Applies the submitted milestone rows to the cycle: updates rows sent with an
     * id, creates the rest, and drops the ones no longer present.
     *
     * Rows are matched by id rather than replaced wholesale so that `achieved_at`
     * survives an unrelated edit of the plan.
     *
     * @param  array<int, array{id?: int|string|null, goal: string, horizon: string, achieved?: mixed}>  $rows
     */
    public function sync(TherapyCycle $cycle, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $goal = trim((string) ($row['goal'] ?? ''));

            if ($goal === '') {
                continue;
            }

            $milestone = isset($row['id']) && $row['id']
                ? $cycle->milestones()->whereKey($row['id'])->first()
                : null;

            $achieved = filter_var($row['achieved'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($milestone) {
                $milestone->update([
                    'goal' => $goal,
                    'horizon' => $row['horizon'],
                    // Keep the original completion time when it was already achieved.
                    'achieved_at' => $achieved ? ($milestone->achieved_at ?? now()) : null,
                ]);
            } else {
                $milestone = new TherapyMilestone([
                    'goal' => $goal,
                    'horizon' => $row['horizon'],
                    'achieved_at' => $achieved ? now() : null,
                ]);
                $milestone->operator_id = $cycle->operator_id;
                $milestone->therapy_cycle_id = $cycle->id;
                $milestone->save();
            }

            $keptIds[] = $milestone->id;
        }

        $cycle->milestones()->whereKeyNot($keptIds)->delete();
    }
}
