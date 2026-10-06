<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmbulatoryCenter;
use Illuminate\Http\Request;

class AmbulatoryCenterController extends Controller
{
    /**
     * Get all active ambulatory centers and their work locations.
     */
    public function index()
    {
        $centers = AmbulatoryCenter::with('workLocations')->where('active', true)->get();
        return response()->json($centers);
    }

    /**
     * Assign a center to a user.
     */
    public function assignToUser(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date',
        ]);

        $center = AmbulatoryCenter::findOrFail($id);
        
        $center->users()->syncWithoutDetaching([
            $request->user_id => [
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
            ]
        ]);

        // Els fitxatges pendents que havien quedat fora de zona es tornen a comprovar amb el centre nou.
        $reavaluats = app(\App\Services\ReavaluaZona::class)
            ->perUsuari(\App\Models\User::findOrFail($request->user_id), $request->user()->id);

        return response()->json(['message' => 'Center assigned successfully', 'fitxatges_reavaluats' => $reavaluats]);
    }

    /**
     * Remove a center from a user.
     */
    public function removeWorker(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $center = AmbulatoryCenter::findOrFail($id);
        $center->users()->detach($request->user_id);

        return response()->json(['message' => 'Worker removed from center successfully']);
    }
}
