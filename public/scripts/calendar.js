(() => {
    const shell = document.querySelector('[data-calendar-shell]');
    if (!shell || !window.FullCalendar) {
        return;
    }

    const grid = shell.querySelector('[data-calendar-grid]');
    const title = shell.querySelector('[data-calendar-title]');
    const status = shell.querySelector('[data-calendar-status]');
    const error = shell.querySelector('[data-calendar-error]');
    const retry = shell.querySelector('[data-calendar-retry]');
    const fallback = document.querySelector('[data-calendar-fallback]');
    const dialog = document.querySelector('[data-calendar-dialog]');
    const timezone = grid.dataset.timezone;
    let requestNumber = 0;
    let selectedElement = null;

    const dateOnly = new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeZone: 'UTC',
    });
    const dayInCalendar = new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeZone: timezone,
    });
    const dateTime = new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone: timezone,
    });
    const clock = new Intl.DateTimeFormat('fr-FR', {
        timeStyle: 'short',
        timeZone: timezone,
    });

    function showDetails(info) {
        info.jsEvent.preventDefault();
        const event = info.event;
        selectedElement = info.el;
        dialog.querySelector('[data-event-title]').textContent = event.title;
        dialog.querySelector('[data-event-location]').textContent =
            event.extendedProps.location || 'Lieu non renseigné';

        let when;
        if (event.allDay) {
            const first = new Date(`${event.startStr.slice(0, 10)}T00:00:00Z`);
            const last = new Date(`${event.endStr.slice(0, 10)}T00:00:00Z`);
            last.setUTCDate(last.getUTCDate() - 1);
            when = dateOnly.format(first);
            if (first.getTime() !== last.getTime()) {
                when += ` au ${dateOnly.format(last)}`;
            }
            when += ' · Toute la journée';
        } else {
            const sameDay = dayInCalendar.format(event.start) === dayInCalendar.format(event.end);
            when = `${dateTime.format(event.start)} → ${sameDay ? clock.format(event.end) : dateTime.format(event.end)}`;
        }
        dialog.querySelector('[data-event-date]').textContent = when;
        const link = dialog.querySelector('[data-event-url]');
        link.hidden = !event.url;
        if (event.url) {
            link.href = event.url;
        } else {
            link.removeAttribute('href');
        }
        dialog.showModal();
    }

    const calendar = new FullCalendar.Calendar(grid, {
        initialView: 'dayGridMonth',
        initialDate: grid.dataset.today,
        locale: 'fr',
        timeZone: timezone,
        colorScheme: document.documentElement.dataset.theme || 'light',
        firstDay: 1,
        headerToolbar: false,
        height: window.matchMedia('(max-width: 760px)').matches ? 590 : 720,
        validRange: { start: '2000-01-01', end: '2100-01-01' },
        fixedWeekCount: true,
        dayMaxEvents: 3,
        editable: false,
        selectable: false,
        nowIndicator: true,
        scrollTime: '08:00:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        eventClass: 'bb-calendar-event',
        eventTitleClass: 'bb-calendar-event-title',
        dayHeaderClass: 'bb-calendar-day-header',
        dayCellClass: 'bb-calendar-day',
        datesSet(info) {
            if (info.view.type === 'timeGridWeek') {
                const first = new Date(`${info.startStr.slice(0, 10)}T00:00:00Z`);
                const last = new Date(`${info.endStr.slice(0, 10)}T00:00:00Z`);
                last.setUTCDate(last.getUTCDate() - 1);
                title.textContent = dateOnly.formatRange(first, last);
            } else {
                title.textContent = info.view.title;
            }
            shell.querySelector('[data-calendar-mobile-hint]').hidden =
                info.view.type !== 'timeGridWeek';
            grid.classList.toggle('calendar-grid--week', info.view.type === 'timeGridWeek');
            shell.querySelectorAll('[data-calendar-view]').forEach((button) => {
                button.setAttribute(
                    'aria-pressed',
                    String(button.dataset.calendarView === info.view.type),
                );
            });
        },
        async events(info) {
            const current = ++requestNumber;
            const url = new URL(grid.dataset.eventsUrl, window.location.origin);
            url.searchParams.set('start', info.startStr.slice(0, 10));
            url.searchParams.set('end', info.endStr.slice(0, 10));
            grid.setAttribute('aria-busy', 'true');
            status.textContent = 'Chargement des événements…';
            error.hidden = true;
            retry.hidden = true;
            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!response.headers.get('Content-Type')?.includes('application/json')) {
                    throw new Error('La session a expiré. Recharge la page pour te reconnecter.');
                }
                const data = await response.json();
                if (!response.ok || !Array.isArray(data.events)) {
                    throw new Error(data.error || 'L’agenda est momentanément indisponible.');
                }
                if (current === requestNumber) {
                    status.textContent = data.hasMore
                        ? 'La période contient plus de 1 000 événements. Réduis-la avec la vue Semaine.'
                        : `Actualisé à ${clock.format(new Date(data.fetchedAt))} · ${timezone}`;
                }
                return data.events;
            } catch (failure) {
                if (current === requestNumber) {
                    error.textContent =
                        failure instanceof Error
                            ? failure.message
                            : 'L’agenda est momentanément indisponible.';
                    error.hidden = false;
                    retry.hidden = false;
                    status.textContent = 'La période n’a pas pu être chargée.';
                }
                throw failure;
            } finally {
                if (current === requestNumber) {
                    grid.setAttribute('aria-busy', 'false');
                }
            }
        },
        eventClick: showDetails,
        eventDidMount(info) {
            info.el.setAttribute('title', info.event.title);
            info.el.setAttribute('aria-label', `Consulter : ${info.event.title}`);
        },
    });

    shell
        .querySelector('[data-calendar-previous]')
        .addEventListener('click', () => calendar.prev());
    shell.querySelector('[data-calendar-next]').addEventListener('click', () => calendar.next());
    shell.querySelector('[data-calendar-today]').addEventListener('click', () => calendar.today());
    shell.querySelectorAll('[data-calendar-view]').forEach((button) => {
        button.addEventListener('click', () => calendar.changeView(button.dataset.calendarView));
    });
    retry.addEventListener('click', () => calendar.refetchEvents());
    window.addEventListener('backlinebridge:themechange', (event) => {
        if (['light', 'dark'].includes(event.detail?.theme)) {
            calendar.setOption('colorScheme', event.detail.theme);
        }
    });
    dialog.addEventListener('close', () => selectedElement?.focus());
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) {
            return;
        }
        const bounds = dialog.getBoundingClientRect();
        if (
            event.clientX < bounds.left ||
            event.clientX > bounds.right ||
            event.clientY < bounds.top ||
            event.clientY > bounds.bottom
        ) {
            dialog.close();
        }
    });

    shell.hidden = false;
    try {
        calendar.render();
        if (fallback) {
            fallback.open = false;
        }
    } catch {
        shell.hidden = true;
    }
})();
