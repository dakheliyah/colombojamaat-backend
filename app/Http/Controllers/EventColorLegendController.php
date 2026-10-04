<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventColorLegend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EventColorLegendController extends Controller
{
    public function index(string $event_id): JsonResponse
    {
        $event = Event::find($event_id);
        if (! $event) {
            return $this->jsonError('NOT_FOUND', 'Event not found.', 404);
        }

        $legends = EventColorLegend::where('event_id', $event_id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return $this->jsonSuccessWithData($legends);
    }

    public function store(Request $request, string $event_id): JsonResponse
    {
        $event = Event::find($event_id);
        if (! $event) {
            return $this->jsonError('NOT_FOUND', 'Event not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique('event_color_legends', 'label')->where('event_id', $event_id),
            ],
            'hex_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $maxOrder = (int) EventColorLegend::where('event_id', $event_id)->max('sort_order');

        $legend = EventColorLegend::create([
            'event_id' => (int) $event_id,
            'label' => $request->input('label'),
            'hex_color' => strtoupper($request->input('hex_color')),
            'sort_order' => $request->input('sort_order', $maxOrder + 1),
        ]);

        return $this->jsonSuccessWithData($legend, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $legend = EventColorLegend::find($id);
        if (! $legend) {
            return $this->jsonError('NOT_FOUND', 'Color legend not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'label' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('event_color_legends', 'label')
                    ->where('event_id', $legend->event_id)
                    ->ignore($id),
            ],
            'hex_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $data = $request->only(['label', 'hex_color', 'sort_order']);
        if (array_key_exists('hex_color', $data) && is_string($data['hex_color'])) {
            $data['hex_color'] = strtoupper($data['hex_color']);
        }

        $legend->update($data);

        return $this->jsonSuccessWithData($legend->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $legend = EventColorLegend::find($id);
        if (! $legend) {
            return $this->jsonError('NOT_FOUND', 'Color legend not found.', 404);
        }

        $legend->delete();

        return $this->jsonSuccess();
    }
}
