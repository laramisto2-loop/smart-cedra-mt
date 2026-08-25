import {
  useCallback,
  useEffect,
  useState,
} from 'react'
import { getTallySheet } from '../services/resultsIngestion.js'
import TallyReviewForm from './TallyReviewForm.jsx'
import TallySheetAttachments from './TallySheetAttachments.jsx'
import TallySubmissionForm from './TallySubmissionForm.jsx'

function formatLabel(value = '') {
  return value
    .split('_')
    .map(
      (word) =>
        word.charAt(0).toUpperCase() + word.slice(1),
    )
    .join(' ')
}

function formatDateTime(value) {
  if (!value) {
    return 'Not recorded'
  }

  return new Intl.DateTimeFormat('en-LB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

export default function TallySheetDetails({
  onChanged,
  onClose,
  permissions = [],
  sheetId,
  user,
}) {
  const [sheet, setSheet] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [isEntryFormOpen, setIsEntryFormOpen] =
    useState(false)
  const [reviewAction, setReviewAction] = useState(null)

  const loadDetails = useCallback(async () => {
    setIsLoading(true)

    try {
      const loadedSheet = await getTallySheet(sheetId)

      setSheet(loadedSheet)
      setError('')

      return loadedSheet
    } catch (requestError) {
      setError(
        requestError.response?.data?.message
        ?? 'The tally sheet details could not be loaded.',
      )

      return null
    } finally {
      setIsLoading(false)
    }
  }, [sheetId])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadDetails()
    }, 0)

    return () => {
      window.clearTimeout(timer)
    }
  }, [loadDetails])

  const submissions = sheet?.submissions ?? []

  const ownDraft = submissions.find(
    (submission) =>
      Number(submission.entered_by_user_id)
        === Number(user?.id)
      && submission.status === 'draft',
  )

  const ownSubmittedEntry = submissions.find(
    (submission) =>
      Number(submission.entered_by_user_id)
        === Number(user?.id)
      && submission.status === 'submitted',
  )

  const firstEntry = submissions.find(
    (submission) => Number(submission.entry_number) === 1,
  )

  const nextEntryNumber =
    ownDraft?.entry_number
    ?? (firstEntry ? 2 : 1)

  const acceptsEntries = [
    'pending',
    'awaiting_second_entry',
  ].includes(sheet?.status)

  const sameUserWouldEnterTwice =
    acceptsEntries
    && nextEntryNumber === 2
    && Number(firstEntry?.entered_by_user_id)
      === Number(user?.id)

  const canSubmit =
    permissions.includes('results.tallies.submit')
    && acceptsEntries
    && !ownSubmittedEntry
    && !sameUserWouldEnterTwice

  const isReviewable = [
    'ready_for_review',
    'discrepancy',
  ].includes(sheet?.status)

  const canReview =
    permissions.includes('results.tallies.review')
    && isReviewable

  const canApprove =
    permissions.includes('results.tallies.approve')
    && sheet?.status === 'ready_for_review'

  const canReject =
    permissions.includes('results.tallies.approve')
    && isReviewable

  async function refreshAndNotify() {
    const refreshed = await loadDetails()

    if (refreshed) {
      onChanged?.(refreshed)
    }
  }

  async function handleSubmissionSaved() {
    setIsEntryFormOpen(false)
    await refreshAndNotify()
  }

  async function handleAttachmentChanged() {
    await refreshAndNotify()
  }

  function handleReviewSaved(updatedSheet) {
    setReviewAction(null)
    setSheet(updatedSheet)
    setError('')
    onChanged?.(updatedSheet)
  }

  return (
    <div className="modal-backdrop results-modal-backdrop">
      <section className="modal-card results-modal results-details-modal">
        <div className="modal-header">
          <div>
            <p className="eyebrow">TALLY SHEET</p>
            <h2>
              {sheet?.reference_code ?? 'Loading tally sheet'}
            </h2>
          </div>

          <button
            aria-label="Close"
            className="modal-close"
            onClick={onClose}
            type="button"
          >
            X
          </button>
        </div>

        {isLoading ? (
          <div className="empty-state">
            <h3>Loading tally sheet...</h3>
          </div>
        ) : error ? (
          <div className="form-error-banner">{error}</div>
        ) : (
          <>
            <div className="results-detail-heading">
              <span
                className={`status-pill status-${sheet.status}`}
              >
                {formatLabel(sheet.status)}
              </span>

              <span>
                {submissions.length} of 2 entries recorded
              </span>
            </div>

            <div className="results-detail-grid">
              <article>
                <span>Contest</span>
                <strong>{sheet.contest?.name}</strong>
                <small>{sheet.contest?.code}</small>
              </article>

              <article>
                <span>Polling location</span>
                <strong>{sheet.polling_center?.name_en}</strong>
                <small>
                  Station{' '}
                  {sheet.polling_station?.station_number}
                </small>
              </article>

              <article>
                <span>Created by</span>
                <strong>
                  {sheet.creator?.name ?? 'Unknown'}
                </strong>
                <small>{sheet.creator?.email}</small>
              </article>

              <article>
                <span>Updated</span>
                <strong>
                  {formatDateTime(sheet.updated_at)}
                </strong>
              </article>
            </div>

            {sheet.notes && (
              <div className="info-banner">
                <strong>Sheet notes</strong>
                <br />
                {sheet.notes}
              </div>
            )}

            {sheet.reconciliation_notes && (
              <div className="form-error-banner">
                <strong>Reconciliation notes</strong>
                <br />
                {sheet.reconciliation_notes}
              </div>
            )}

            {sameUserWouldEnterTwice && (
              <div className="info-banner">
                The second entry must be recorded by a different
                user. Sign in using another authorized account.
              </div>
            )}

            <TallySheetAttachments
              onChanged={handleAttachmentChanged}
              permissions={permissions}
              sheet={sheet}
            />

            <div className="results-section-heading">
              <div>
                <h3>Double-entry history</h3>
                <p>
                  Submitted entries are immutable and independently
                  attributed.
                </p>
              </div>

              {canSubmit && (
                <button
                  className="primary-button"
                  onClick={() => setIsEntryFormOpen(true)}
                  type="button"
                >
                  {ownDraft
                    ? `Continue entry ${ownDraft.entry_number}`
                    : `Record entry ${nextEntryNumber}`}
                </button>
              )}
            </div>

            {(canReview || canApprove || canReject) && (
              <div className="results-review-panel">
                <div>
                  <h3>Administrative review</h3>
                  <p>
                    Review the two independent entries before
                    approving the official polling-station result.
                  </p>
                </div>

                <div className="results-review-actions">
                  {canReview && (
                    <button
                      className="secondary-button"
                      onClick={() => setReviewAction('review')}
                      type="button"
                    >
                      {sheet.reviewed_at
                        ? 'Update review'
                        : 'Mark reviewed'}
                    </button>
                  )}

                  {canReject && (
                    <button
                      className="delete-button"
                      onClick={() => setReviewAction('reject')}
                      type="button"
                    >
                      Reject
                    </button>
                  )}

                  {canApprove && (
                    <button
                      className="primary-button"
                      onClick={() => setReviewAction('approve')}
                      type="button"
                    >
                      Approve results
                    </button>
                  )}
                </div>
              </div>
            )}

            {submissions.length === 0 ? (
              <div className="empty-state">
                <h3>No tally entries recorded</h3>
                <p>
                  Record the first independent tally entry.
                </p>
              </div>
            ) : (
              <div className="table-scroll">
                <table className="data-table results-data-table">
                  <thead>
                    <tr>
                      <th>Entry</th>
                      <th>Reference</th>
                      <th>Status</th>
                      <th>Entered by</th>
                      <th>Registered</th>
                      <th>Cast</th>
                      <th>Valid</th>
                      <th>Invalid</th>
                      <th>Blank</th>
                      <th>Submitted</th>
                    </tr>
                  </thead>

                  <tbody>
                    {submissions.map((submission) => (
                      <tr key={submission.id}>
                        <td>
                          <strong>
                            Entry {submission.entry_number}
                          </strong>
                        </td>

                        <td>{submission.reference_code}</td>

                        <td>
                          <span
                            className={`status-pill status-${submission.status}`}
                          >
                            {formatLabel(submission.status)}
                          </span>
                        </td>

                        <td>
                          {submission.entrant?.name ?? 'Unknown'}
                          <span className="table-subtext">
                            {submission.entrant?.email}
                          </span>
                        </td>

                        <td>
                          {submission.registered_voters
                            ?? 'Not recorded'}
                        </td>

                        <td>
                          {submission.ballots_cast
                            ?? 'Not recorded'}
                        </td>

                        <td>
                          {submission.valid_ballots
                            ?? 'Not recorded'}
                        </td>

                        <td>
                          {submission.invalid_ballots
                            ?? 'Not recorded'}
                        </td>

                        <td>
                          {submission.blank_ballots
                            ?? 'Not recorded'}
                        </td>

                        <td>
                          {formatDateTime(
                            submission.submitted_at,
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div className="modal-actions">
              <button
                className="secondary-button"
                onClick={onClose}
                type="button"
              >
                Close
              </button>
            </div>
          </>
        )}
      </section>

      {isEntryFormOpen && sheet && (
        <TallySubmissionForm
          entryNumber={Number(nextEntryNumber)}
          existingSubmission={ownDraft}
          key={`${sheet.id}-${nextEntryNumber}`}
          onCancel={() => setIsEntryFormOpen(false)}
          onSaved={handleSubmissionSaved}
          sheet={sheet}
        />
      )}

      {reviewAction && sheet && (
        <TallyReviewForm
          action={reviewAction}
          key={`${sheet.id}-${reviewAction}`}
          onCancel={() => setReviewAction(null)}
          onSaved={handleReviewSaved}
          sheet={sheet}
        />
      )}
    </div>
  )
}