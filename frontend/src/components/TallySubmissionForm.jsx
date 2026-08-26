import { useState } from 'react'
import {
  createTallySubmission,
  submitTallySubmission,
  updateTallySubmission,
} from '../services/resultsIngestion.js'

function errorMessage(error, fallback) {
  const validationErrors = error.response?.data?.errors

  if (validationErrors) {
    return Object.values(validationErrors).flat().join(' ')
  }

  return error.response?.data?.message ?? fallback
}

function initialValues(sheet, submission, options) {
  const existingResults = new Map(
    (submission?.results ?? []).map((result) => [
      Number(result.election_option_id),
      result.votes,
    ]),
  )

  return {
    registeredVoters:
      submission?.registered_voters
      ?? sheet.polling_station?.registered_voters
      ?? '',
    invalidBallots: submission?.invalid_ballots ?? 0,
    blankBallots: submission?.blank_ballots ?? 0,
    notes: submission?.notes ?? '',
    votes: Object.fromEntries(
      options.map((option) => [
        option.id,
        existingResults.get(Number(option.id)) ?? 0,
      ]),
    ),
  }
}

export default function TallySubmissionForm({
  entryNumber,
  existingSubmission = null,
  onCancel,
  onSaved,
  sheet,
}) {
  const activeOptions = [...(sheet.contest?.options ?? [])]
    .filter(
      (option) =>
        option.is_active !== false
        && option.option_type !== 'blank',
    )
    .sort(
      (first, second) =>
        first.ballot_order - second.ballot_order,
    )

  const [form, setForm] = useState(() =>
    initialValues(sheet, existingSubmission, activeOptions),
  )
  const [draftSubmission, setDraftSubmission] = useState(
    existingSubmission,
  )
  const [clientUuid] = useState(() =>
    existingSubmission?.client_uuid
    ?? window.crypto.randomUUID(),
  )
  const [isSaving, setIsSaving] = useState(false)
  const [submitError, setSubmitError] = useState('')

  const validBallots = activeOptions.reduce(
    (total, option) =>
      total + Number(form.votes[option.id] || 0),
    0,
  )

  const ballotsCast =
    validBallots
    + Number(form.invalidBallots || 0)
    + Number(form.blankBallots || 0)

  function updateField(field, value) {
    setForm((current) => ({
      ...current,
      [field]: value,
    }))
  }

  function updateVotes(optionId, value) {
    setForm((current) => ({
      ...current,
      votes: {
        ...current.votes,
        [optionId]: value,
      },
    }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setIsSaving(true)
    setSubmitError('')

    const payload = {
      registered_voters: Number(form.registeredVoters),
      ballots_cast: ballotsCast,
      valid_ballots: validBallots,
      invalid_ballots: Number(form.invalidBallots),
      blank_ballots: Number(form.blankBallots),
      notes: form.notes.trim() || null,
      entered_at:
        draftSubmission?.entered_at
        ?? new Date().toISOString(),
      results: activeOptions.map((option) => ({
        election_option_id: Number(option.id),
        votes: Number(form.votes[option.id] || 0),
      })),
    }

    try {
      let draft = draftSubmission

      if (draft) {
        draft = await updateTallySubmission(
          draft.id,
          payload,
        )
      } else {
        draft = await createTallySubmission(sheet.id, {
          ...payload,
          client_uuid: clientUuid,
          entry_number: entryNumber,
        })
      }

      setDraftSubmission(draft)

      const submitted = await submitTallySubmission(draft.id)
      onSaved(submitted)
    } catch (error) {
      setSubmitError(
        errorMessage(
          error,
          'The tally entry could not be submitted.',
        ),
      )
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="modal-backdrop results-modal-backdrop">
      <section className="modal-card results-modal">
        <div className="modal-header">
          <div>
            <p className="eyebrow">DOUBLE-ENTRY VERIFICATION</p>
            <h2>Record tally entry {entryNumber}</h2>
          </div>

          <button
            aria-label="Close"
            className="modal-close"
            disabled={isSaving}
            onClick={onCancel}
            type="button"
          >
            ×
          </button>
        </div>

        <p>
          Enter the official counts independently. Submitted
          entries become immutable and cannot be edited later.
        </p>

        <div className="info-banner">
          <strong>{sheet.reference_code}</strong>
          <br />
          {sheet.polling_center?.name_en} — Station{' '}
          {sheet.polling_station?.station_number}
        </div>

        {submitError && (
          <div className="form-error-banner">
            {submitError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="modal-form-grid">
            <label className="form-field">
              <span>Registered voters</span>
              <input
                min="0"
                onChange={(event) =>
                  updateField(
                    'registeredVoters',
                    event.target.value,
                  )
                }
                required
                type="number"
                value={form.registeredVoters}
              />
            </label>

            <label className="form-field">
              <span>Invalid ballots</span>
              <input
                min="0"
                onChange={(event) =>
                  updateField(
                    'invalidBallots',
                    event.target.value,
                  )
                }
                required
                type="number"
                value={form.invalidBallots}
              />
            </label>

            <label className="form-field">
              <span>Blank ballots</span>
              <input
                min="0"
                onChange={(event) =>
                  updateField(
                    'blankBallots',
                    event.target.value,
                  )
                }
                required
                type="number"
                value={form.blankBallots}
              />
            </label>
          </div>

          <div className="results-count-summary">
            <div>
              <span>Valid ballots</span>
              <strong>{validBallots}</strong>
            </div>
            <div>
              <span>Total ballots cast</span>
              <strong>{ballotsCast}</strong>
            </div>
          </div>

          <div className="results-entry-options">
            <div>
              <h3>Votes by ballot option</h3>
              <p>
                The valid-ballot total is calculated automatically.
              </p>
            </div>

            {activeOptions.map((option) => (
              <label
                className="results-entry-option"
                key={option.id}
              >
                <span>
                  <strong>{option.name}</strong>
                  <small>{option.code}</small>
                </span>

                <input
                  aria-label={`Votes for ${option.name}`}
                  min="0"
                  onChange={(event) =>
                    updateVotes(option.id, event.target.value)
                  }
                  required
                  type="number"
                  value={form.votes[option.id]}
                />
              </label>
            ))}
          </div>

          <label className="form-field form-field-wide">
            <span>Entry notes (optional)</span>
            <textarea
              onChange={(event) =>
                updateField('notes', event.target.value)
              }
              placeholder="Record the source document or any relevant observations."
              rows="4"
              value={form.notes}
            />
          </label>

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
                isSaving || activeOptions.length === 0
              }
              type="submit"
            >
              {isSaving
                ? 'Submitting...'
                : `Submit entry ${entryNumber}`}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}