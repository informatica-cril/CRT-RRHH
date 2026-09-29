<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        $query = Zone::where('active', true);
        
        if ($request->has('postal_code')) {
            $query->whereJsonContains('postal_codes', $request->postal_code);
        }
        if ($request->has('municipality')) {
            $query->whereJsonContains('municipalities', $request->municipality);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string',
            'province' => 'nullable|string',
            'postal_codes' => 'nullable|array',
            'municipalities' => 'nullable|array',
            'active' => 'boolean',
        ]);

        $zone = Zone::create($data);
        return response()->json($zone, 201);
    }

    public function show($id)
    {
        $zone = Zone::with(['users' => function($q) {
            $q->wherePivot('valid_from', '<=', now()->toDateString())
              ->where(function($q2) {
                  $q2->whereNull('zone_worker.valid_to')
                     ->orWhere('zone_worker.valid_to', '>=', now()->toDateString());
              });
        }])->findOrFail($id);

        return response()->json($zone);
    }

    public function update(Request $request, $id)
    {
        $zone = Zone::findOrFail($id);
        $data = $request->validate([
            'name' => 'string|max:255',
            'type' => 'nullable|string',
            'province' => 'nullable|string',
            'postal_codes' => 'nullable|array',
            'municipalities' => 'nullable|array',
            'active' => 'boolean',
        ]);

        $zone->update($data);
        return response()->json($zone);
    }

    public function destroy($id)
    {
        $zone = Zone::findOrFail($id);
        $zone->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function assignWorker(Request $request, $id)
    {
        $zone = Zone::findOrFail($id);
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        // Close any other open-ended assignment of the same zone type for this
        // worker, so the new assignment unambiguously replaces it instead of
        // both being "active" at once (which broke worker-side zone lookups).
        $sameTypeZoneIds = Zone::where('type', $zone->type)->pluck('id');
        \DB::table('zone_worker')
            ->where('user_id', $request->user_id)
            ->whereIn('zone_id', $sameTypeZoneIds)
            ->whereNull('valid_to')
            ->where('zone_id', '!=', $zone->id)
            ->update(['valid_to' => \Carbon\Carbon::parse($request->valid_from)->subDay()->toDateString()]);

        $zone->users()->attach($request->user_id, [
            'valid_from' => $request->valid_from,
            'valid_to' => $request->valid_to,
        ]);

        return response()->json(['message' => 'Worker assigned successfully']);
    }

    public function removeWorker(Request $request, $id)
    {
        $zone = Zone::findOrFail($id);
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        \DB::table('zone_worker')
            ->where('zone_id', $zone->id)
            ->where('user_id', $request->user_id)
            ->whereNull('valid_to')
            ->update(['valid_to' => now()->toDateString()]);

        return response()->json(['message' => 'Worker removed successfully']);
    }
}
