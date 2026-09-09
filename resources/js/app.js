import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('datePicker', (initialValue = '', minimum = null, maximum = null) => ({
    open: false,
    value: initialValue || '',
    viewYear: new Date().getFullYear(),
    viewMonth: new Date().getMonth(),
    minimum,
    maximum,
    popupStyle: 'visibility: hidden;',

    init() {
        this.syncView();
        this.$watch('value', () => this.syncView());
    },

    toggle() {
        this.open = !this.open;
        if (this.open) {
            this.syncView();
            this.$nextTick(() => this.updatePosition());
        }
    },

    openPicker() {
        this.open = true;
        this.syncView();
        this.$nextTick(() => this.updatePosition());
    },

    close() {
        this.open = false;
    },

    updatePosition() {
        if (!this.open || !this.$refs.trigger) return;

        const rect = this.$refs.trigger.getBoundingClientRect();
        const modalPanel = this.$refs.trigger.closest('.crm-modal-panel');
        const bounds = modalPanel
            ? modalPanel.getBoundingClientRect()
            : { top: 0, right: window.innerWidth, bottom: window.innerHeight, left: 0 };
        const leftBoundary = Math.max(16, bounds.left + 16);
        const rightBoundary = Math.min(window.innerWidth - 16, bounds.right - 16);
        const width = Math.min(336, window.innerWidth - 32, Math.max(240, rightBoundary - leftBoundary));
        const left = Math.max(leftBoundary, Math.min(rect.left, rightBoundary - width));
        const topPadding = modalPanel ? Math.max(16, bounds.top + 16) : 80;
        const bottomPadding = 16;
        const preferredHeight = 420;
        const bottomBoundary = modalPanel ? Math.min(window.innerHeight - 16, bounds.bottom - 16) : window.innerHeight - bottomPadding;
        const spaceBelow = Math.max(bottomBoundary - rect.bottom - 8, 160);
        const spaceAbove = Math.max(rect.top - topPadding - 8, 0);
        const openAbove = spaceBelow < preferredHeight
            && spaceAbove > spaceBelow
            && rect.top - Math.min(preferredHeight, spaceAbove) - 8 >= topPadding;
        const popupHeight = Math.min(preferredHeight, openAbove ? spaceAbove : spaceBelow);
        const top = openAbove ? Math.max(topPadding, rect.top - popupHeight - 8) + 'px' : (rect.bottom + 8) + 'px';
        const bottom = 'auto';

        this.popupStyle = [
            'width: ' + width + 'px',
            'left: ' + left + 'px',
            'top: ' + top,
            'bottom: ' + bottom,
            'max-height: ' + popupHeight + 'px',
            'overflow-y: auto',
        ].join('; ');
    },

    syncView() {
        const parsed = this.parseDate(this.value);
        if (!parsed) return;
        this.viewYear = parsed.getFullYear();
        this.viewMonth = parsed.getMonth();
    },

    parseDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
        const [year, month, day] = value.split('-').map(Number);
        const parsed = new Date(year, month - 1, day);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    },

    formatDate(date) {
        const pad = (number) => String(number).padStart(2, '0');
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    },

    get displayValue() {
        const parsed = this.parseDate(this.value);
        return parsed ? new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
        }).format(parsed) : '';
    },

    get monthLabel() {
        return new Intl.DateTimeFormat('en-US', {
            month: 'long',
            year: 'numeric',
        }).format(new Date(this.viewYear, this.viewMonth, 1));
    },

    get calendarDays() {
        const firstDay = new Date(this.viewYear, this.viewMonth, 1);
        const gridStart = new Date(this.viewYear, this.viewMonth, 1 - firstDay.getDay());
        const today = this.formatDate(new Date());

        return Array.from({ length: 42 }, (_, index) => {
            const date = new Date(gridStart);
            date.setDate(gridStart.getDate() + index);
            const dateValue = this.formatDate(date);

            return {
                date: dateValue,
                day: date.getDate(),
                label: new Intl.DateTimeFormat('en-US', { dateStyle: 'long' }).format(date),
                currentMonth: date.getMonth() === this.viewMonth,
                today: dateValue === today,
                disabled: (this.minimum && dateValue < this.minimum) || (this.maximum && dateValue > this.maximum),
            };
        });
    },

    previousMonth() {
        const date = new Date(this.viewYear, this.viewMonth - 1, 1);
        this.viewYear = date.getFullYear();
        this.viewMonth = date.getMonth();
    },

    nextMonth() {
        const date = new Date(this.viewYear, this.viewMonth + 1, 1);
        this.viewYear = date.getFullYear();
        this.viewMonth = date.getMonth();
    },

    selectDate(day) {
        if (day.disabled) return;
        this.value = day.date;
        this.close();
    },

    selectToday() {
        const today = this.formatDate(new Date());
        if ((this.minimum && today < this.minimum) || (this.maximum && today > this.maximum)) return;
        this.value = today;
        this.close();
    },

    clearDate() {
        this.value = '';
        this.close();
    },
}));

Alpine.start();
