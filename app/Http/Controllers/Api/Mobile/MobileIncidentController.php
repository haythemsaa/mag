<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccidentResource;
use App\Models\Accident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * @group Mobile API - Incidents
 *
 * APIs for mobile incident reporting (accidents, breakdowns, etc.)
 */
class MobileIncidentController extends Controller
{
    /**
     * Report a new incident
     *
     * Allows drivers to report accidents or breakdowns from the mobile app.
     * Supports uploading photos and location data.
     *
     * @authenticated
     *
     * @bodyParam type string required Incident type (accident, breakdown, vandalism, other). Example: accident
     * @bodyParam description string required Incident description. Example: Minor collision with parked car
     * @bodyParam occurred_at datetime required When the incident occurred. Example: 2025-11-16 10:30:00
     * @bodyParam latitude numeric Incident location latitude. Example: 48.8566
     * @bodyParam longitude numeric Incident location longitude. Example: 2.3522
     * @bodyParam address string Incident location address. Example: 123 Rue de Paris, Paris
     * @bodyParam severity string Severity level (minor, moderate, severe, total_loss). Example: minor
     * @bodyParam third_party_involved boolean Whether a third party was involved. Example: true
     * @bodyParam third_party_name string Third party name. Example: Jean Martin
     * @bodyParam third_party_phone string Third party phone. Example: +33612345678
     * @bodyParam third_party_insurance string Third party insurance company. Example: AXA
     * @bodyParam witnesses string Witness information. Example: Marie Dupont, +33698765432
     * @bodyParam photos array Photos of the incident. Example: [file1, file2]
     * @bodyParam photos.* file Photo file (jpg, png, max 10MB each). Example: incident_photo.jpg
     *
     * @response 201 {
     *   "message": "Incident reported successfully",
     *   "data": {
     *     "id": 123,
     *     "accident_number": "ACC-2025-000123",
     *     "type": "accident",
     *     "occurred_at": "2025-11-16 10:30:00",
     *     "status": "declared"
     *   }
     * }
     */
    public function report(Request $request): JsonResponse
    {
        $vehicle = $request->user()->assignedVehicle;

        if (!$vehicle) {
            return response()->json([
                'message' => 'No vehicle assigned to this driver',
            ], 404);
        }

        $validated = $request->validate([
            'type' => 'required|in:accident,breakdown,vandalism,other',
            'description' => 'required|string|max:2000',
            'occurred_at' => 'required|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'severity' => 'nullable|in:minor,moderate,severe,total_loss',
            'third_party_involved' => 'nullable|boolean',
            'third_party_name' => 'nullable|string|max:255',
            'third_party_phone' => 'nullable|string|max:20',
            'third_party_insurance' => 'nullable|string|max:255',
            'third_party_vehicle_registration' => 'nullable|string|max:20',
            'witnesses' => 'nullable|string|max:1000',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'file|image|mimes:jpeg,png,jpg|max:10240', // 10MB max per photo
        ]);

        // Create the accident/incident
        $incident = Accident::create([
            'organization_id' => $request->user()->organization_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $request->user()->id,
            'accident_date' => $validated['occurred_at'],
            'location' => $validated['address'] ?? 'N/A',
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'description' => $validated['description'],
            'severity' => $validated['severity'] ?? 'minor',
            'responsibility' => 'unknown', // Will be determined later
            'status' => 'declared',
            'third_party_involved' => $validated['third_party_involved'] ?? false,
            'third_party_name' => $validated['third_party_name'] ?? null,
            'third_party_phone' => $validated['third_party_phone'] ?? null,
            'third_party_insurance' => $validated['third_party_insurance'] ?? null,
            'third_party_vehicle_registration' => $validated['third_party_vehicle_registration'] ?? null,
            'witnesses' => $validated['witnesses'] ?? null,
        ]);

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('accidents/' . $incident->id, 'public');

                $incident->photos()->create([
                    'file_path' => $path,
                    'file_name' => $photo->getClientOriginalName(),
                    'file_size' => $photo->getSize(),
                    'mime_type' => $photo->getMimeType(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Incident reported successfully',
            'data' => [
                'id' => $incident->id,
                'accident_number' => $incident->accident_number,
                'type' => $validated['type'],
                'occurred_at' => $incident->accident_date,
                'status' => $incident->status,
                'photos_uploaded' => $incident->photos()->count(),
            ],
        ], 201);
    }

    /**
     * List driver's reported incidents
     *
     * Returns all incidents reported by the authenticated driver.
     *
     * @authenticated
     *
     * @queryParam status string Filter by status. Example: declared
     * @queryParam limit integer Number of incidents to return. Example: 20
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 123,
     *       "accident_number": "ACC-2025-000123",
     *       "accident_date": "2025-11-16",
     *       "severity": "minor",
     *       "status": "declared",
     *       "description": "Minor collision"
     *     }
     *   ]
     * }
     */
    public function list(Request $request): AnonymousResourceCollection
    {
        $query = Accident::where('driver_id', $request->user()->id)
            ->orderBy('accident_date', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $limit = min($request->input('limit', 20), 100);
        $incidents = $query->limit($limit)->get();

        return AccidentResource::collection($incidents);
    }

    /**
     * Upload additional photos for an incident
     *
     * Allows adding more photos to an already reported incident.
     *
     * @authenticated
     *
     * @urlParam accident_id integer required Incident ID. Example: 123
     * @bodyParam photos array required Photos to upload. Example: [file1, file2]
     * @bodyParam photos.* file Photo file (jpg, png, max 10MB each). Example: photo.jpg
     *
     * @response 200 {
     *   "message": "Photos uploaded successfully",
     *   "data": {
     *     "total_photos": 5,
     *     "newly_added": 2
     *   }
     * }
     */
    public function uploadPhotos(Request $request, int $accidentId): JsonResponse
    {
        $accident = Accident::where('driver_id', $request->user()->id)
            ->findOrFail($accidentId);

        $validated = $request->validate([
            'photos' => 'required|array|max:10',
            'photos.*' => 'file|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $uploadedCount = 0;

        foreach ($request->file('photos') as $photo) {
            $path = $photo->store('accidents/' . $accident->id, 'public');

            $accident->photos()->create([
                'file_path' => $path,
                'file_name' => $photo->getClientOriginalName(),
                'file_size' => $photo->getSize(),
                'mime_type' => $photo->getMimeType(),
            ]);

            $uploadedCount++;
        }

        return response()->json([
            'message' => 'Photos uploaded successfully',
            'data' => [
                'total_photos' => $accident->photos()->count(),
                'newly_added' => $uploadedCount,
            ],
        ]);
    }

    /**
     * Delete a photo from an incident
     *
     * @authenticated
     *
     * @urlParam accident_id integer required Incident ID. Example: 123
     * @urlParam photo_id integer required Photo ID to delete. Example: 456
     *
     * @response 200 {
     *   "message": "Photo deleted successfully"
     * }
     */
    public function deletePhoto(Request $request, int $accidentId, int $photoId): JsonResponse
    {
        $accident = Accident::where('driver_id', $request->user()->id)
            ->findOrFail($accidentId);

        $photo = $accident->photos()->findOrFail($photoId);

        // Delete file from storage
        if (Storage::disk('public')->exists($photo->file_path)) {
            Storage::disk('public')->delete($photo->file_path);
        }

        // Delete record
        $photo->delete();

        return response()->json([
            'message' => 'Photo deleted successfully',
        ]);
    }
}
