import { useRef, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import {
  deleteIncidentAttachment,
  downloadIncidentAttachment,
  uploadIncidentAttachment,
} from '../services/incidents.js'

const labels = {
  submitted: 'Submitted',
  in_review: 'In review',
  resolved: 'Resolved',
  dismissed: 'Dismissed',
  general: 'General',
  access: 'Access',
  safety: 'Safety',
  medical: 'Medical',
  equipment: 'Equipment',
  logistics: 'Logistics',
  conduct: 'Conduct',
  other: 'Other',
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  critical: 'Critical',
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

function formatFileSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function IncidentDetails({ incident, onClose, onRefresh }) {
  const fileInput = useRef(null)
  const [isUploading, setIsUploading] = useState(false)
  const [attachmentError, setAttachmentError] = useState('')
  const [attachmentToDelete, setAttachmentToDelete] = useState(null)

  async function handleUpload(event) {
    const file = event.target.files?.[0]
    if (!file) return

    setAttachmentError('')

    if (file.size > 10 * 1024 * 1024) {
      setAttachmentError('Attachments must be 10 MB or smaller.')
      event.target.value = ''
      return
    }

    setIsUploading(true)

    try {
      await uploadIncidentAttachment(incident.id, file)
      await onRefresh()
    } catch (requestError) {
      const validationMessage = Object.values(
        requestError.response?.data?.errors ?? {},
      ).flat()[0]

      setAttachmentError(
        validationMessage
          ?? 'The attachment could not be uploaded.',
      )
    } finally {
      setIsUploading(false)
      event.target.value = ''
    }
  }

  async function handleDownload(attachment) {
    setAttachmentError('')

    try {
      await downloadIncidentAttachment(attachment)
    } catch {
      setAttachmentError('The attachment could not be downloaded.')
    }
  }

  async function handleDeleteAttachment() {
    await deleteIncidentAttachment(attachmentToDelete.id)
    setAttachmentToDelete(null)
    await onRefresh()
  }

  const location = [
    incident.area?.name_en,
    incident.polling_center?.name_en,
    incident.polling_station
      ? `Station ${incident.polling_station.station_number}`
      : null,
  ].filter(Boolean)

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card incident-details-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="incident-details-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Incident record</p>
            <h3 id="incident-details-title">{incident.title}</h3>
            <p className="page-description">
              {incident.reference_code}
            </p>
          </div>
          <button
            type="button"
            className="modal-close"
            onClick={onClose}
            aria-label="Close incident details"
          >
            ×
          </button>
        </div>

        <div className="incident-detail-badges">
          <span className={`status-pill ${incident.status}`}>
            {labels[incident.status] ?? incident.status}
          </span>
          <span className={`incident-severity ${incident.severity}`}>
            {labels[incident.severity] ?? incident.severity}
          </span>
          <span className="incident-category">
            {labels[incident.category] ?? incident.category}
          </span>
        </div>

        <div className="incident-description">
          {incident.description}
        </div>

        <dl className="incident-details-grid">
          <div>
            <dt>Occurred</dt>
            <dd>{formatDateTime(incident.occurred_at)}</dd>
          </div>
          <div>
            <dt>Reported</dt>
            <dd>{formatDateTime(incident.reported_at)}</dd>
          </div>
          <div>
            <dt>Reporter</dt>
            <dd>{incident.reporter?.name ?? 'Unknown'}</dd>
          </div>
          <div>
            <dt>Assignee</dt>
            <dd>{incident.assignee?.name ?? 'Unassigned'}</dd>
          </div>
          <div>
            <dt>Location</dt>
            <dd>{location.join(' — ') || 'Not specified'}</dd>
          </div>
          <div>
            <dt>Related task</dt>
            <dd>{incident.campaign_task?.title ?? 'None'}</dd>
          </div>
        </dl>

        {incident.location_notes && (
          <section className="incident-note-panel">
            <strong>Location notes</strong>
            <p>{incident.location_notes}</p>
          </section>
        )}

        {incident.resolution_notes && (
          <section className="incident-note-panel resolution">
            <strong>Review or resolution notes</strong>
            <p>{incident.resolution_notes}</p>
            <small>
              Reviewed by {incident.reviewer?.name ?? 'team member'} ·{' '}
              {formatDateTime(incident.reviewed_at)}
            </small>
          </section>
        )}

        <section className="incident-attachments">
          <div className="incident-section-heading">
            <div>
              <h4>Private evidence</h4>
              <p>JPG, PNG, WebP, or PDF; maximum 10 MB.</p>
            </div>

            {incident.actions.manage_attachments && (
              <>
                <input
                  ref={fileInput}
                  type="file"
                  accept=".jpg,.jpeg,.png,.webp,.pdf"
                  onChange={handleUpload}
                  hidden
                />
                <button
                  type="button"
                  className="secondary-button"
                  onClick={() => fileInput.current?.click()}
                  disabled={isUploading}
                >
                  {isUploading ? 'Uploading...' : 'Add attachment'}
                </button>
              </>
            )}
          </div>

          {attachmentError && (
            <div className="error-message" role="alert">
              {attachmentError}
            </div>
          )}

          {incident.attachments.length === 0 ? (
            <p className="state-message compact-state">
              No attachments recorded.
            </p>
          ) : (
            <div className="attachment-list">
              {incident.attachments.map((attachment) => (
                <article className="attachment-item" key={attachment.id}>
                  <div>
                    <strong>{attachment.original_name}</strong>
                    <span>
                      {formatFileSize(attachment.size_bytes)} · Uploaded by{' '}
                      {attachment.uploader?.name ?? 'team member'}
                    </span>
                  </div>
                  <div className="table-actions">
                    <button
                      type="button"
                      className="text-button"
                      onClick={() => handleDownload(attachment)}
                    >
                      Download
                    </button>
                    {incident.actions.manage_attachments && (
                      <button
                        type="button"
                        className="text-button danger"
                        onClick={() => setAttachmentToDelete(attachment)}
                      >
                        Delete
                      </button>
                    )}
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>

        <div className="modal-actions">
          <button type="button" className="secondary-button" onClick={onClose}>
            Close
          </button>
        </div>
      </section>

      {attachmentToDelete && (
        <ConfirmDialog
          title="Delete attachment?"
          message={`Delete ${attachmentToDelete.original_name}? This cannot be undone.`}
          onConfirm={handleDeleteAttachment}
          onCancel={() => setAttachmentToDelete(null)}
          errorMessage="The attachment could not be deleted."
        />
      )}
    </div>
  )
}

export default IncidentDetails
