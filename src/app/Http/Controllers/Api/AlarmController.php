<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Robot;
use App\Models\RobotAlarmEvent;
use App\Models\RobotAlarmSnapshot;
use App\Services\Rms\AlarmSnapshotProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlarmController extends BaseRmsController
{
    public function events(Request $request): JsonResponse
    {
        $filters=$this->queryPeriod($request);
        $other=$request->validate([
            'robot_id'=>['sometimes','integer','exists:robots,id'],
            'status'=>['sometimes','in:active,cleared,unknown'],
            'severity'=>['sometimes','string','max:32'],
        ]);
        $query=RobotAlarmEvent::query()->with('robot:id,robot_code,name');
        if(!empty($other['robot_id'])) $query->where('robot_id',$other['robot_id']);
        if(!empty($other['status'])) $query->where('status',$other['status']);
        if(!empty($other['severity'])) $query->where('severity',$other['severity']);
        $this->applyPeriod($query,$filters,'started_at');
        return response()->json($query->orderByDesc('started_at')->paginate($filters['per_page']??25));
    }

    public function snapshots(Request $request, Robot $robot): JsonResponse
    {
        $filters=$this->queryPeriod($request);
        $query=$robot->alarmSnapshots();
        $this->applyPeriod($query->getQuery(),$filters,'sampled_at');
        return response()->json($query->orderByDesc('sampled_at')->paginate($filters['per_page']??25));
    }

    public function latestSnapshot(Robot $robot): JsonResponse
    {
        return response()->json(['data'=>$robot->alarmSnapshots()->orderByDesc('sampled_at')->first()]);
    }

    public function storeSnapshot(Request $request, AlarmSnapshotProcessor $processor): JsonResponse
    {
        $data=$request->validate([
            'robot_id'=>['required','integer','exists:robots,id'],
            'sampled_at'=>['required','date'],
            'read_ok'=>['required','boolean'],
            'status_code'=>['nullable','integer'],
            'status_message'=>['nullable','string'],
            'sdk_text'=>['nullable','string','max:65535'],
            // Incoming client-derived fields are deliberately not trusted.
            'active_alarm_count'=>['nullable','integer','min:0'],
            'parsed_alarms'=>['nullable','array'],
        ]);
        unset($data['active_alarm_count'],$data['parsed_alarms']);
        if(!$data['read_ok']) $data['sdk_text']=null;
        $key=['robot_id'=>$data['robot_id'],'sampled_at'=>$data['sampled_at']];
        $snapshot=RobotAlarmSnapshot::firstOrCreate($key,$data);
        $result=$snapshot->wasRecentlyCreated ? $processor->process($snapshot) : ['state'=>'duplicate'];
        return response()->json([
            'data'=>$snapshot->fresh(),'created'=>$snapshot->wasRecentlyCreated,'parsing'=>$result,
        ],$snapshot->wasRecentlyCreated?201:200);
    }
}
