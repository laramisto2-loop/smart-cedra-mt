function formatDateTime(value) {
  if (!value) {
    return 'Not available'
  }

  return new Intl.DateTimeFormat('en-LB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatPercentage(value) {
  return value === null || value === undefined
    ? 'Not available'
    : `${Number(value).toFixed(2)}%`
}

function TurnoutSeries({
  series,
  selectedCenter,
  selectedStation,
  isLoading,
  error,
}) {
  if (!selectedCenter) {
    return (
      <section className="turnout-series-panel">
        <div className="card-heading">
          <div>
            <p className="eyebrow">Time-series summary</p>
            <h3>Select a polling center</h3>
          </div>
        </div>

        <p className="empty-state-copy">
          Choose a polling center to view how its aggregate turnout
          changes over time.
        </p>
      </section>
    )
  }

  if (isLoading) {
    return (
      <section className="turnout-series-panel">
        <p className="loading-message">
          Loading turnout series...
        </p>
      </section>
    )
  }

  if (error) {
    return (
      <section className="turnout-series-panel">
        <div className="error-message" role="alert">
          {error}
        </div>
      </section>
    )
  }

  const snapshots = series?.data ?? []
  const meta = series?.meta ?? {}
  const maximumCount = Math.max(
    1,
    ...snapshots.map(
      (snapshot) => Number(snapshot.turnout_count),
    ),
  )

  const chartPoints = snapshots
    .map((snapshot, index) => {
      const x =
        snapshots.length === 1
          ? 50
          : (index / (snapshots.length - 1)) * 100
      const y =
        36
        - (Number(snapshot.turnout_count) / maximumCount)
          * 30

      return `${x},${y}`
    })
    .join(' ')

  const locationLabel = selectedStation
    ? `${selectedCenter.name_en} — Station ${selectedStation.station_number}`
    : `${selectedCenter.name_en} — Entire center`

  return (
    <section className="turnout-series-panel">
      <div className="card-heading">
        <div>
          <p className="eyebrow">Time-series summary</p>
          <h3>{locationLabel}</h3>
        </div>

        <span className="active-pill">
          {meta.points_count ?? 0} snapshots
        </span>
      </div>

      <div className="turnout-summary-grid">
        <article>
          <span>Latest turnout</span>
          <strong>
            {meta.latest_turnout_count ?? '—'}
          </strong>
        </article>

        <article>
          <span>Registered voters</span>
          <strong>
            {meta.registered_voters ?? '—'}
          </strong>
        </article>

        <article>
          <span>Turnout percentage</span>
          <strong>
            {formatPercentage(meta.turnout_percentage)}
          </strong>
        </article>

        <article>
          <span>Change from previous</span>
          <strong>
            {meta.change_since_previous === null
              || meta.change_since_previous === undefined
              ? '—'
              : `${
                  meta.change_since_previous >= 0 ? '+' : ''
                }${meta.change_since_previous}`}
          </strong>
        </article>
      </div>

      {snapshots.length === 0 ? (
        <div className="empty-state compact-empty-state">
          <h3>No turnout history found</h3>
          <p>
            Record the first aggregate snapshot for this location.
          </p>
        </div>
      ) : (
        <>
          <div
            className="turnout-chart"
            role="img"
            aria-label={`Aggregate turnout trend for ${locationLabel}`}
          >
            <svg
              viewBox="0 0 100 40"
              preserveAspectRatio="none"
              aria-hidden="true"
            >
              <line
                x1="0"
                y1="36"
                x2="100"
                y2="36"
                className="turnout-chart-axis"
              />

              <polyline
                points={chartPoints}
                className="turnout-chart-line"
              />

              {snapshots.map((snapshot, index) => {
                const x =
                  snapshots.length === 1
                    ? 50
                    : (
                        index
                        / (snapshots.length - 1)
                      ) * 100
                const y =
                  36
                  - (
                    Number(snapshot.turnout_count)
                    / maximumCount
                  ) * 30

                return (
                  <circle
                    key={snapshot.id}
                    cx={x}
                    cy={y}
                    r="1.4"
                    className="turnout-chart-point"
                  />
                )
              })}
            </svg>
          </div>

          <div className="table-wrapper">
            <table className="geography-table turnout-series-table">
              <thead>
                <tr>
                  <th>Captured</th>
                  <th>Turnout</th>
                  <th>Registered</th>
                  <th>Percentage</th>
                  <th>Reporter</th>
                </tr>
              </thead>

              <tbody>
                {[...snapshots].reverse().map((snapshot) => (
                  <tr key={snapshot.id}>
                    <td>
                      {formatDateTime(snapshot.captured_at)}
                    </td>
                    <td>
                      <strong>{snapshot.turnout_count}</strong>
                    </td>
                    <td>
                      {snapshot.registered_voters ?? '—'}
                    </td>
                    <td>
                      {formatPercentage(
                        snapshot.turnout_percentage,
                      )}
                    </td>
                    <td>
                      {snapshot.reporter?.name ?? 'Former user'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </section>
  )
}

export default TurnoutSeries