function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function optionLabel(option) {
    return String(option?.textContent ?? option?.label ?? '').trim();
}

const nativeClass = 'hidden';
const wrapperClass = 'relative w-full';
const triggerClass = 'flex h-11 min-h-11 w-full items-center justify-between gap-2 rounded-lg border border-[var(--border)] bg-admin-field px-3 text-left text-sm text-[var(--text)] transition after:h-[7px] after:w-[7px] after:shrink-0 after:rotate-45 after:border-b-2 after:border-r-2 after:border-current after:content-[\'\'] focus:border-accent focus:bg-admin-field-focus focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--admin-page-accent)_18%,transparent)] focus:outline-none';
const placeholderClass = 'text-slate-400';
const labelClass = 'flex min-w-0 items-center gap-1 overflow-hidden';
const chipClass = 'inline-flex min-h-6 max-w-36 items-center gap-1.5 overflow-hidden rounded-full px-2 py-0.5 text-xs font-bold text-accent [background:color-mix(in_srgb,var(--admin-page-accent)_14%,transparent)] [text-overflow:ellipsis] whitespace-nowrap';
const menuClass = 'absolute left-0 right-0 top-[calc(100%+6px)] z-30 grid gap-1 rounded-lg border border-[var(--border)] bg-white p-1.5 shadow-[0_18px_44px_rgba(15,23,42,.14)] dark:bg-slate-900 dark:shadow-[0_20px_70px_rgba(0,0,0,.38)]';
const searchClass = 'min-h-[38px] w-full rounded-md border border-[var(--border)] bg-admin-field px-2.5 py-2 text-sm text-[var(--text)] outline-none focus:border-accent focus:bg-admin-field-focus';
const listClass = 'mt-1.5 grid max-h-60 gap-1 overflow-auto';
const optionClass = 'flex min-h-[34px] cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 hover:bg-surface-soft';
const selectedOptionClass = 'bg-surface-soft';
const checkboxClass = 'm-0 grid h-[17px] w-[17px] appearance-none place-items-center rounded-[5px] border border-[color-mix(in_srgb,var(--border)_85%,currentColor_15%)] checked:border-accent checked:bg-accent';
const checkClass = 'pointer-events-none relative -ml-[26px] h-[17px] w-[17px] text-white before:absolute before:left-[6px] before:top-0.5 before:h-[9px] before:w-[5px] before:rotate-45 before:border-b-2 before:border-r-2 before:border-current before:opacity-0 before:content-[\'\'] peer-checked:before:opacity-100';
const optionLabelClass = 'min-w-0 overflow-hidden text-ellipsis whitespace-nowrap text-sm font-semibold';
const emptyClass = 'p-2.5 text-[13px] text-slate-500';

export function initMultiSelect(selectElement) {
    if (!(selectElement instanceof HTMLSelectElement) || !selectElement.multiple) {
        return null;
    }

    if (selectElement.dataset.multiSelectInit === 'true') {
        return {select: selectElement, destroy: () => {}};
    }

    const select = selectElement;
    select.dataset.multiSelectInit = 'true';

    const wrapper = document.createElement('div');
    wrapper.className = wrapperClass;

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = triggerClass;
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const label = document.createElement('span');
    label.className = labelClass;

    const menu = document.createElement('div');
    menu.className = `${menuClass} hidden`;
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-multiselectable', 'true');

    const search = document.createElement('input');
    search.type = 'search';
    search.className = searchClass;
    search.placeholder = select.dataset.searchPlaceholder || 'Search...';

    const list = document.createElement('div');
    list.className = listClass;

    const empty = document.createElement('div');
    empty.className = `${emptyClass} hidden`;
    empty.textContent = select.dataset.emptyLabel || 'No options found';

    menu.append(search, list, empty);
    trigger.append(label);

    const selectedOptions = () => Array.from(select.selectedOptions);

    const updateLabel = () => {
        const selected = selectedOptions();
        if (selected.length === 0) {
            label.textContent = select.dataset.placeholder || '';
            trigger.classList.add(placeholderClass);
            return;
        }

        trigger.classList.remove(placeholderClass);
        label.innerHTML = selected.slice(0, 3)
            .map((option) => `<span class="${chipClass}">${escapeHtml(optionLabel(option))}</span>`)
            .join('');

        if (selected.length > 3) {
            label.insertAdjacentHTML('beforeend', `<span class="${chipClass} max-w-none">+${selected.length - 3}</span>`);
        }
    };

    const syncSelect = () => {
        select.dispatchEvent(new Event('change', {bubbles: true}));
        updateLabel();
    };

    const rebuildList = () => {
        const query = search.value.trim().toLowerCase();
        let visible = 0;
        list.innerHTML = '';

        Array.from(select.options).forEach((option) => {
            const text = optionLabel(option);
            if (query !== '' && !text.toLowerCase().includes(query) && !String(option.value).toLowerCase().includes(query)) {
                return;
            }

            visible += 1;
            const item = document.createElement('label');
            item.className = optionClass;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            item.classList.toggle(selectedOptionClass, option.selected);

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = `peer ${checkboxClass}`;
            checkbox.checked = option.selected;
            checkbox.disabled = option.disabled;

            const mark = document.createElement('span');
            mark.className = checkClass;
            mark.setAttribute('aria-hidden', 'true');

            const copy = document.createElement('span');
            copy.className = optionLabelClass;
            copy.textContent = text;

            checkbox.addEventListener('change', () => {
                option.selected = checkbox.checked;
                item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                item.classList.toggle(selectedOptionClass, option.selected);
                syncSelect();
            });

            item.append(checkbox, mark, copy);
            list.append(item);
        });

        empty.classList.toggle('hidden', visible > 0);
    };

    const closeMenu = () => {
        menu.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
    };

    const openMenu = () => {
        rebuildList();
        menu.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        search.focus();
    };

    select.classList.add(nativeClass);
    const parent = select.parentNode;
    if (!parent) {
        return null;
    }

    parent.insertBefore(wrapper, select);
    wrapper.append(select, trigger, menu);

    updateLabel();
    rebuildList();

    trigger.addEventListener('click', () => {
        if (menu.classList.contains('hidden')) {
            openMenu();
            return;
        }
        closeMenu();
    });

    search.addEventListener('input', rebuildList);
    select.addEventListener('change', () => {
        updateLabel();
        rebuildList();
    });

    const onDocumentClick = (event) => {
        const target = event.target;
        if (target instanceof Node && !wrapper.contains(target)) {
            closeMenu();
        }
    };

    const onEscape = (event) => {
        if (event.key === 'Escape') {
            closeMenu();
            trigger.focus();
        }
    };

    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);

    return {
        select,
        wrapper,
        destroy() {
            document.removeEventListener('click', onDocumentClick);
            document.removeEventListener('keydown', onEscape);
            select.classList.remove(nativeClass);
            if (wrapper.parentNode) {
                wrapper.parentNode.insertBefore(select, wrapper);
                wrapper.parentNode.removeChild(wrapper);
            }
            delete select.dataset.multiSelectInit;
        },
    };
}

export function initMultiSelects(selector) {
    const elements = typeof selector === 'string' ? document.querySelectorAll(selector) : selector;
    return Array.from(elements || [])
        .map((element) => initMultiSelect(element))
        .filter(Boolean);
}
