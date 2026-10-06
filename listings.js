(() => {
    const filterForm = document.querySelector('#listing-filters');
    const listingGrid = document.querySelector('.listing-grid');

    if (!filterForm || !listingGrid) {
        return;
    }

    const filters = Array.from(filterForm.querySelectorAll('[data-filter]'));
    const cards = Array.from(listingGrid.querySelectorAll('[data-listing]'));
    const resultCount = document.querySelector('#filter-result-count');
    const emptyState = document.querySelector('#listing-empty');
    const clearButton = filterForm.querySelector('[data-clear-filters]');

    function updateListings(animateMatches = false) {
        const selectedFilters = filters.map((filter) => ({
            key: filter.dataset.filter,
            value: filter.value,
        }));
        let visibleCount = 0;

        cards.forEach((card) => {
            const matches = selectedFilters.every(({ key, value }) => (
                value === '' || card.dataset[key] === value
            ));

            if (!matches) {
                card.hidden = true;
                card.classList.remove('is-fading-in');
                return;
            }

            visibleCount += 1;
            if (animateMatches || card.hidden) {
                card.classList.remove('is-fading-in');
                card.hidden = false;
                void card.offsetWidth;
                card.classList.add('is-fading-in');
            }
        });

        resultCount.textContent = `${visibleCount} of ${cards.length} ${cards.length === 1 ? 'place' : 'places'}`;
        emptyState.hidden = visibleCount !== 0;
    }

    filterForm.addEventListener('change', () => updateListings(true));
    clearButton.addEventListener('click', () => {
        filters.forEach((filter) => {
            filter.value = '';
        });

        document.querySelectorAll('.search-form select').forEach((select) => {
            select.value = '';
        });

        updateListings(true);
    });

    updateListings();
})();
