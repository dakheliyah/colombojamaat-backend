<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Get all events for the active miqaat only.
     */
    public function index(): JsonResponse
    {
        $events = Event::whereHas('miqaat', fn ($q) => $q->active())->get();

        return $this->jsonSuccessWithData($events);
    }

    /**
     * Get events by miqaat ID. miqaat_id must be the active miqaat.
     */
    public function byMiqaat(string $miqaat_id): JsonResponse
    {
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }
        $events = Event::where('miqaat_id', $miqaat_id)->get();

        return $this->jsonSuccessWithData($events);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $event = Event::create([
            'miqaat_id' => $request->input('miqaat_id'),
            'date' => $request->input('date'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return $this->jsonSuccessWithData($event, 201);
    }
}
