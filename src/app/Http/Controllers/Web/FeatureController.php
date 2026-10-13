<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Contracts\View\View;

class FeatureController extends BaseRmsWebController
{
    public function index(): View
    {
        // Project plan status, not claims of deployed features.
        $features = [
            ['name' => 'YMConnect connection', 'stage' => 'poc_tested'],
            ['name' => 'Position PULSE monitoring', 'stage' => 'poc_tested'],
            ['name' => 'Torque monitoring', 'stage' => 'poc_tested'],
            ['name' => 'Active alarm reading', 'stage' => 'partially_tested'],
            ['name' => 'Operating hours reading', 'stage' => 'sdk_supported'],
            ['name' => 'Controller status', 'stage' => 'sdk_supported'],
            ['name' => 'Job monitoring', 'stage' => 'sdk_supported'],
            ['name' => 'I/O monitoring', 'stage' => 'sdk_supported'],
            ['name' => 'Multi-robot overview', 'stage' => 'planned'],
            ['name' => 'Preventive maintenance calendar & journal', 'stage' => 'schema_prepared'],
            ['name' => 'Maintenance recommendations', 'stage' => 'schema_prepared'],
            ['name' => 'Email notifications', 'stage' => 'planned'],
            ['name' => 'Downtime & utilization', 'stage' => 'planned'],
            ['name' => 'Hardware life estimation', 'stage' => 'needs_investigation'],
        ];

        return view('rms.features.index', [
            'title' => 'Development Status',
            'features' => $features,
            'note' => 'Stages indicate scope/POC readiness. They do not mean end-to-end production deployment.',
        ]);
    }
}
