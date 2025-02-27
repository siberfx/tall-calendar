<div class="grid grid-cols-1 gap-1.5" x-data="{errors: $wire.entangle('validationErrors')}">
    @section('event-edit.content')
        <x-select
            wire:model="calendarEvent.calendar_id"
            :label="__('Calendar')"
            option-value="id"
            option-label="name"
            :clearable="false"
            :options="$this->selectableCalendars"
            x-on:selected="$wire.calendarEvent.is_repeatable = $wire.isCalendarEventRepeatable($event.detail.value);"
        />
        <x-input x-ref="autofocus" :label="__('Title') . '*'" wire:model="calendarEvent.title" x-bind:readonly="! $wire.calendarEvent.is_editable ?? false"/>
        <x-textarea :label="__('Description')" wire:model="calendarEvent.description" x-bind:readonly="! $wire.calendarEvent.is_editable ?? false"/>
        <x-checkbox :label="__('all-day')" wire:model="calendarEvent.allDay" x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"/>
        <div class="grid grid-cols-3 items-center gap-1.5">
            <x-label>
                {{__('Start')}}
            </x-label>
            <x-input
                id="calendar-event-start-date"
                type="date"
                x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
                x-bind:value="dayjs($wire.calendarEvent.start).format('YYYY-MM-DD')"
                x-on:change="setDateTime('start', $event)"
            />
            <x-input
                id="calendar-event-start-time"
                x-show="! $wire.calendarEvent.allDay"
                type="time"
                x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
                x-on:change="setDateTime('start', $event)"
                x-bind:value="dayjs($wire.calendarEvent.start).format('HH:mm')"
            />
        </div>
        <div class="grid grid-cols-3 items-center gap-1.5">
            <x-label>
                {{__('End')}}
            </x-label>
            <x-input
                id="calendar-event-end-date"
                type="date"
                x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
                x-bind:value="dayjs($wire.calendarEvent.end).format('YYYY-MM-DD')"
                x-on:change="setDateTime('end', $event)"
            />
            <x-input
                id="calendar-event-end-time"
                x-show="! $wire.calendarEvent.allDay"
                type="time"
                x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
                x-on:change="setDateTime('end', $event)"
                x-bind:value="dayjs($wire.calendarEvent.end).format('HH:mm')"
            />
        </div>
        <div class="mb-2" x-show="$wire.calendarEvent.is_repeatable">
            <x-checkbox
                :label="__('Repeatable')"
                wire:model="calendarEvent.has_repeats"
                x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
            />
        </div>
        <div x-show="$wire.calendarEvent.has_repeats && $wire.calendarEvent.is_repeatable">
            <div class="grid grid-cols-3 items-center gap-1.5">
                <x-label>
                    {{ __('Repeat every') }}
                </x-label>
                <x-inputs.number wire:model="calendarEvent.interval" :min="1" x-bind:disabled="! $wire.calendarEvent.is_editable ?? false" />
                <x-select
                    x-on:selected="$wire.calendarEvent.unit = $event.detail.value"
                    x-init="$watch('$wire.calendarEvent.unit', (value) => {
                        const option = options.find(option => option.value === value);
                        if (option) {
                            select(option);
                        }
                    })"
                    :clearable="false"
                    :options="[
                        ['label' => __('Day(s)'), 'value' => 'days'],
                        ['label' => __('Week(s)'), 'value' => 'weeks'],
                        ['label' => __('Month(s)'), 'value' => 'months'],
                        ['label' => __('Year(s)'), 'value' => 'years'],
                    ]"
                    option-label="label"
                    option-value="value"
                    x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
                />
            </div>

            <template x-if="$wire.calendarEvent.unit === 'weeks'">
                <div class="grid grid-cols-7 items-center gap-1.5 mt-4">
                    <x-button
                        rounded
                        primary
                        flat
                        xs
                        :label="__('Mon')"
                        x-on:click="$wire.calendarEvent.weekdays.indexOf('Mon') !== -1 ? $wire.calendarEvent.weekdays = $wire.calendarEvent.weekdays.filter((day) => day !== 'Mon') : $wire.calendarEvent.weekdays.push('Mon')"
                        x-bind:class="$wire.calendarEvent.weekdays.indexOf('Mon') !== -1 ? 'bg-primary-500 text-white' : ''"
                    />
                    <x-button
                        rounded
                        primary
                        flat
                        xs
                        :label="__('Tue')"
                        x-on:click="$wire.calendarEvent.weekdays.indexOf('Tue') !== -1 ? $wire.calendarEvent.weekdays = $wire.calendarEvent.weekdays.filter((day) => day !== 'Tue') : $wire.calendarEvent.weekdays.push('Tue')"
                        x-bind:class="$wire.calendarEvent.weekdays.indexOf('Tue') !== -1 ? 'bg-primary-500 text-white' : ''"
                    />
                    <x-button
                        rounded
                        primary
                        flat
                        xs
                        :label="__('Wed')"
                        x-on:click="$wire.calendarEvent.weekdays.indexOf('Wed') !== -1 ? $wire.calendarEvent.weekdays = $wire.calendarEvent.weekdays.filter((day) => day !== 'Wed') : $wire.calendarEvent.weekdays.push('Wed')"
                        x-bind:class="$wire.calendarEvent.weekdays.indexOf('Wed') !== -1 ? 'bg-primary-500 text-white' : ''"
                    />
                    <x-button
                        rounded
                        primary
                        flat
                        xs
                        :label="__('Thu')"
                        x-on:click="$wire.calendarEvent.weekdays.indexOf('Thu') !== -1 ? $wire.calendarEvent.weekdays = $wire.calendarEvent.weekdays.filter((day) => day !== 'Thu') : $wire.calendarEvent.weekdays.push('Thu')"
                        x-bind:class="$wire.calendarEvent.weekdays.indexOf('Thu') !== -1 ? 'bg-primary-500 text-white' : ''"
                    />
                    <x-button
                        rounded
                        primary
                        flat
                        xs
                        :label="__('Fri')"
                        x-on:click="$wire.calendarEvent.weekdays.indexOf('Fri') !== -1 ? $wire.calendarEvent.weekdays = $wire.calendarEvent.weekdays.filter((day) => day !== 'Fri') : $wire.calendarEvent.weekdays.push('Fri')"
                        x-bind:class="$wire.calendarEvent.weekdays.indexOf('Fri') !== -1 ? 'bg-primary-500 text-white' : ''"
                    />
                    <x-button
                        rounded
                        primary
                        flat
                        xs
                        :label="__('Sat')"
                        x-on:click="$wire.calendarEvent.weekdays.indexOf('Sat') !== -1 ? $wire.calendarEvent.weekdays = $wire.calendarEvent.weekdays.filter((day) => day !== 'Sat') : $wire.calendarEvent.weekdays.push('Sat')"
                        x-bind:class="$wire.calendarEvent.weekdays.indexOf('Sat') !== -1 ? 'bg-primary-500 text-white' : ''"
                    />
                    <x-button
                        rounded
                        primary
                        flat
                        xs
                        :label="__('Sun')"
                        x-on:click="$wire.calendarEvent.weekdays.indexOf('Sun') !== -1 ? $wire.calendarEvent.weekdays = $wire.calendarEvent.weekdays.filter((day) => day !== 'Sun') : $wire.calendarEvent.weekdays.push('Sun')"
                        x-bind:class="$wire.calendarEvent.weekdays.indexOf('Sun') !== -1 ? 'bg-primary-500 text-white' : ''"
                    />
                </div>
            </template>
            <template x-if="$wire.calendarEvent.unit === 'months'">
                <x-select class="mt-4"
                    x-on:selected="$wire.calendarEvent.monthly = $event.detail.value"
                    x-init="$watch('$wire.calendarEvent.monthly', (value) => {
                        const option = options.find(option => option.value === value);
                        if (option) {
                            select(option);
                        }
                    })"
                    :clearable="false"
                    x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
                >
                    <x-select.option value="day">
                        <span x-text="'{{ __('Monthly on') }} ' + dayjs($wire.calendarEvent.start).format('DD') + '.'"></span>
                    </x-select.option>
                    <x-select.option value="first">
                        <span x-text="'{{ __('Monthly on first') }} ' + dayjs($wire.calendarEvent.start).format('dddd')"></span>
                    </x-select.option>
                    <x-select.option value="second">
                        <span x-text="'{{ __('Monthly on second') }} ' + dayjs($wire.calendarEvent.start).format('dddd')"></span>
                    </x-select.option>
                    <x-select.option value="third">
                        <span x-text="'{{ __('Monthly on third') }} ' + dayjs($wire.calendarEvent.start).format('dddd')"></span>
                    </x-select.option>
                    <x-select.option value="fourth">
                        <span x-text="'{{ __('Monthly on fourth') }} ' + dayjs($wire.calendarEvent.start).format('dddd')"></span>
                    </x-select.option>
                    <x-select.option value="last">
                        <span x-text="'{{ __('Monthly on last') }} ' + dayjs($wire.calendarEvent.start).format('dddd')"></span>
                    </x-select.option>
                </x-select>
            </template>

            <x-label class="mt-4 mb-2">
                {{ __('Repeat end') }}
            </x-label>
            <x-radio :label="__('Never')" :value="null" x-model="$wire.calendarEvent.repeat_radio" x-bind:disabled="! $wire.calendarEvent.is_editable ?? false" />
            <div class="grid grid-cols-2 items-center gap-1.5">
                <x-radio :label="__('Date At')" value="repeat_end" x-model="$wire.calendarEvent.repeat_radio" x-bind:disabled="! $wire.calendarEvent.is_editable ?? false" />
                <x-input
                    id="calendar-event-repeat-end-date"
                    type="date"
                    x-bind:disabled="(! $wire.calendarEvent.is_editable ?? false) || $wire.calendarEvent.repeat_radio !== 'repeat_end'"
                    x-bind:value="dayjs($wire.calendarEvent.repeat_end).format('YYYY-MM-DD')"
                    x-on:change="$wire.calendarEvent.repeat_end = dayjs($event.target.value).format('YYYY-MM-DD')"
                />
                <x-radio :label="__('After amount of events')" value="recurrences" x-model="$wire.calendarEvent.repeat_radio" x-bind:disabled="! $wire.calendarEvent.is_editable ?? false" />
                <x-inputs.number x-model="$wire.calendarEvent.recurrences" x-bind:disabled="(! $wire.calendarEvent.is_editable ?? false) || $wire.calendarEvent.repeat_radio !== 'recurrences'" />
            </div>
        </div>
        <div x-show="calendarEvent.is_invited">
            <x-select x-model="calendarEvent.status" x-init="$watch('calendarEvent.status', (value) => {
                    const option = options.find(option => option.value === value);
                    if (option) {
                        select(option);
                    } else {
                        clear();
                    }
                })" :label="__('My status')" :clearable="false">
                <x-select.option value="accepted">
                    <div>
                        <x-button.circle
                            disabled
                            positive
                            xs
                            icon="check"
                        />{{__('Accepted')}}
                    </div>
                </x-select.option>
                <x-select.option :label="__('Declined')" value="declined">
                    <div>
                        <x-button.circle
                            disabled
                            negative
                            xs
                            icon="x"
                        />{{__('Declined')}}
                    </div>
                </x-select.option>
                <x-select.option :label="__('Maybe')" value="maybe">
                    <div>
                        <x-button.circle
                            disabled
                            warning
                            xs
                            label="?"
                        />{{__('Maybe')}}
                    </div>
                </x-select.option>
            </x-select>
        </div>
        <div>
            <div class="grid grid-cols-1 gap-1.5" x-show="$wire.calendarEvent.is_editable ?? false" x-on:click.outside="search = false">
                <x-label for="invite" :label="__('Invites')" />
                <template x-for="invited in $wire.calendarEvent.invited">
                    <div class="flex gap-1.5">
                        <x-button.circle
                            negative
                            xs
                            icon="trash"
                            x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
                            x-on:click="$wire.calendarEvent.invited.splice($wire.calendarEvent.invited.indexOf(invited), 1)"
                        />
                        <template x-if="invited.pivot?.status === 'accepted'">
                            <x-button.circle
                                disabled
                                positive
                                xs
                                icon="check"
                            />
                        </template>
                        <template x-if="invited.pivot?.status === 'declined'">
                            <x-button.circle
                                disabled
                                negative
                                xs
                                icon="x" />
                        </template>
                        <template x-if="invited.pivot?.status === 'maybe'">
                            <x-button.circle
                                disabled
                                warning
                                xs
                                label="?"
                            />
                        </template>
                        <template x-if="invited.pivot?.status !== 'accepted' && invited.pivot?.status !== 'declined' && invited.pivot?.status !== 'maybe'">
                            <x-button.circle
                                disabled
                                secondary
                                xs
                                label="?"
                            />
                        </template>
                        <x-badge md x-text="invited.label" />
                    </div>
                </template>
                <x-select
                    id="invite"
                    option-value="id"
                    option-label="label"
                    :placeholder="__('Add invite')"
                    :template="[
                        'name'   => 'user-option',
                    ]"
                    :async-data="[
                        'api' => route('search', \FluxErp\Models\User::class),
                        'method' => 'POST',
                        'params' => [
                            'with' => 'media',
                            'where' => [
                                [
                                    'id',
                                    '!=',
                                    auth()->user()?->id
                                ]
                            ],
                        ]
                    ]"
                    x-on:selected="$wire.calendarEvent.invited.push($event.detail); clear(); asyncData.params.where.push(['id', '!=', $event.detail.id])"
                />
            </div>
        </div>
        <div class="mb-2">
            <x-checkbox
                :label="__('Has taken place')"
                wire:model="calendarEvent.has_taken_place"
                x-bind:disabled="! $wire.calendarEvent.is_editable ?? false"
            />
        </div>
    @show
</div>
