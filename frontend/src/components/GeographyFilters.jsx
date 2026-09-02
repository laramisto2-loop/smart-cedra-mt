function GeographyFilters({
  searchLabel,
  searchPlaceholder,
  searchDraft,
  onSearchDraftChange,
  onSubmit,
  onClear,
  filterLabel = '',
  filterValue = '',
  onFilterChange = null,
  filterOptions = [],
}) {
  const hasParentFilter = Boolean(filterLabel)

  return (
    <article className="content-card geography-filter-card">
      <form
        className={`geography-filter-form ${
          hasParentFilter ? '' : 'search-only'
        }`}
        onSubmit={onSubmit}
      >
        <label className="form-field geography-search-field">
          <span>{searchLabel}</span>
          <input
            type="search"
            value={searchDraft}
            onChange={(event) =>
              onSearchDraftChange(event.target.value)
            }
            maxLength="100"
            placeholder={searchPlaceholder}
          />
        </label>

        {hasParentFilter && (
          <label className="form-field geography-parent-filter">
            <span>{filterLabel}</span>
            <select
              value={filterValue}
              onChange={onFilterChange}
            >
              {filterOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>
        )}

        <button type="submit" className="primary-button">
          Search
        </button>

        <button
          type="button"
          className="secondary-button"
          onClick={onClear}
        >
          Clear filters
        </button>
      </form>
    </article>
  )
}

export default GeographyFilters
