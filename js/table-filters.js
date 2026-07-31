(function () {
    const VIEWPORT_PADDING = 8;
    const POPUP_GAP = 8;
    let activeFilter = null;
    let pendingReposition = false;

    function getPopup(col) {
        return document.getElementById(`filter-popup-${col}`);
    }

    function closeAllFilterPopups() {
        document.querySelectorAll('.filter-popup').forEach(popup => {
            popup.classList.remove('active');
        });
        activeFilter = null;
    }

    function setPopupPosition(iconElement, popup) {
        const header = iconElement.closest('th');
        if (!header || !popup) return;

        if (popup.parentElement !== document.body) {
            document.body.appendChild(popup);
        }

        const headerRect = header.getBoundingClientRect();
        const popupWidth = popup.offsetWidth || 220;
        const popupHeight = popup.offsetHeight || 0;
        const spaceAbove = headerRect.top;
        const placeBelow = spaceAbove < popupHeight + POPUP_GAP + VIEWPORT_PADDING;

        let viewportTop = placeBelow
            ? headerRect.bottom + POPUP_GAP
            : headerRect.top - popupHeight - POPUP_GAP;
        const maxTop = Math.max(VIEWPORT_PADDING, window.innerHeight - popupHeight - VIEWPORT_PADDING);
        viewportTop = Math.max(VIEWPORT_PADDING, Math.min(viewportTop, maxTop));

        let viewportLeft = headerRect.left;
        const maxLeft = Math.max(VIEWPORT_PADDING, window.innerWidth - popupWidth - VIEWPORT_PADDING);

        if (viewportLeft < VIEWPORT_PADDING) {
            viewportLeft = VIEWPORT_PADDING;
        } else if (viewportLeft > maxLeft) {
            viewportLeft = maxLeft;
        }

        popup.style.position = 'absolute';
        popup.style.top = `${window.scrollY + viewportTop}px`;
        popup.style.bottom = 'auto';
        popup.style.left = `${window.scrollX + viewportLeft}px`;
    }

    function scheduleActivePopupPosition() {
        if (!activeFilter || pendingReposition) return;
        pendingReposition = true;
        requestAnimationFrame(() => {
            pendingReposition = false;
            if (activeFilter && activeFilter.popup.classList.contains('active')) {
                setPopupPosition(activeFilter.iconElement, activeFilter.popup);
            }
        });
    }

    function createFilterIcon(col) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'filter-icon');
        svg.setAttribute('data-col', col);
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('style', 'width: 16px; height: 16px; cursor: pointer; opacity: 0.6;');
        svg.setAttribute('onclick', 'toggleFilterPopup(this, event)');
        svg.innerHTML = '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>';
        return svg;
    }

    function createFilterPopup(col, placeholder) {
        const popup = document.createElement('div');
        popup.className = 'filter-popup';
        popup.id = `filter-popup-${col}`;
        popup.innerHTML = `
            <div class="filter-popup-label">Show items with value that:</div>
            <select class="filter-popup-select" id="filter-condition-${col}">
                <option value="contains">Contains</option>
                <option value="equals">Equals</option>
                <option value="starts_with">Starts with</option>
            </select>
            <input type="text" class="filter-popup-input" id="filter-input-${col}" placeholder="${placeholder || 'Search...'}">
            <div class="filter-popup-buttons">
                <button class="filter-btn-filter" onclick="applyPopupFilter('${col}')">Filter</button>
                <button class="filter-btn-clear" onclick="clearPopupFilter('${col}')">Clear</button>
            </div>
        `;
        return popup;
    }

    function initializeSharedTableFilters() {
        document.querySelectorAll('th[data-filter-col]').forEach(header => {
            const col = header.dataset.filterCol;
            if (!col || header.querySelector('.filter-icon')) return;

            const label = header.textContent.trim();
            const placeholder = header.dataset.filterPlaceholder || 'Search...';
            header.textContent = '';

            const wrapper = document.createElement('div');
            wrapper.setAttribute('style', 'display: flex; align-items: center; gap: 6px;');

            const labelSpan = document.createElement('span');
            labelSpan.textContent = label;
            wrapper.appendChild(labelSpan);
            wrapper.appendChild(createFilterIcon(col));

            header.appendChild(wrapper);
            header.appendChild(createFilterPopup(col, placeholder));
        });
    }

    function getPopupFilters() {
        const filters = {};
        if (!window.popupFilters) return filters;

        for (const [col, filterData] of Object.entries(window.popupFilters)) {
            if (filterData.value) {
                filters[col] = {
                    condition: filterData.condition || 'contains',
                    value: String(filterData.value).toLowerCase().trim()
                };
            }
        }
        return filters;
    }

    function formatFilterValue(item, col, formatters) {
        if (formatters && typeof formatters[col] === 'function') {
            return String(formatters[col](item) || '').toLowerCase();
        }
        return String(item[col] || '').toLowerCase();
    }

    function matchesFilterValue(fieldValue, filterData) {
        const value = filterData.value;
        if (filterData.condition === 'equals') return fieldValue === value;
        if (filterData.condition === 'starts_with') return fieldValue.startsWith(value);
        return fieldValue.includes(value);
    }

    window.toggleFilterPopup = function (iconElement, event) {
        const evt = event || window.event;
        if (evt) {
            evt.stopPropagation();
            evt.preventDefault();
        }

        const col = iconElement.dataset.col;
        const popup = getPopup(col);
        if (!popup) return;

        const wasActive = popup.classList.contains('active');
        closeAllFilterPopups();
        if (wasActive) return;

        popup.classList.add('active');
        activeFilter = { iconElement, popup };
        setPopupPosition(iconElement, popup);
    };

    window.applyPopupFilter = function (col) {
        const condition = document.getElementById(`filter-condition-${col}`).value;
        const input = document.getElementById(`filter-input-${col}`);

        if (!window.popupFilters) window.popupFilters = {};
        window.popupFilters[col] = {
            condition,
            value: input.value.toLowerCase().trim()
        };

        closeAllFilterPopups();
        if (typeof window.applyColumnFilters === 'function') {
            window.applyColumnFilters();
        }
    };

    window.clearPopupFilter = function (col) {
        const input = document.getElementById(`filter-input-${col}`);
        const conditionSelect = document.getElementById(`filter-condition-${col}`);

        if (input) input.value = '';
        if (conditionSelect) conditionSelect.value = 'contains';
        if (window.popupFilters && window.popupFilters[col]) {
            delete window.popupFilters[col];
        }

        closeAllFilterPopups();
        if (typeof window.applyColumnFilters === 'function') {
            window.applyColumnFilters();
        }
    };

    window.getSharedPopupFilters = getPopupFilters;
    window.matchesSharedPopupFilters = function (item, formatters) {
        const filters = getPopupFilters();
        for (const [col, filterData] of Object.entries(filters)) {
            const fieldValue = formatFilterValue(item, col, formatters);
            if (!matchesFilterValue(fieldValue, filterData)) return false;
        }
        return true;
    };

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.filter-icon') && !event.target.closest('.filter-popup')) {
            closeAllFilterPopups();
        }
    });

    document.addEventListener('scroll', scheduleActivePopupPosition, true);
    window.addEventListener('resize', scheduleActivePopupPosition);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSharedTableFilters);
    } else {
        initializeSharedTableFilters();
    }
})();
