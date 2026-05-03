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
const menuClass = 'absolute left-0 right-0 top-[calc(100%+6px)] z-30 grid gap-1 rounded-lg border border-[var(--border)] bg-white p-1.5 shadow-[0_18px_44px_rgba(15,23,42,.14)] dark:bg-slate-900 dark:shadow-[0_20px_70px_rgba(0,0,0,.38)]';
const optionClass = 'block min-h-8 w-full rounded-md px-2.5 text-left text-sm text-[var(--text)] hover:bg-surface-soft';
const compactTriggerClass = 'h-8 min-h-8 rounded-md px-2.5 text-[13px]';
const compactMenuClass = 'gap-0.5 rounded-md p-1';
const compactOptionClass = 'min-h-7 rounded px-2 py-1 text-[13px]';
const selectedOptionClass = 'bg-surface-soft';
const disabledOptionClass = 'cursor-not-allowed opacity-50';
const placeholderClass = 'text-slate-400';

export function initStyledSelect(selectElement, options = {}) {
    if (!(selectElement instanceof HTMLSelectElement)) {
        return null;
    }

    if (selectElement.dataset.styledSelectInit === 'true') {
        return {
            select: selectElement,
            destroy: () => {},
        };
    }

    const select = selectElement;
    select.dataset.styledSelectInit = 'true';

    const wrapper = document.createElement('div');
    wrapper.className = wrapperClass;

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = triggerClass;
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const menu = document.createElement('div');
    menu.className = `${menuClass} hidden`;
    menu.setAttribute('role', 'listbox');
    const compact = select.dataset.styledSelectDensity === 'compact' || options.density === 'compact';
    if (compact) {
        trigger.classList.add(...compactTriggerClass.split(' '));
        menu.classList.add(...compactMenuClass.split(' '));
        Object.assign(trigger.style, {
            fontSize: '13px',
            height: '32px',
            minHeight: '32px',
            paddingLeft: '10px',
            paddingRight: '10px',
        });
    }

    const closeMenu = () => {
        menu.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
        menu.removeAttribute('style');
        if (menu.parentNode !== wrapper) {
            wrapper.appendChild(menu);
        }
    };

    const positionMenu = () => {
        if (menu.classList.contains('hidden')) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const viewportGap = 10;
        const availableBelow = window.innerHeight - rect.bottom - viewportGap;
        const availableAbove = rect.top - viewportGap;
        const openAbove = availableBelow < 180 && availableAbove > availableBelow;
        const maxHeight = Math.max(120, Math.floor((openAbove ? availableAbove : availableBelow) - 8));

        Object.assign(menu.style, {
            left: `${Math.round(rect.left)}px`,
            maxHeight: `${maxHeight}px`,
            overflowY: 'auto',
            position: 'fixed',
            right: 'auto',
            top: `${Math.round(openAbove ? rect.top - Math.min(menu.scrollHeight, maxHeight) - 7 : rect.bottom + 7)}px`,
            width: `${Math.round(rect.width)}px`,
            zIndex: '1200',
        });
    };

    const openMenu = () => {
        document.body.appendChild(menu);
        menu.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        positionMenu();
    };

    const updateLabel = () => {
        const selected = select.options[select.selectedIndex] ?? null;
        const fallback = options.placeholder ?? '';
        trigger.textContent = selected ? optionLabel(selected) : fallback;
        trigger.classList.toggle(placeholderClass, !selected);
    };

    const rebuildOptions = () => {
        menu.innerHTML = '';
        Array.from(select.options).forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = optionClass;
            if (compact) {
                item.classList.add(...compactOptionClass.split(' '));
                Object.assign(item.style, {
                    fontSize: '13px',
                    minHeight: '28px',
                    padding: '4px 8px',
                });
            }
            item.setAttribute('role', 'option');
            item.dataset.value = String(option.value ?? '');
            item.innerHTML = escapeHtml(optionLabel(option));
            item.classList.toggle(selectedOptionClass, option.selected);

            if (option.disabled) {
                item.disabled = true;
                item.classList.add(...disabledOptionClass.split(' '));
            }

            item.addEventListener('click', () => {
                if (option.disabled) {
                    return;
                }
                select.value = String(option.value ?? '');
                select.dispatchEvent(new Event('change', {bubbles: true}));
                rebuildOptions();
                updateLabel();
                closeMenu();
            });

            menu.appendChild(item);
        });
    };

    select.classList.add(nativeClass);
    const parent = select.parentNode;
    if (!parent) {
        return null;
    }

    parent.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    wrapper.appendChild(trigger);
    wrapper.appendChild(menu);

    rebuildOptions();
    updateLabel();

    trigger.addEventListener('click', () => {
        const isOpen = !menu.classList.contains('hidden');
        if (isOpen) {
            closeMenu();
            return;
        }
        openMenu();
    });

    select.addEventListener('change', () => {
        updateLabel();
        rebuildOptions();
    });

    const onDocumentClick = (event) => {
        const target = event.target;
        if (!(target instanceof Node)) {
            return;
        }
        if (!wrapper.contains(target)) {
            if (menu.contains(target)) {
                return;
            }
            closeMenu();
        }
    };

    const onEscape = (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    };

    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
    window.addEventListener('resize', positionMenu);
    window.addEventListener('scroll', positionMenu, true);

    return {
        select,
        wrapper,
        destroy() {
            document.removeEventListener('click', onDocumentClick);
            document.removeEventListener('keydown', onEscape);
            window.removeEventListener('resize', positionMenu);
            window.removeEventListener('scroll', positionMenu, true);
            closeMenu();
            select.classList.remove(nativeClass);
            if (wrapper.parentNode) {
                wrapper.parentNode.insertBefore(select, wrapper);
                wrapper.parentNode.removeChild(wrapper);
            }
            delete select.dataset.styledSelectInit;
        },
    };
}

export function initStyledSelects(selector, options = {}) {
    if (!selector) {
        return [];
    }

    const elements = typeof selector === 'string'
        ? document.querySelectorAll(selector)
        : selector;

    return Array.from(elements)
        .map((element) => initStyledSelect(element, options))
        .filter(Boolean);
}
