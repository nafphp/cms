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
    wrapper.className = 'wow-select';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'wow-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const menu = document.createElement('div');
    menu.className = 'wow-select-menu hidden';
    menu.setAttribute('role', 'listbox');

    const closeMenu = () => {
        menu.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
    };

    const openMenu = () => {
        menu.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
    };

    const updateLabel = () => {
        const selected = select.options[select.selectedIndex] ?? null;
        const fallback = options.placeholder ?? '';
        trigger.textContent = selected ? optionLabel(selected) : fallback;
        trigger.classList.toggle('is-placeholder', !selected);
    };

    const rebuildOptions = () => {
        menu.innerHTML = '';
        Array.from(select.options).forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'wow-select-option';
            item.setAttribute('role', 'option');
            item.dataset.value = String(option.value ?? '');
            item.innerHTML = escapeHtml(optionLabel(option));
            item.classList.toggle('is-selected', option.selected);

            if (option.disabled) {
                item.disabled = true;
                item.classList.add('is-disabled');
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

    select.classList.add('wow-select-native');
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

    return {
        select,
        wrapper,
        destroy() {
            document.removeEventListener('click', onDocumentClick);
            document.removeEventListener('keydown', onEscape);
            select.classList.remove('wow-select-native');
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

