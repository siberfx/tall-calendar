<?php

namespace TeamNiftyGmbH\Calendar\Livewire;

use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use TeamNiftyGmbH\Calendar\Models\Calendar;
use TeamNiftyGmbH\Calendar\Models\Pivot\Inviteable;
use WireUi\Traits\Actions;

class CalendarComponent extends Component
{
    use Actions;

    #[Locked]
    public array $allCalendars = [];

    #[Locked]
    public array $selectableCalendars = [];

    public array $calendarEvent = [];

    #[Locked]
    public array $oldCalendarEvent = [];

    #[Locked]
    public bool $calendarEventWasRepeatable = false;

    #[Locked]
    public bool $showCalendars = true;

    #[Locked]
    public bool $showInvites = true;

    #[Locked]
    public array $calendarPeriod = [
        'start' => null,
        'end' => null,
    ];

    public array $validationErrors = [];

    public string $confirmSave = 'future';

    public string $confirmDelete = 'this';

    protected Collection $sharedWithMe;

    protected Collection $myCalendars;

    public function mount(): void
    {
        $this->calendarEvent = [
            'calendar_id' => null,
            'model_id' => null,
            'start' => now(),
            'end' => now(),
        ];
    }

    public function getRules(): array
    {
        return [
            'title' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'description' => 'nullable|string',

            'has_repeats' => 'boolean',
            'interval' => 'exclude_unless:has_repeats,true|required_if:has_repeats,true|integer|min:1',
            'unit' => 'exclude_unless:has_repeats,true|required_if:has_repeats,true|in:days,weeks,months,years',
            'weekdays' => 'exclude_unless:unit,weeks|required_if:unit,weeks|array',
            'weekdays.*' => 'required|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'monthly' => 'exclude_unless:unit,months|required_if:unit,months|in:day,first,second,third,fourth,last',
            'repeat_radio' => 'exclude_unless:has_repeats,true|nullable|in:repeat_end,recurrences',
            'repeat_end' => [
                'exclude_unless:repeat_radio,repeat_end',
                'required_if:repeat_radio,repeat_end',
                'date',
                'after:start',
            ],
            'recurrences' => [
                'exclude_unless:repeat_radio,recurrences',
                'required_if:repeat_radio,recurrences',
                'integer',
                'min:1',
            ],

            'has_taken_place' => 'boolean',
        ];
    }

    public function render(): Factory|Application|View
    {
        return view('tall-calendar::livewire.calendar.calendar');
    }

    public function getEvents(array $info, array $calendarAttributes): array
    {
        $this->calendarPeriod = [
            'start' => Carbon::parse($info['startStr'])->toDateTimeString(),
            'end' => Carbon::parse($info['endStr'])->toDateTimeString(),
        ];

        $calendar = app(config('tall-calendar.models.calendar'))->query()->find($calendarAttributes['id']);

        $calendarEvents = $calendar->calendarEvents()
            ->whereNull('repeat')
            ->where(function ($query) use ($info) {
                $query->whereBetween('start', [
                    Carbon::parse($info['start']),
                    Carbon::parse($info['end']),
                ])
                    ->orWhereBetween('end', [
                        Carbon::parse($info['start']),
                        Carbon::parse($info['end']),
                    ]);
            })
            ->with('invited', fn ($query) => $query->withPivot('status'))
            ->get()
            ->merge(
                $calendar->invitesCalendarEvents()
                    ->addSelect('calendar_events.*')
                    ->addSelect('inviteables.status')
                    ->addSelect('inviteables.model_calendar_id AS calendar_id')
                    ->whereIn('inviteables.status', ['accepted', 'maybe'])
                    ->get()
                    ->each(fn ($event) => $event->is_invited = true)
            );

        return $this->calculateRepeatableEvents($calendar, $calendarEvents)
            ->map(function ($event) use ($calendarAttributes, $calendar) {
                $invited = $this->getInvited($event);

                return $event->toCalendarEventObject([
                    'is_editable' => $calendarAttributes['permission'] !== 'reader',
                    'invited' => $invited,
                    'is_repeatable' => $calendar->has_repeatable_events ?? false,
                    'has_repeats' => ! is_null($event->repeat),
                ]);
            })
            ?->toArray();
    }

    public function getInvited(Model $event): array
    {
        return $event->invitedModels()
            ->map(
                function (Model $inviteable) {
                    return [
                        'id' => $inviteable->id,
                        'label' => $inviteable->getLabel(),
                        'pivot' => $inviteable->pivot,
                    ];
                }
            )
            ->toArray();
    }

    public function getInvites(): array
    {
        return auth()->user()
            ->invites()
            ->with('calendarEvent:id,start,end,title,is_all_day,calendar_id')
            ->get()
            ->toArray();
    }

    public function getCalendarGroups(): array
    {
        return [
            'my' => __('My Calendars'),
            'shared' => __('Shared with me'),
            'public' => __('Public'),
            'other' => __('Other'),
        ];
    }

    public function getCalendars(): array
    {
        $this->allCalendars = array_merge(
            $this->getMyCalendars()->toArray(),
            $this->getSharedWithMeCalendars()->toArray(),
            $this->getPublicCalendars()->toArray(),
            $this->getOtherCalendars()->toArray(),
        );

        $this->selectableCalendars = collect($this->allCalendars)
            ->where('resourceEditable', true)
            ->map(function ($calendar) {
                if ($parentId = data_get($calendar, 'parentId')) {
                    $calendar['name'] = app(config('tall-calendar.models.calendar'))
                        ->query()
                        ->whereKey($parentId)
                        ->value('name') . ' -> ' . $calendar['name'] ?? '';
                }

                return $calendar;
            })
            ->all();

        return $this->allCalendars;
    }

    public function getMyCalendars(): Collection
    {
        return $this->myCalendars = auth()->user()
            ->calendars()
            ->withPivot('permission')
            ->wherePivot('permission', 'owner')
            ->withCount('calendarables')
            ->get()
            ->toFlatTree()
            ->map(function (Calendar $calendar) {
                return $calendar->toCalendarObject(
                    [
                        'permission' => $calendar['pivot']['permission'],
                        'group' => 'my',
                    ]
                );
            });
    }

    public function getSharedWithMeCalendars(): Collection
    {
        return $this->sharedWithMe = auth()->user()
            ->calendars()
            ->withPivot('permission')
            ->wherePivot('permission', '!=', 'owner')
            ->get()
            ->toFlatTree()
            ->map(function (Calendar $calendar) {
                return $calendar->toCalendarObject(
                    [
                        'permission' => $calendar['pivot']['permission'],
                        'resourceEditable' => $calendar['pivot']['permission'] !== 'reader',
                        'group' => 'shared',
                    ]
                );
            });
    }

    public function getPublicCalendars(): Collection
    {
        return app(config('tall-calendar.models.calendar'))->query()
            ->where('is_public', true)
            ->whereNotIn('id', $this->myCalendars->pluck('id'))
            ->whereNotIn('id', $this->sharedWithMe->pluck('id'))
            ->get()
            ->toFlatTree()
            ->map(function (Calendar $calendar) {
                return $calendar->toCalendarObject([
                    'permission' => 'reader',
                    'group' => 'public',
                    'resourceEditable' => false,
                ]);
            });
    }

    public function getOtherCalendars(): Collection
    {
        return collect();
    }

    public function getViews(): array
    {
        return [
            'dayGridMonth',
        ];
    }

    public function getConfig(): array
    {
        return [
            'locale' => app()->getLocale(),
            'firstDay' => Carbon::getWeekStartsAt(),
            'height' => 'auto',
            'views' => $this->getViews(),
            'headerToolbar' => [
                'end' => 'prev,next today',
                'left' => 'title',
                'center' => 'timeGridDay,timeGridWeek,dayGridMonth',
            ],
            'nowIndicator' => true,
            'buttonText' => [
                'today' => __('Today'),
                'month' => __('Month'),
                'week' => __('Week'),
                'day' => __('Day'),
            ],
        ];
    }

    public function getCalendarEventsBeingListenedFor(): array
    {
        return array_intersect_key(
            method_exists(parent::class, 'getEventsBeingListenedFor')
                ? parent::getEventsBeingListenedFor()
                : array_keys(parent::getListeners()),
            [
                'select',
                'unselect',
                'dateClick',
                'eventClick',
                'eventMouseEnter',
                'eventMouseLeave',
                'eventDragStart',
                'eventDragStop',
                'eventDrop',
                'eventResizeStart',
                'eventResizeStop',
                'eventResize',
                'eventReceive',
                'eventLeave',
                'eventAdd',
                'eventChange',
                'eventRemove',
                'drop',
                'eventsSet',
            ]
        );
    }

    #[Renderless]
    public function saveEvent(array $attributes): array|false
    {
        if (! data_get($attributes, 'has_repeats')) {
            $attributes = array_merge($attributes, [
                'interval' => null,
                'unit' => null,
                'weekdays' => [],
                'monthly' => null,
                'repeat' => null,
                'repeat_radio' => null,
                'repeat_end' => null,
                'recurrences' => null,
            ]);
        }

        $validator = Validator::make($attributes, $this->getRules());
        if ($validator->fails()) {
            return false;
        }

        $event = app(config('tall-calendar.models.calendar_event'))->query()
            ->with('invites')
            ->when(
                ($attributes['id'] ?? null) === null || is_numeric($attributes['id'] ?? null),
                fn ($query) => $query->whereKey($attributes['id'] ?? null),
                fn ($query) => $query->where('ulid', $attributes['id'])
            )
            ->firstOrNew();

        $originalEvent = null;
        if ($this->calendarEventWasRepeatable && $this->confirmSave !== 'all') {
            if ($this->confirmSave === 'this' && $attributes['has_repeats'] ?? false) {
                $this->confirmSave = 'future';
            }

            // If confirm option is "this" or "selected and future", create new event with given values
            if (in_array($this->confirmSave, ['this', 'future'])) {
                $originalEvent = $event;
                $event = $event->replicate(['ulid']);
            }

            // If confirm option is "this", add current start date to excluded dates
            // If confirm option is "selected and future", set repeat_end of original event to current start date
            if ($this->confirmSave === 'this') {
                $originalEvent->excluded = array_merge(
                    $originalEvent->excluded ?: [],
                    [Carbon::parse($this->oldCalendarEvent['start'])->format('Y-m-d H:i:s')]
                );
                $originalEvent->save();
            } elseif ($this->confirmSave === 'future') {
                $attributes['excluded'] = null;

                $originalEvent->repeat_end = Carbon::parse($this->oldCalendarEvent['start'])
                    ->subSecond()
                    ->toDateTimeString();
                $originalEvent->save();
            }
        }

        if ($this->confirmSave === 'all') {
            $attributes['excluded'] = [];
        }

        $event->fromCalendarEventObject($attributes);
        $event->save();

        $invites = collect($attributes['invited'] ?? [])
            ->map(function ($invite) {
                return [
                    'id' => $invite['id'] ?? null,
                    'is_selected' => $invite['isSelected'] ?? false,
                    'inviteable_id' => $invite['id'],
                    'inviteable_type' => $invite['type'] ?? auth()->user()->getMorphClass(),
                    'email' => $invite['email'] ?? $invite['description'] ?? null,
                    'pivot' => $invite['pivot'] ?? [],
                ];
            });

        $event->invites()
            ->whereNotIn('id', $invites->where('is_selected', false)->pluck('id'))
            ->delete();

        foreach ($invites as $invite) {
            $event->invites()->updateOrCreate(
                [
                    'inviteable_id' => $invite['inviteable_id'],
                    'inviteable_type' => $invite['inviteable_type'],
                ],
                $invite['pivot'],
            );
        }

        $calendarEvents = collect();

        foreach (collect([$event, $originalEvent])->filter() as $calendarEvent) {
            foreach ($this->calculateRepetitionsFromEvent($calendarEvent->toArray()) as $repetition) {
                $calendarEvents->push($repetition);
            }
        }

        return $calendarEvents
            ->map(fn ($event) => $event->toCalendarEventObject([
                'is_editable' => $attributes['is_editable'] ?? false,
                'is_repeatable' => $attributes['is_repeatable'] ?? false,
                'has_repeats' => ! is_null($event->repeat),
            ]))
            ->toArray();
    }

    public function deleteEvent(array $attributes): array|false
    {
        $event = app(config('tall-calendar.models.calendar_event'))->query()
            ->when(
                ($attributes['id'] ?? null) === null || is_numeric($attributes['id'] ?? null),
                fn ($query) => $query->whereKey($attributes['id'] ?? null),
                fn ($query) => $query->where('ulid', $attributes['id'])
            )
            ->firstOrFail();

        if (! $event->repeat) {
            $this->confirmDelete = 'all';
        }

        $result = match ($this->confirmDelete) {
            'this' => $event->fill([
                'excluded' => array_merge(
                    $event->excluded ?: [],
                    [Carbon::parse($this->oldCalendarEvent['start'])->format('Y-m-d H:i:s')]
                ),
            ])->save(),
            'future' => $event->fill([
                'repeat_end' => Carbon::parse($this->oldCalendarEvent['start'])
                    ->subSecond()
                    ->toDateTimeString(),
            ])->save(),
            default => $event->delete()
        };

        if (! $result) {
            return $result;
        }

        return [
            'id' => $event->id,
            'confirmOption' => $this->confirmDelete,
            'repetition' => $attributes['repetition'] ?? null,
        ];
    }

    public function inviteStatus(Inviteable $event, string $status, int $calendarId): void
    {
        $event->status = $status;
        $event->model_calendar_id = $calendarId;
        $event->save();

        $this->skipRender();
    }

    public function isCalendarEventRepeatable(int|string $calendarId): bool
    {
        return (bool) app(config('tall-calendar.models.calendar'))->query()
            ->whereKey($calendarId)
            ->value('has_repeatable_events');
    }

    #[Renderless]
    public function updateSelectableCalendars(array $calendar): void
    {
        $index = collect($this->selectableCalendars)->search(fn ($item) => $item['id'] === data_get($calendar, 'id'));
        if ($parentId = data_get($calendar, 'parentId')) {
            $calendar['name'] = app(config('tall-calendar.models.calendar'))
                ->query()
                ->whereKey($parentId)
                ->value('name') . ' -> ' . $calendar['name'] ?? '';
        }

        if ($index === false) {
            $this->selectableCalendars[] = $calendar;
        } else {
            $this->selectableCalendars[$index] = $calendar;
        }
    }

    #[Renderless]
    public function removeSelectableCalendar(array $calendar): void
    {
        $this->selectableCalendars = collect($this->selectableCalendars)
            ->reject(fn ($item) => $item['id'] === data_get($calendar, 'id'))
            ->all();
    }

    #[Renderless]
    public function showModal(): void
    {
        $this->js(
            <<<'JS'
               $openModal('calendar-event-modal');
            JS
        );
    }

    #[Renderless]
    public function onDateClick(array $eventInfo, ?array $calendar = null): void
    {
        if (! $calendar && $this->selectableCalendars) {
            $calendar = reset($this->selectableCalendars);
        }

        if (data_get($calendar, 'resourceEditable', false)) {
            $start = Carbon::parse($eventInfo['dateStr']);

            if ($start->format('H:i:s') === '00:00:00'
                && data_get($eventInfo, 'view.type') === 'dayGridMonth'
            ) {
                $now = now()->timezone(data_get($eventInfo, 'view.dateEnv.timeZone'));
                $start->timezone(data_get($eventInfo, 'view.dateEnv.timeZone'))
                    ->setHour($now->hour)
                    ->setMinute(now()->ceilMinute(15)->minute);
            }

            $this->onEventClick([
                'event' => [
                    'start' => $start->toDateTimeString(),
                    'end' => $start->addMinutes(15)->toDateTimeString(),
                    'allDay' => data_get($eventInfo, 'view.type') !== 'dayGridMonth'
                        ? data_get($eventInfo, 'allDay', false)
                        : false,
                    'calendar_id' => $calendar['id'] ?? null,
                    'model_type' => $calendar['modelType'] ?? null,
                    'model_id' => null,
                    'has_taken_place' => false,
                    'is_editable' => $calendar['resourceEditable'] ?? false,
                    'is_repeatable' => $calendar['hasRepeatableEvents'] ?? false,
                    'invited' => [],
                ],
            ]);
        }
    }

    #[Renderless]
    public function onEventClick(array $eventInfo): void
    {
        $this->calendarEvent = array_merge(
            [
                'interval' => null,
                'unit' => 'days',
                'weekdays' => [],
                'monthly' => null,
                'repeat_radio' => null,
                'repeat_end' => null,
                'recurrences' => null,
                'has_repeats' => false,
            ],
            Arr::pull($eventInfo['event'], 'extendedProps', []),
            $eventInfo['event']
        );

        if (data_get($this->calendarEvent, 'id')) {
            $explodedId = explode('|', $this->calendarEvent['id']);
            $this->calendarEvent['id'] = $explodedId[0];
            $this->calendarEvent['repetition'] = $explodedId[1] ?? null;
        }

        $this->oldCalendarEvent = $this->calendarEvent;

        $this->calendarEventWasRepeatable = $this->calendarEvent['has_repeats'] ?? false;
        $this->confirmSave = 'future';
        $this->confirmDelete = 'this';

        $this->showModal();
    }

    #[Renderless]
    public function onEventDragStart(array $eventInfo): void {}

    #[Renderless]
    public function onEventDragStop(array $eventInfo): void {}

    #[Renderless]
    public function onEventDrop(array $eventInfo): void
    {
        $event = array_merge(
            Arr::pull($eventInfo['event'], 'extendedProps', []),
            $eventInfo['event'],
            [
                'has_repeats' => false,
            ]
        );
        $event['id'] = explode('|', data_get($event, 'id', ''))[0] ?: null;

        $this->oldCalendarEvent = array_merge(
            Arr::pull($eventInfo['oldEvent'], 'extendedProps', []),
            $eventInfo['oldEvent']
        );
        $this->calendarEventWasRepeatable = $this->oldCalendarEvent['has_repeats'] ?? false;
        $this->confirmSave = 'this';

        $this->saveEvent($event);
    }

    protected function calculateRepeatableEvents($calendar, Collection $calendarEvents): Collection
    {
        $repeatables = $calendar->calendarEvents()
            ->whereNotNull('repeat')
            ->whereDate('start', '<', $this->calendarPeriod['end'])
            ->where(fn ($query) => $query->whereDate('repeat_end', '>', $this->calendarPeriod['start'])
                ->orWhereNull('repeat_end')
            )
            ->get();

        foreach ($repeatables as $repeatable) {
            foreach ($this->calculateRepetitionsFromEvent($repeatable->toArray()) as $event) {
                $calendarEvents->push($event);
            }
        }

        return $calendarEvents;
    }

    protected function calculateRepetitionsFromEvent(array|Model $event): array
    {
        if (! ($repeatString = data_get($event, 'repeat'))) {
            return [
                $event instanceof Model ?
                    $event : app(config('tall-calendar.models.calendar_event'))->forceFill($event),
            ];
        }

        $i = 0;
        $events = [];
        $recurrences = data_get($event, 'recurrences');

        for ($j = count($repeatValues = explode(',', $repeatString)); $j > 0; $j--) {
            if (data_get($event, 'recurrences')) {
                if ($recurrences < 1) {
                    continue;
                }

                $datePeriod = new DatePeriod(
                    Carbon::parse(data_get($event, 'start')),
                    DateInterval::createFromDateString($repeatValues[$i]),
                    ($count = (int) ceil($recurrences / $j)) - (int) ($i === 0), // subtract 1, because start date does not count towards recurrences limit
                    (int) ($i !== 0) // 1 = Exclude start date
                );

                $recurrences -= $count;
            } else {
                $datePeriod = new DatePeriod(
                    Carbon::parse(data_get($event, 'start')),
                    DateInterval::createFromDateString($repeatValues[$i]),
                    Carbon::parse(is_null(data_get($event, 'repeat_end')) ?
                        $this->calendarPeriod['end'] :
                        min([data_get($event, 'repeat_end'), $this->calendarPeriod['end']])
                    ),
                    (int) ($i !== 0)
                );
            }

            // filter dates in between start and end
            $dates = array_filter(
                iterator_to_array($datePeriod),
                fn ($item) => ($date = $item->format('Y-m-d H:i:s')) > $this->calendarPeriod['start']
                    && $date < $this->calendarPeriod['end']
                    && ! in_array($date, data_get($event, 'excluded') ?: [])
                    && (
                        ! data_get($event, 'repeat_end')
                        || $date < Carbon::parse(data_get($event, 'repeat_end'))->toDateTimeString()
                    )
            );

            $events = array_merge($events, Arr::mapWithKeys($dates, function ($date, $key) use ($event, $i) {
                $repetition = ($key + 1) * ($i + 1);
                $interval = date_diff(Carbon::parse(data_get($event, 'start')), Carbon::parse(data_get($event, 'end')));

                return [
                    $repetition => app(config('tall-calendar.models.calendar_event'))->forceFill(
                        array_merge(
                            $event,
                            [
                                'start' => ($start = Carbon::parse(data_get($event, 'start'))->setDateFrom($date))
                                    ->format('Y-m-d H:i:s'),
                                'end' => $start->add($interval)->format('Y-m-d H:i:s'),
                            ],
                            ['id' => data_get($event, 'id') . '|' . $repetition]
                        )
                    ),
                ];
            }));

            $i++;
        }

        return $events;
    }
}
