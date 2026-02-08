<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Sharaf;
use App\Models\SharafDefinition;
use App\Models\Census;
use App\Models\Miqaat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Get all events for the active miqaat only.
     * For reporting, use getAll() to get all events regardless of active status.
     */
    public function index(): JsonResponse
    {
        $events = Event::whereHas('miqaat', fn ($q) => $q->active())->get();

        return $this->jsonSuccessWithData($events);
    }

    /**
     * Get all events (for reporting - returns all events regardless of active status).
     */
    public function getAll(): JsonResponse
    {
        $events = Event::with('miqaat')->orderBy('miqaat_id')->orderBy('date')->get();

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

    /**
     * Get events by miqaat ID (for reporting - no active miqaat restriction).
     */
    public function byMiqaatId(string $miqaat_id): JsonResponse
    {
        $validator = Validator::make(['miqaat_id' => $miqaat_id], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid miqaat ID.',
                422
            );
        }

        $events = Event::where('miqaat_id', (int) $miqaat_id)
            ->with('miqaat')
            ->orderBy('date')
            ->get();

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

    /**
     * GET /api/events/{event_id}/sharaf-report-summary
     * Returns comprehensive summary statistics for sharafs in an event.
     */
    public function sharafReportSummary(string $event_id): JsonResponse
    {
        $validator = Validator::make(
            ['event_id' => $event_id],
            ['event_id' => ['required', 'integer']]
        );

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid event ID.',
                422
            );
        }

        $eventId = (int) $event_id;
        $event = Event::find($eventId);

        if (!$event) {
            return $this->jsonError('NOT_FOUND', 'Event not found.', 404);
        }

        // Ensure event belongs to active miqaat
        if (($err = $this->ensureActiveMiqaat($event->miqaat_id)) !== null) {
            return $err;
        }

        // Get all sharafs for this event, filtered by active miqaat
        $sharafs = Sharaf::whereHas('sharafDefinition', function ($q) use ($eventId) {
            $q->where('event_id', $eventId);
        })
            ->whereHas('sharafDefinition.event.miqaat', fn ($q) => $q->active())
            ->with(['sharafDefinition', 'sharafMembers'])
            ->get();

        // Get all sharaf definitions for this event
        $sharafDefinitions = SharafDefinition::where('event_id', $eventId)->get();

        // Collect all ITS numbers first for bulk census lookup
        $allItsNumbers = [];
        foreach ($sharafs as $sharaf) {
            $allItsNumbers[] = $sharaf->hof_its;
            foreach ($sharaf->sharafMembers as $member) {
                $allItsNumbers[] = $member->its_id;
            }
        }
        $allItsNumbers = array_unique($allItsNumbers);

        // Load all census records in one query
        $censusRecords = Census::whereIn('its_id', $allItsNumbers)
            ->get()
            ->keyBy('its_id');

        // Build summary by definition
        $summaryByDefinition = [];
        $overallSummary = [
            'total_sharafs' => 0,
            'total_members' => 0,
            'male_count' => 0,  
            'female_count' => 0,
        ];

        // Collect all ITS numbers with their sharaf and role information
        $itsToSharafs = [];

        foreach ($sharafDefinitions as $definition) {
            $definitionSharafs = $sharafs->where('sharaf_definition_id', $definition->id);
            
            $totalSharafs = $definitionSharafs->count();
            $totalMembers = 0;
            $maleCount = 0;
            $femaleCount = 0;

            foreach ($definitionSharafs as $sharaf) {
                // Count HOF
                $hofIts = $sharaf->hof_its;
                $totalMembers++;
                
                // Get HOF gender from census (using preloaded data)
                $hofCensus = $censusRecords->get($hofIts);
                if ($hofCensus) {
                    $gender = strtolower($hofCensus->gender ?? '');
                    if ($gender === 'male') {
                        $maleCount++;
                    } elseif ($gender === 'female') {
                        $femaleCount++;
                    }
                }

                // Count members (only members are tracked for clash detection)
                foreach ($sharaf->sharafMembers as $member) {
                    $totalMembers++;
                    $memberIts = $member->its_id;

                    // Track member for clash detection
                    if (!isset($itsToSharafs[$memberIts])) {
                        $itsToSharafs[$memberIts] = [];
                    }
                    $itsToSharafs[$memberIts][] = [
                        'sharaf_id' => $sharaf->id,
                        'sharaf_name' => $sharaf->name,
                        'sharaf_definition_id' => $definition->id,
                        'sharaf_definition_name' => $definition->name,
                        'role' => 'member',
                    ];

                    // Get member gender from census (using preloaded data)
                    $memberCensus = $censusRecords->get($memberIts);
                    if ($memberCensus) {
                        $gender = strtolower($memberCensus->gender ?? '');
                        if ($gender === 'male') {
                            $maleCount++;
                        } elseif ($gender === 'female') {
                            $femaleCount++;
                        }
                    }
                }
            }

            $summaryByDefinition[] = [
                'sharaf_definition_id' => $definition->id,
                'sharaf_definition_name' => $definition->name,
                'total_sharafs' => $totalSharafs,
                'total_members' => $totalMembers,
                'male_count' => $maleCount,
                'female_count' => $femaleCount,
            ];

            // Update overall summary
            $overallSummary['total_sharafs'] += $totalSharafs;
            $overallSummary['total_members'] += $totalMembers;
            $overallSummary['male_count'] += $maleCount;
            $overallSummary['female_count'] += $femaleCount;
        }

        // Detect clashes (ITS numbers appearing in multiple sharafs)
        $clashes = [];
        foreach ($itsToSharafs as $itsNo => $sharafEntries) {
            if (count($sharafEntries) > 1) {
                // This ITS appears in multiple sharafs - it's a clash
                $census = $censusRecords->get($itsNo);
                
                $clashes[] = [
                    'its_no' => $itsNo,
                    'name' => $census ? $census->name : null,
                    'gender' => $census ? $census->gender : null,
                    'sharafs' => $sharafEntries,
                ];
            }
        }

        $data = [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'summary_by_definition' => $summaryByDefinition,
            'overall_summary' => $overallSummary,
            'clashes' => $clashes,
        ];

        return $this->jsonSuccessWithData($data);
    }

    /**
     * GET /api/miqaats/{miqaat_id}/sharaf-report-summary-cross-events
     * Returns clash detection report for all sharafs across all events in a miqaat.
     * Identifies people who appear in multiple sharafs across different events.
     */
    public function sharafReportSummaryCrossEvents(string $miqaat_id): JsonResponse
    {
        $validator = Validator::make(
            ['miqaat_id' => $miqaat_id],
            ['miqaat_id' => ['required', 'integer']]
        );

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid miqaat ID.',
                422
            );
        }

        $miqaatId = (int) $miqaat_id;
        $miqaat = Miqaat::find($miqaatId);

        if (!$miqaat) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        // Ensure miqaat is active
        if (($err = $this->ensureActiveMiqaat($miqaatId)) !== null) {
            return $err;
        }

        // Get all sharafs for all events in this miqaat, filtered by active miqaat
        $sharafs = Sharaf::whereHas('sharafDefinition.event', function ($q) use ($miqaatId) {
            $q->where('miqaat_id', $miqaatId);
        })
            ->whereHas('sharafDefinition.event.miqaat', fn ($q) => $q->active())
            ->with([
                'sharafDefinition.event',
                'sharafMembers.sharafPosition' => function ($q) {
                    $q->orderBy('order');
                }
            ])
            ->get();

        // Collect all ITS numbers from sharaf_members (includes HOF since it's also in sharaf_members)
        $allItsNumbers = [];
        $itsToSharafs = []; // Track: its_no => [array of sharaf entries with event info]

        foreach ($sharafs as $sharaf) {
            $event = $sharaf->sharafDefinition->event;
            $eventId = $event->id;
            $eventName = $event->name;

            // Process all members from sharaf_members (including HOF)
            foreach ($sharaf->sharafMembers as $member) {
                $memberIts = $member->its_id;
                $allItsNumbers[] = $memberIts;

                // Track member with event information
                if (!isset($itsToSharafs[$memberIts])) {
                    $itsToSharafs[$memberIts] = [];
                }

                // Determine role - check if this member is the HOF
                // HOF can be identified by: matching hof_its OR having position name 'HOF'
                $isHof = ($member->its_id === $sharaf->hof_its) || 
                         ($member->sharafPosition && strtolower($member->sharafPosition->name ?? '') === 'hof');
                $role = $isHof ? 'HOF' : 'member';

                $itsToSharafs[$memberIts][] = [
                    'sharaf_id' => $sharaf->id,
                    'sharaf_name' => $sharaf->name,
                    'sharaf_definition_id' => $sharaf->sharaf_definition_id,
                    'sharaf_definition_name' => $sharaf->sharafDefinition->name,
                    'event_id' => $eventId,
                    'event_name' => $eventName,
                    'role' => $role,
                ];
            }
        }

        $allItsNumbers = array_unique($allItsNumbers);

        // Load all census records in one query
        $censusRecords = Census::whereIn('its_id', $allItsNumbers)
            ->get()
            ->keyBy('its_id');

        // Detect cross-event clashes
        // A clash exists when the same ITS appears in sharafs from DIFFERENT events
        $clashes = [];
        foreach ($itsToSharafs as $itsNo => $sharafEntries) {
            // Get unique event IDs for this ITS
            $eventIds = array_unique(array_column($sharafEntries, 'event_id'));

            // Only report clash if person appears in multiple DIFFERENT events
            if (count($eventIds) > 1) {
                $census = $censusRecords->get($itsNo);

                $clashes[] = [
                    'its_no' => $itsNo,
                    'name' => $census ? $census->name : null,
                    'gender' => $census ? $census->gender : null,
                    'sharafs' => $sharafEntries,
                ];
            }
        }

        $data = [
            'miqaat_id' => $miqaat->id,
            'miqaat_name' => $miqaat->name,
            'clashes' => $clashes,
        ];

        return $this->jsonSuccessWithData($data);
    }
}
