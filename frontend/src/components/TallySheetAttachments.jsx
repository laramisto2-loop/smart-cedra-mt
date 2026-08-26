import {
  useRef,
  useState,
} from 'react'
import {
  deleteTallyAttachment,
  downloadTallyAttachment,
  uploadTallyAttachment,
} from '../services/resultsIngestion.js'

function formatBytes(value) {
  const bytes = Number(value)

  if (!Number.isFinite(bytes) || bytes <= 0) {
    return 'Unknown size'
  }

  if (bytes < 1024) {
    return `${bytes} B`
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
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

export default function TallySheetAttachments({
  onChanged,
  permissions = [],
  sheet,
}) {
  const fileInputRef = useRef(null)
  const [selectedFile, setSelectedFile] = useState(null)
  const [isUploading, setIsUploading] = useState(false)
  const [activeAttachmentId, setActiveAttachmentId] =
    useState(null)
  const [error, setError] = useState('')

  const attachments = sheet?.attachments ?? []
  const isFinalized = [
    'approved',
    'rejected',
  ].includes(sheet?.status)

  const canManage =
    permissions.includes('results.attachments.manage')
    && !isFinalized

  async function handleUpload(event) {
    event.preventDefault()

    if (!selectedFile) {
      setError('Select an evidence file before uploading.')
      return
    }

    if (selectedFile.size > 10 * 1024 * 1024) {
      setError('The evidence file cannot exceed 10 MB.')
      return
    }

    setIsUploading(true)
    setError('')

    try {
      const clientUuid =
        window.crypto?.randomUUID?.() ?? null

      const fileDate = selectedFile.lastModified
        ? new Date(selectedFile.lastModified).toISOString()
        : null

      await uploadTallyAttachment(sheet.id, {
        file: selectedFile,
        clientUuid,
        capturedAt: fileDate,
        clientUpdatedAt: fileDate,
      })

      setSelectedFile(null)

      if (fileInputRef.current) {
        fileInputRef.current.value = ''
      }

      await onChanged?.()
    } catch (requestError) {
      setError(
        requestError.response?.data?.message
        ?? 'The evidence file could not be uploaded.',
      )
    } finally {
      setIsUploading(false)
    }
  }

  async function handleDownload(attachment) {
    setActiveAttachmentId(attachment.id)
    setError('')

    try {
      await downloadTallyAttachment(
        attachment.id,
        attachment.original_name,
      )
    } catch (requestError) {
      setError(
        requestError.response?.data?.message
        ?? 'The evidence file could not be downloaded.',
      )
    } finally {
      setActiveAttachmentId(null)
    }
  }

  async function handleDelete(attachment) {
    const confirmed = window.confirm(
      `Delete "${attachment.original_name}" permanently?`,
    )

    if (!confirmed) {
      return
    }

    setActiveAttachmentId(attachment.id)
    setError('')

    try {
      await deleteTallyAttachment(attachment.id)
      await onChanged?.()
    } catch (requestError) {
      setError(
        requestError.response?.data?.message
        ?? 'The evidence file could not be deleted.',
      )
    } finally {
      setActiveAttachmentId(null)
    }
  }

  return (
    <section className="results-attachments">
      <div className="results-attachment-heading">
        <div>
          <h3>Private tally evidence</h3>
          <p>
            Upload photographs or PDF copies of the official tally
            sheet. Evidence is tenant-protected.
          </p>
        </div>

        <span className="status-pill status-approved">
          {attachments.length}{' '}
          {attachments.length === 1 ? 'file' : 'files'}
        </span>
      </div>

      {error && (
        <div className="form-error-banner">{error}</div>
      )}

      {canManage && (
        <form
          className="results-attachment-form"
          onSubmit={handleUpload}
        >
          <label className="form-field">
            <span>Evidence file</span>
            <input
              accept=".pdf,.jpg,.jpeg,.png,.webp"
              onChange={(event) => {
                setSelectedFile(event.target.files?.[0] ?? null)
                setError('')
              }}
              ref={fileInputRef}
              type="file"
            />
            <small>
              PDF, JPG, PNG, or WebP. Maximum size: 10 MB.
            </small>
          </label>

          <button
            className="primary-button"
            disabled={!selectedFile || isUploading}
            type="submit"
          >
            {isUploading ? 'Uploading...' : 'Upload evidence'}
          </button>
        </form>
      )}

      {isFinalized && (
        <div className="info-banner">
          This tally sheet is finalized. Its existing evidence can
          be downloaded, but new files cannot be added or deleted.
        </div>
      )}

      {attachments.length === 0 ? (
        <div className="empty-state compact-state">
          <h3>No evidence uploaded</h3>
          <p>
            Add a photograph or PDF of the official tally sheet
            before final approval.
          </p>
        </div>
      ) : (
        <div className="attachment-list">
          {attachments.map((attachment) => {
            const isProcessing =
              activeAttachmentId === attachment.id

            return (
              <article
                className="attachment-item"
                key={attachment.id}
              >
                <div>
                  <strong>{attachment.original_name}</strong>
                  <span>
                    {formatBytes(attachment.size_bytes)}
                    {' · '}
                    {attachment.uploader?.name ?? 'Unknown uploader'}
                    {' · '}
                    {formatDateTime(attachment.created_at)}
                  </span>
                </div>

                <div className="attachment-actions">
                  <button
                    className="secondary-button"
                    disabled={isProcessing}
                    onClick={() => handleDownload(attachment)}
                    type="button"
                  >
                    {isProcessing ? 'Please wait...' : 'Download'}
                  </button>

                  {canManage && (
                    <button
                      className="delete-button"
                      disabled={isProcessing}
                      onClick={() => handleDelete(attachment)}
                      type="button"
                    >
                      Delete
                    </button>
                  )}
                </div>
              </article>
            )
          })}
        </div>
      )}
    </section>
  )
}