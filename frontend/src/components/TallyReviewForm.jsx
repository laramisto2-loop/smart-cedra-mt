import { useState } from 'react'
import {
  approveTallySheet,
  rejectTallySheet,
  reviewTallySheet,
} from '../services/resultsIngestion.js'

const actionContent = {
  review: {
    eyebrow: 'RESULTS REVIEW',
    title: 'Review tally sheet',
    description:
      'Confirm the independently entered results and record any reconciliation notes.',
    notesLabel: 'Review notes (optional)',
    notesPlaceholder:
      'Record the checks performed or any reconciliation observations.',
    submitLabel: 'Save review',
    savingLabel: 'Saving review...',
  },
  approve: {
    eyebrow: 'RESULTS APPROVAL',
    title: 'Approve tally sheet',
    description:
      'Approve the verified submission as the official result for this polling station.',
    notesLabel: 'Approval notes (optional)',
    notesPlaceholder:
      'Record any final approval observations.',
    submitLabel: 'Approve results',
    savingLabel: 'Approving...',
  },
  reject: {
    eyebrow: 'RESULTS REVIEW',
    title: 'Reject tally sheet',
    description:
      'Reject this tally sheet and explain what must be corrected.',
    notesLabel: 'Rejection reason',
    notesPlaceholder:
      'Explain why this tally sheet is being rejected.',
    submitLabel: 'Reject tally sheet',
    savingLabel: 'Rejecting...',
  },
}

export default function TallyReviewForm({
  action,
  onCancel,
  onSaved,
  sheet,
}) {
  const submittedEntries = (sheet.submissions ?? []).filter(
    (submission) => submission.status === 'submitted',
  )

  const [submissionId, setSubmissionId] = useState(() =>
    String(
      sheet.approved_submission_id
        ?? submittedEntries[0]?.id
        ?? '',
    ),
  )
  const [notes, setNotes] = useState('')
  const [errors, setErrors] = useState({})
  const [submitError, setSubmitError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  const content = actionContent[action]
  const requiresSubmission =
    action === 'review' && sheet.status === 'discrepancy'
  const requiresNotes = action === 'reject'

  async function handleSubmit(event) {
    event.preventDefault()

    setErrors({})
    setSubmitError('')
    setIsSaving(true)

    try {
      let updatedSheet

      if (action === 'reject') {
        updatedSheet = await rejectTallySheet(sheet.id, {
          reason: notes.trim(),
        })
      } else {
        const payload = {
          notes: notes.trim() || null,
        }

        if (submissionId) {
          payload.submission_id = Number(submissionId)
        }

        updatedSheet =
          action === 'approve'
            ? await approveTallySheet(sheet.id, payload)
            : await reviewTallySheet(sheet.id, payload)
      }

      await onSaved(updatedSheet)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      }

      setSubmitError(
        requestError.response?.data?.message
          ?? 'The review decision could not be saved.',
      )
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="modal-backdrop results-modal-backdrop">
      <section
        aria-labelledby="tally-review-title"
        aria-modal="true"
        className="modal-card"
        role="dialog"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">{content.eyebrow}</p>
            <h3 id="tally-review-title">{content.title}</h3>
          </div>

          <button
            aria-label="Close review form"
            className="modal-close"
            disabled={isSaving}
            onClick={onCancel}
            type="button"
          >
            ×
          </button>
        </div>

        <p className="page-description">
          {sheet.reference_code}
        </p>

        <div className="form-message">
          {content.description}
        </div>

        {submitError && (
          <div className="error-message" role="alert">
            {submitError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          {action !== 'reject' && (
            <label className="form-field">
              <span>
                Official tally entry
                {requiresSubmission ? '' : ' (optional)'}
              </span>

              <select
                onChange={(event) => {
                  setSubmissionId(event.target.value)
                  setErrors({})
                }}
                required={requiresSubmission}
                value={submissionId}
              >
                <option value="">Select an entry</option>

                {submittedEntries.map((submission) => (
                  <option
                    key={submission.id}
                    value={submission.id}
                  >
                    Entry {submission.entry_number} —{' '}
                    {submission.entrant?.name ?? 'Unknown user'}
                  </option>
                ))}
              </select>

              {errors.submission_id && (
                <small className="field-error">
                  {errors.submission_id[0]}
                </small>
              )}
            </label>
          )}

          <label className="form-field">
            <span>{content.notesLabel}</span>

            <textarea
              maxLength="5000"
              onChange={(event) => {
                setNotes(event.target.value)
                setErrors({})
              }}
              placeholder={content.notesPlaceholder}
              required={requiresNotes}
              rows="5"
              value={notes}
            />

            {(errors.notes || errors.reason) && (
              <small className="field-error">
                {(errors.notes ?? errors.reason)[0]}
              </small>
            )}
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
              className={
                action === 'reject'
                  ? 'delete-button'
                  : 'primary-button'
              }
              disabled={
                isSaving
                || (requiresNotes && !notes.trim())
                || (requiresSubmission && !submissionId)
              }
              type="submit"
            >
              {isSaving
                ? content.savingLabel
                : content.submitLabel}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}