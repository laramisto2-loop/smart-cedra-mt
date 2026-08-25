import { useMemo, useState } from 'react'
import { createTallySheet } from '../services/resultsIngestion.js'

function errorMessage(error, fallback) {
  return error.response?.data?.message ?? fallback
}

export default function TallySheetForm({
  contests,
  pollingCenters,
  pollingStations,
  onCancel,
  onSaved,
}) {
  const [form, setForm] = useState({
    electionContestId: contests[0]?.id
      ? String(contests[0].id)
      : '',
    pollingCenterId: '',
    pollingStationId: '',
    notes: '',
  })
  const [errors, setErrors] = useState({})
  const [submitError, setSubmitError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  const availableStations = useMemo(
    () =>
      pollingStations.filter(
        (station) =>
          String(station.polling_center_id) ===
          String(form.pollingCenterId),
      ),
    [form.pollingCenterId, pollingStations],
  )

  function updateField(field, value) {
    setForm((current) => ({
      ...current,
      [field]: value,
      ...(field === 'pollingCenterId'
        ? { pollingStationId: '' }
        : {}),
    }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setIsSaving(true)
    setErrors({})
    setSubmitError('')

    try {
      const tallySheet = await createTallySheet({
        election_contest_id: Number(form.electionContestId),
        polling_center_id: Number(form.pollingCenterId),
        polling_station_id: Number(form.pollingStationId),
        notes: form.notes.trim() || null,
      })

      onSaved(tallySheet)
    } catch (error) {
      setErrors(error.response?.data?.errors ?? {})
      setSubmitError(
        errorMessage(error, 'The tally sheet could not be created.'),
      )
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div
      className="modal-backdrop results-modal-backdrop"
      role="presentation"
    >
      <section
        aria-labelledby="tally-sheet-form-title"
        aria-modal="true"
        className="modal-card results-modal"
        role="dialog"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">RESULTS INGESTION</p>
            <h2 id="tally-sheet-form-title">Create tally sheet</h2>
          </div>

          <button
            aria-label="Close"
            className="icon-button"
            disabled={isSaving}
            onClick={onCancel}
            type="button"
          >
            ×
          </button>
        </div>

        <p className="modal-introduction">
          Select an active contest and its polling location. Each contest
          can have only one tally sheet per polling station.
        </p>

        {submitError && (
          <div className="form-error-banner">{submitError}</div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="results-form-grid">
            <label className="form-field form-field-wide">
              <span>Active election contest</span>
              <select
                onChange={(event) =>
                  updateField(
                    'electionContestId',
                    event.target.value,
                  )
                }
                required
                value={form.electionContestId}
              >
                <option value="">Select a contest</option>
                {contests.map((contest) => (
                  <option key={contest.id} value={contest.id}>
                    {contest.name} — {contest.code}
                  </option>
                ))}
              </select>
              {errors.election_contest_id?.map((message) => (
                <small className="field-error" key={message}>
                  {message}
                </small>
              ))}
            </label>

            <label className="form-field">
              <span>Polling center</span>
              <select
                onChange={(event) =>
                  updateField(
                    'pollingCenterId',
                    event.target.value,
                  )
                }
                required
                value={form.pollingCenterId}
              >
                <option value="">Select a center</option>
                {pollingCenters.map((center) => (
                  <option key={center.id} value={center.id}>
                    {center.name_en} — {center.code}
                  </option>
                ))}
              </select>
              {errors.polling_center_id?.map((message) => (
                <small className="field-error" key={message}>
                  {message}
                </small>
              ))}
            </label>

            <label className="form-field">
              <span>Polling station</span>
              <select
                disabled={!form.pollingCenterId}
                onChange={(event) =>
                  updateField(
                    'pollingStationId',
                    event.target.value,
                  )
                }
                required
                value={form.pollingStationId}
              >
                <option value="">
                  {form.pollingCenterId
                    ? 'Select a station'
                    : 'Select a center first'}
                </option>
                {availableStations.map((station) => (
                  <option key={station.id} value={station.id}>
                    Station {station.station_number}
                    {station.name_en
                      ? ` — ${station.name_en}`
                      : ''}
                  </option>
                ))}
              </select>
              {errors.polling_station_id?.map((message) => (
                <small className="field-error" key={message}>
                  {message}
                </small>
              ))}
            </label>

            <label className="form-field form-field-wide">
              <span>Sheet notes (optional)</span>
              <textarea
                onChange={(event) =>
                  updateField('notes', event.target.value)
                }
                placeholder="Add source, custody, or polling-location notes."
                rows="4"
                value={form.notes}
              />
              {errors.notes?.map((message) => (
                <small className="field-error" key={message}>
                  {message}
                </small>
              ))}
            </label>
          </div>

          <div className="modal-actions">
            <button
              className="secondary-button"
              disabled={isSaving}
              onClick={onCancel}
              type="button"
            >
              Cancel
            </button>

            <button
              className="primary-button"
              disabled={
                isSaving ||
                !form.electionContestId ||
                !form.pollingCenterId ||
                !form.pollingStationId
              }
              type="submit"
            >
              {isSaving ? 'Creating...' : 'Create tally sheet'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}