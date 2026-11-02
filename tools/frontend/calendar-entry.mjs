import { Calendar as BaseCalendar, globalLocales } from 'fullcalendar';
import dayGrid from 'fullcalendar/daygrid';
import timeGrid from 'fullcalendar/timegrid';
import classic from 'fullcalendar/themes/classic';
import french from 'fullcalendar/locales/fr';

globalLocales.push(french);
export class Calendar extends BaseCalendar {
    constructor(element, options = {}) {
        super(element, {
            ...options,
            plugins: [dayGrid, timeGrid, classic, ...(options.plugins || [])],
        });
    }
}
