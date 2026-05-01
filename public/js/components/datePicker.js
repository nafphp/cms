const formatter = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
    month: 'long',
    year: 'numeric',
});

const popoverClass = 'absolute left-0 top-[calc(100%+6px)] z-40 w-[min(310px,calc(100vw-48px))] rounded-lg border border-[var(--border)] bg-white p-1.5 shadow-[0_18px_44px_rgba(15,23,42,.14)] dark:bg-slate-900 dark:shadow-[0_20px_70px_rgba(0,0,0,.38)]';
const headClass = 'flex items-center justify-between px-1 pb-2 pt-1';
const monthButtonClass = 'h-8 w-8 rounded-md text-xl hover:bg-surface-soft';
const weekdayClass = 'grid grid-cols-7 gap-1 pb-1 text-center text-[11px] font-extrabold text-slate-500';
const gridClass = 'grid grid-cols-7 gap-1';
const dayBaseClass = 'h-[34px] rounded-md text-[13px] font-semibold hover:bg-surface-soft';
const mutedDayClass = 'text-slate-400';
const selectedDayClass = 'bg-accent text-white hover:bg-accent';
const todayDayClass = 'bg-surface-soft';

function pad(value) {
    return String(value).padStart(2, '0');
}

function formatDate(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function parseDate(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) {
        return null;
    }

    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return Number.isNaN(date.getTime()) ? null : date;
}

export function initDatePicker(root) {
    if (!(root instanceof HTMLElement) || root.dataset.datePickerInit === 'true') {
        return null;
    }

    const input = root.querySelector('[data-date-picker-input]');
    const toggle = root.querySelector('[data-date-picker-toggle]');
    if (!(input instanceof HTMLInputElement) || !(toggle instanceof HTMLButtonElement)) {
        return null;
    }

    root.dataset.datePickerInit = 'true';

    const popover = document.createElement('div');
    popover.className = `${popoverClass} hidden`;

    let cursor = parseDate(input.value) || new Date();
    cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);

    const close = () => {
        popover.classList.add('hidden');
    };

    const open = () => {
        render();
        popover.classList.remove('hidden');
    };

    const setValue = (date) => {
        input.value = formatDate(date);
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
        close();
        input.focus();
    };

    const render = () => {
        const selected = parseDate(input.value);
        const today = new Date();
        const firstDay = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        const startOffset = (firstDay.getDay() + 6) % 7;
        const start = new Date(firstDay);
        start.setDate(firstDay.getDate() - startOffset);

        const days = Array.from({length: 42}, (_unused, index) => {
            const date = new Date(start);
            date.setDate(start.getDate() + index);
            const isCurrentMonth = date.getMonth() === cursor.getMonth();
            const value = formatDate(date);
            const className = [
                dayBaseClass,
                isCurrentMonth ? '' : mutedDayClass,
                value === formatDate(today) ? todayDayClass : '',
                selected && value === formatDate(selected) ? selectedDayClass : '',
            ].filter(Boolean).join(' ');
            return `<button type="button" class="${className}" data-date="${value}">${date.getDate()}</button>`;
        }).join('');

        popover.innerHTML = `
            <div class="${headClass}">
                <button class="${monthButtonClass}" type="button" data-month="-1" aria-label="Previous month">&lsaquo;</button>
                <strong>${formatter.format(cursor)}</strong>
                <button class="${monthButtonClass}" type="button" data-month="1" aria-label="Next month">&rsaquo;</button>
            </div>
            <div class="${weekdayClass}">
                <span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span>
            </div>
            <div class="${gridClass}">${days}</div>
        `;
    };

    popover.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const monthButton = target.closest('[data-month]');
        if (monthButton instanceof HTMLElement) {
            cursor = new Date(cursor.getFullYear(), cursor.getMonth() + Number(monthButton.dataset.month || 0), 1);
            render();
            return;
        }

        const dayButton = target.closest('[data-date]');
        if (dayButton instanceof HTMLElement) {
            const date = parseDate(dayButton.dataset.date);
            if (date) {
                setValue(date);
            }
        }
    });

    toggle.addEventListener('click', () => {
        if (popover.classList.contains('hidden')) {
            open();
            return;
        }
        close();
    });

    input.addEventListener('focus', () => {
        cursor = parseDate(input.value) || cursor;
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    });

    const onDocumentClick = (event) => {
        const target = event.target;
        if (target instanceof Node && !root.contains(target)) {
            close();
        }
    };

    const onEscape = (event) => {
        if (event.key === 'Escape') {
            close();
        }
    };

    root.append(popover);
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);

    return {
        root,
        destroy() {
            document.removeEventListener('click', onDocumentClick);
            document.removeEventListener('keydown', onEscape);
            popover.remove();
            delete root.dataset.datePickerInit;
        },
    };
}

export function initDatePickers(selector) {
    const elements = typeof selector === 'string' ? document.querySelectorAll(selector) : selector;
    return Array.from(elements || [])
        .map((element) => initDatePicker(element))
        .filter(Boolean);
}
