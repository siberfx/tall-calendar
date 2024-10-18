const calendar = () => {
    return {
        calendarItem: {},
        parseDateTime(event, locale, property) {
            const dateTime = new Date(event.start);
            let config = null;
            if (event.is_all_day === true) {
                config = {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                }
            } else {
                config = {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                }
            }

            return dateTime.toLocaleString(locale, config);
        },
        inviteStatus(calendarEvent, status) {
            calendarEvent.status = status;
            if (this.calendarItem.resourceEditable === false) {
                this.calendarClick(this.calendars.find(c => c.resourceEditable === true));
            }

            const existingEvent = this.calendar.getEventById(calendarEvent.calendar_event.id);

            if ((status === 'accepted' || status === 'maybe') && !existingEvent) {
                this.calendar.addEvent(calendarEvent.calendar_event, this.calendar.getEventSourceById(this.calendarId));
            } else if (status === 'declined' && existingEvent) {
                existingEvent.remove();
            }

            this.$wire.inviteStatus(calendarEvent.id, status, this.calendarId);
        },
        calendarClick(calendar) {
            this.calendarId = calendar.id;
            this.calendarItem = calendar;
        },
        saveCalendar() {
            this.$wire.saveCalendar().then(calendar => {
                if (calendar === false) {
                    return false;
                }

                calendar.group = calendar.group || 'my';

                let index = this.calendars.findIndex(c => c.id === calendar.id);

                if (calendar.parentId
                    || (index !== -1 && this.calendars[index].parentId !== calendar.parentId)
                ) {
                    let siblingIndex = this.calendars.findLastIndex(c => c.parentId === calendar.parentId);
                    let parentIndex = -1;

                    if (siblingIndex !== -1) {
                        this.calendars.splice(siblingIndex + 1, 0, calendar);
                    } else {
                        parentIndex = this.calendars.findIndex(c => c.id === calendar.parentId);

                        if (parentIndex !== -1) {
                            this.calendars.splice(parentIndex + 1, 0, calendar);
                        } else {
                            parentIndex = this.calendars.length;
                            this.calendars.push(calendar);
                        }
                    }

                    if (index !== -1) {
                        this.calendars.splice(
                            siblingIndex > index || parentIndex > index ? index : index + 1,
                            1
                        );
                    }
                } else {
                    this.calendars.splice(index, index !== -1 ? 1 : 0, calendar);
                }

                this.calendarId = calendar.id;

                calendar.permission = calendar.is_editable ? 'owner': 'reader';
                this.calendar.getEventSourceById(this.calendarItem.id)?.remove();
                calendar.events = (info) => this.$wire.$parent.getEvents(info, calendar);
                this.calendar.addEventSource(calendar);

                this.$wire.$parent.updateSelectableCalendars(calendar);

                // check if this.close exists
                if (typeof this.close === 'function') {
                    this.close();
                }
            });
        },
        deleteCalendar() {
            this.$wire.deleteCalendar(this.calendarItem).then(success => {
                if (success) {
                    this.calendar.getEventSourceById(this.calendarItem.id).remove();
                    this.calendars.splice(this.calendars.findIndex(c => c.id === this.calendarItem.id), 1);
                    this.$wire.$parent.removeSelectableCalendar(this.calendarItem);

                    this.close();
                }
            });
        },
        saveEvent() {
            this.$wire.saveEvent(this.$wire.calendarEvent).then(event => {
                if (event === false) {
                    return false;
                }

                if (event instanceof Array) {
                    event.map(item => String(item.id).split('|')[0])
                        .filter((value, index, self) => self.indexOf(value) === index)
                        .forEach((id) => {
                            this.calendar.getEvents()
                                .filter(filter => filter.id.split('|')[0] === String(id))
                                .forEach(e => e.remove())
                        });

                    event.forEach((e) => {
                        this.calendar.addEvent(e, this.calendar.getEventSourceById(e.calendar_id));
                    });
                } else {
                    this.calendar.getEventById(event.id)?.remove();
                    this.calendar.addEvent(event, this.calendar.getEventSourceById(event.calendar_id));
                }

                // check if this.close exists
                if (typeof this.close === 'function') {
                    this.close();
                }
            });
        },
        setDateTime(type, event) {
            const date = event.target.parentNode.parentNode.parentNode.querySelector('input[type="date"]').value;
            let time = event.target.parentNode.parentNode.parentNode.querySelector('input[type="time"]').value;

            if (this.$wire.calendarEvent.allDay) {
                time = '00:00:00';
            }

            let dateTime = dayjs(date + ' ' + time);

            if (type === 'start') {
                this.$wire.calendarEvent.start = dateTime.format(); // Use the default ISO 8601 format
            } else {
                this.$wire.calendarEvent.end = dateTime.format(); // Use the default ISO 8601 format
            }
        },
        deleteEvent() {
            this.$wire.deleteEvent(this.$wire.calendarEvent).then(event => {
                if (event === false) {
                    return false;
                }

                switch (true) {
                    case event.repetition === null:
                        this.calendar.getEventById(event.id)?.remove();
                        break;
                    case event.confirmOption === 'this':
                        this.calendar.getEventById(event.id + '|' + event.repetition)?.remove();
                        break;
                    case event.confirmOption === 'future':
                    case event.confirmOption === 'all':
                        this.calendar.getEvents().filter(e => {
                            const split = e.id.split('|');
                            if (event.confirmOption === 'future') {
                                return split[0] === String(event.id) && split[1] >= event.repetition;
                            } else {
                                return split[0] === String(event.id);
                            }
                        }).forEach(e => e.remove());
                        break;
                }

                // check if this.close exists
                if (typeof this.close === 'function') {
                    this.close();
                }
            });
        },
        calendar: null,
        config: {},
        id: null,
        calendarId: null,
        calendars: [],
        invites: [],
        calendarEvent: {},
        dispatchCalendarEvents(eventName, params) {
            const eventNameKebap = eventName.replace(/([a-z0-9]|(?=[A-Z]))([A-Z])/g, '$1-$2').toLowerCase();
            this.$wire.dispatch(`calendar-${eventNameKebap}`, params);
        },
        getCalendarEventSources() {
            this.calendars.forEach((calendar) => {
                if (this.calendarId === null) {
                    this.calendarId = calendar.id;
                }

                calendar.events = (info) => this.$wire.getEvents(info, calendar);
            });

            return this.calendars;
        },
        toggleEventSource(calendar) {
            const calendarEventSource = this.calendar.getEventSourceById(calendar.id);
            if (calendarEventSource) {
                calendarEventSource.remove();
            } else {
                this.calendar.addEventSource(calendar);
            }
        },
        init() {
            this.id = this.$id('calendar');
            this.$wire.getCalendars().then((calendars) => {
                this.calendars = calendars;
            });
            this.$wire.getConfig().then((config) => {
                this.config = config;
                this.initCalendar();
            });
            this.$wire.getInvites().then((invites) => {
                this.invites = invites;
            });
        },
        initCalendar() {
            let calendarEl = document.getElementById(this.id);

            let defaultConfig = {
                plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
                initialView: 'dayGridMonth',
                initialDate: new Date(),
                editable: true,
                selectable: true,
                selectMirror: true,
                dayMaxEvents: true,
                eventSources: this.getCalendarEventSources(),
                select: selectionInfo => {
                    this.dispatchCalendarEvents('select', selectionInfo);
                },
                unselect: (jsEvent, view) => {
                    this.dispatchCalendarEvents('unselect', {jsEvent, view});
                },
                dateClick: dateClickInfo => {
                    this.$wire.onDateClick(dateClickInfo, this.calendarItem);
                    this.dispatchCalendarEvents('dateClick', dateClickInfo);
                },
                eventDidMount: eventDidMountInfo => {
                    this.dispatchCalendarEvents('eventDidMount', eventDidMountInfo);
                },
                eventClick: eventClickInfo => {
                    this.$wire.onEventClick(eventClickInfo);
                    this.dispatchCalendarEvents('eventClick', eventClickInfo);
                },
                eventMouseEnter: eventMouseEnterInfo => {
                    this.dispatchCalendarEvents('eventMouseEnter', eventMouseEnterInfo);
                },
                eventMouseLeave: eventMouseLeaveInfo => {
                    this.dispatchCalendarEvents('eventMouseLeave', eventMouseLeaveInfo);
                },
                eventDragStart: eventDragStartInfo => {
                    this.$wire.onEventDragStart(eventDragStartInfo);
                    this.dispatchCalendarEvents('eventDragStart', eventDragStartInfo);
                },
                eventDragStop: eventDragStopInfo => {
                    this.$wire.onEventDragStop(eventDragStopInfo);
                    this.dispatchCalendarEvents('eventDragStop', eventDragStopInfo);
                },
                eventDrop: eventDropInfo => {
                    this.$wire.onEventDrop(eventDropInfo);
                    this.dispatchCalendarEvents('eventDrop', eventDropInfo);
                },
                eventResizeStart: eventResizeStartInfo => {
                    this.dispatchCalendarEvents('eventResizeStart', eventResizeStartInfo);
                },
                eventResizeStop: eventResizeStopInfo => {
                    this.dispatchCalendarEvents('eventResizeStop', eventResizeStopInfo);
                },
                eventResize: eventResizeInfo => {
                    this.dispatchCalendarEvents('eventResize', eventResizeInfo);
                },
                drop: dropInfo => {
                    this.dispatchCalendarEvents('drop', dropInfo);
                },
                eventReceive: eventReceiveInfo => {
                    this.dispatchCalendarEvents('eventReceive', eventReceiveInfo);
                },
                eventLeave: eventLeaveInfo => {
                    this.dispatchCalendarEvents('eventLeave', eventLeaveInfo);
                },
                eventAdd: eventAddInfo => {
                    this.dispatchCalendarEvents('eventAdd', eventAddInfo);
                },
                eventChange: eventChangeInfo => {
                    this.dispatchCalendarEvents('eventChange', eventChangeInfo);
                },
                eventRemove: eventRemoveInfo => {
                    this.dispatchCalendarEvents('eventRemove', eventRemoveInfo);
                },
                eventsSet: eventsSetInfo => {
                    this.dispatchCalendarEvents('eventsSet', eventsSetInfo);
                },
            };

            this.calendar = new Calendar(calendarEl, {...defaultConfig, ...this.config});
            this.calendar.render();
        },
    }
}

export default calendar;
