<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkLocation;
use Illuminate\Http\Request;

class WorkLocationController extends Controller
{
    public function index()
    {
        return response()->json(WorkLocation::where('active', true)->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius' => 'nullable|integer',
            'active' => 'boolean',
        ]);

        $location = WorkLocation::create($data);
        return response()->json($location, 201);
    }

    public function show($id)
    {
        $location = WorkLocation::with(['users' => function($q) {
            $q->wherePivot('valid_from', '<=', now()->toDateString())
              ->where(function($q2) {
                  $q2->whereNull('location_worker.valid_to')
                     ->orWhere('location_worker.valid_to', '>=', now()->toDateString());
              });
        }])->findOrFail($id);

        return response()->json($location);
    }

    public function update(Request $request, $id)
    {
        $location = WorkLocation::findOrFail($id);
        $data = $request->validate([
            'name' => 'string|max:255',
            'address' => 'nullable|string',
            'lat' => 'numeric',
            'lng' => 'numeric',
            'radius' => 'nullable|integer',
            'active' => 'boolean',
        ]);

        $location->update($data);
        return response()->json($location);
    }

    public function destroy($id)
    {
        $location = WorkLocation::findOrFail($id);
        $location->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function assignWorker(Request $request, $id)
    {
        $location = WorkLocation::findOrFail($id);
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $location->users()->attach($request->user_id, [
            'valid_from' => $request->valid_from,
            'valid_to' => $request->valid_to,
        ]);

        return response()->json(['message' => 'Worker assigned successfully']);
    }

    public function removeWorker(Request $request, $id)
    {
        $location = WorkLocation::findOrFail($id);
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $location->users()->detach($request->user_id);

        return response()->json(['message' => 'Worker removed successfully']);
    }
}
