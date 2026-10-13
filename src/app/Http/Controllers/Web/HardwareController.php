<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Contracts\View\View;

class HardwareController extends BaseRmsWebController
{
    public function index(): View
    {
        return view('rms.hardware.index', [
            'title' => 'Hardware Insights',
            'estimationAvailable' => false,
            'estimatedRemainingLife' => null,
            'researchItems' => [
                'Verify SDK diagnostic fields and engineering units.',
                'Collect and validate long-term condition history.',
                'Define baseline, failure criteria and maintenance review.',
            ],
            'note' => 'Hardware life prediction is under investigation, not a deployed feature.',
        ]);
    }
}
