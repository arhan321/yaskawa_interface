<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\MaintenanceRecommendation;
use App\Models\Robot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RecommendationController extends BaseRmsWebController
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'robot_id' => ['nullable', 'integer', 'exists:robots,id'],
            'status' => ['nullable', 'in:all,open,acknowledged,resolved,dismissed'],
            'priority' => ['nullable', 'string', 'max:32'],
        ]);
        $query = MaintenanceRecommendation::query()
            ->with(['robot', 'healthAssessment', 'maintenancePlan']);
        if (!empty($filter['robot_id'])) {
            $query->where('robot_id', $filter['robot_id']);
        }
        if (!empty($filter['status']) && $filter['status'] !== 'all') {
            $query->where('status', $filter['status']);
        }
        if (!empty($filter['priority']) && $filter['priority'] !== 'all') {
            $query->where('priority', $filter['priority']);
        }

        return view('rms.recommendations.index', [
            'title' => 'Maintenance Recommendations',
            'robots' => Robot::query()->orderBy('robot_code')->get(['id', 'robot_code', 'name']),
            'recommendations' => $query->orderByDesc('generated_at')
                ->paginate(20)->withQueryString(),
            'openCount' => MaintenanceRecommendation::query()->where('status', 'open')->count(),
            'filter' => $filter,
            'generationEnabled' => false,
            'note' => 'Recommendations are stored records, not automatically inferred from raw torque.',
        ]);
    }
}
