import { useState } from 'react'
import {
  downloadContactTemplate,
  exportContactData,
  importContactData,
  previewContactImport,
} from '../services/contactTransfers.js'

function getRequestError(error, fallbackMessage) {
  const validationErrors = error.response?.data?.errors

  if (validationErrors) {
    const firstError = Object.values(validationErrors)
      .flat()
      .find(Boolean)

    if (firstError) {
      return firstError
    }
  }

  return error.response?.data?.message ?? fallbackMessage
}

function formatRowValues(data) {
  return Object.entries(data ?? {})
    .map(([key, value]) => {
      const displayedValue =
        value === null || value === '' ? '-' : String(value)

      return `${key}: ${displayedValue}`
    })
    .join(' • ')
}

function ContactTransferDialog({
  user,
  onImported,
  onClose,
}) {
  const [file, setFile] = useState(null)
  const [fileInputKey, setFileInputKey] = useState(0)
  const [preview, setPreview] = useState(null)
  const [confirmed, setConfirmed] = useState(false)
  const [busyAction, setBusyAction] = useState('')
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const permissions = user.permissions ?? []
  const canImport = permissions.includes('contacts.import')
  const canExport = permissions.includes('contacts.export')
  const hasInvalidRows = (preview?.summary?.invalid ?? 0) > 0
  const canConfirmImport =
    preview &&
    !hasInvalidRows &&
    confirmed &&
    busyAction === ''

  function changeFile(event) {
    setFile(event.target.files?.[0] ?? null)
    setPreview(null)
    setConfirmed(false)
    setError('')
    setSuccess('')
  }

  async function downloadTemplate() {
    setBusyAction('template')
    setError('')
    setSuccess('')

    try {
      await downloadContactTemplate()
    } catch (requestError) {
      setError(
        getRequestError(
          requestError,
          'The contact CSV template could not be downloaded.',
        ),
      )
    } finally {
      setBusyAction('')
    }
  }

  async function downloadExport() {
    setBusyAction('export')
    setError('')
    setSuccess('')

    try {
      await exportContactData()
    } catch (requestError) {
      setError(
        getRequestError(
          requestError,
          'The current contact data could not be exported.',
        ),
      )
    } finally {
      setBusyAction('')
    }
  }

  async function previewFile(event) {
    event.preventDefault()

    if (!file) {
      setError('Choose a CSV file before previewing it.')

      return
    }

    setBusyAction('preview')
    setError('')
    setSuccess('')
    setPreview(null)
    setConfirmed(false)

    try {
      const result = await previewContactImport(file)

      setPreview(result)
    } catch (requestError) {
      setError(
        getRequestError(
          requestError,
          'The contact CSV could not be previewed.',
        ),
      )
    } finally {
      setBusyAction('')
    }
  }

  async function confirmImport() {
    if (!file || !canConfirmImport) {
      return
    }

    setBusyAction('import')
    setError('')
    setSuccess('')

    try {
      const result = await importContactData(file)
      const created = result.summary?.created ?? 0
      const updated = result.summary?.updated ?? 0

      setSuccess(
        `Import complete: ${created} created and ${updated} updated.`,
      )
      setFile(null)
      setPreview(null)
      setConfirmed(false)
      setFileInputKey((currentKey) => currentKey + 1)
      onImported()
    } catch (requestError) {
      setError(
        getRequestError(
          requestError,
          'The contact data could not be imported.',
        ),
      )
    } finally {
      setBusyAction('')
    }
  }

  function closeDialog() {
    if (busyAction === '') {
      onClose()
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card contact-transfer-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="contact-transfer-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Contact data tools</p>
            <h3 id="contact-transfer-title">
              Import and export contacts
            </h3>
            <p className="modal-description">
              Download contact data or safely review a CSV before
              importing it.
            </p>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={closeDialog}
            disabled={busyAction !== ''}
            aria-label="Close contact data tools"
          >
            ×
          </button>
        </div>

        <div className="transfer-order-note">
          <strong>Contact records only:</strong>
          <span>
            Consent decisions and interaction history are managed
            separately and are not changed by this CSV.
          </span>
        </div>

        {error && (
          <div className="form-message error-message" role="alert">
            {error}
          </div>
        )}

        {success && (
          <div
            className="form-message success-message"
            role="status"
          >
            {success}
          </div>
        )}

        <div className="transfer-grid">
          <section className="transfer-card">
            <p className="eyebrow">Step 1</p>
            <h3>Prepare your CSV</h3>
            <p>
              Start with a blank template or export the tenant’s
              current contacts for editing.
            </p>

            <div className="transfer-actions">
              {canImport && (
                <button
                  type="button"
                  className="secondary-button"
                  onClick={downloadTemplate}
                  disabled={busyAction !== ''}
                >
                  {busyAction === 'template'
                    ? 'Downloading...'
                    : 'Download blank template'}
                </button>
              )}

              {canExport && (
                <button
                  type="button"
                  className="secondary-button"
                  onClick={downloadExport}
                  disabled={busyAction !== ''}
                >
                  {busyAction === 'export'
                    ? 'Exporting...'
                    : 'Export current contacts'}
                </button>
              )}
            </div>
          </section>

          <section className="transfer-card">
            <p className="eyebrow">Step 2</p>
            <h3>Upload and preview</h3>
            <p>
              Previewing validates every row without changing any
              contact records.
            </p>

            {!canImport && (
              <div className="form-message">
                You can export contacts, but you do not have
                permission to import them.
              </div>
            )}

            {canImport && (
              <form onSubmit={previewFile}>
                <label htmlFor="contact-csv">CSV file</label>
                <input
                  key={fileInputKey}
                  id="contact-csv"
                  type="file"
                  accept=".csv,text/csv"
                  onChange={changeFile}
                  disabled={busyAction !== ''}
                />

                {file && (
                  <p className="selected-file">
                    Selected file: <strong>{file.name}</strong>
                  </p>
                )}

                <div className="transfer-actions">
                  <button
                    type="submit"
                    className="primary-button"
                    disabled={!file || busyAction !== ''}
                  >
                    {busyAction === 'preview'
                      ? 'Checking file...'
                      : 'Preview changes'}
                  </button>
                </div>
              </form>
            )}
          </section>
        </div>

        {preview && (
          <section className="transfer-preview">
            <div className="transfer-preview-heading">
              <div>
                <p className="eyebrow">Preview result</p>
                <h3>{preview.filename}</h3>
              </div>

              <span
                className={
                  hasInvalidRows
                    ? 'preview-readiness invalid'
                    : 'preview-readiness ready'
                }
              >
                {hasInvalidRows
                  ? 'Problems must be fixed'
                  : 'Ready to import'}
              </span>
            </div>

            <div className="transfer-summary">
              <div>
                <span>Total rows</span>
                <strong>{preview.summary?.total ?? 0}</strong>
              </div>

              <div>
                <span>Will be created</span>
                <strong>{preview.summary?.create ?? 0}</strong>
              </div>

              <div>
                <span>Will be updated</span>
                <strong>{preview.summary?.update ?? 0}</strong>
              </div>

              <div className={hasInvalidRows ? 'invalid' : ''}>
                <span>Invalid rows</span>
                <strong>{preview.summary?.invalid ?? 0}</strong>
              </div>
            </div>

            {preview.truncated && (
              <p className="preview-note">
                Only the first preview rows are displayed. Every row
                in the file was still validated.
              </p>
            )}

            <div className="transfer-table-wrapper">
              <table className="transfer-table">
                <thead>
                  <tr>
                    <th>CSV line</th>
                    <th>Result</th>
                    <th>Values</th>
                    <th>Problems</th>
                  </tr>
                </thead>

                <tbody>
                  {(preview.rows ?? []).map((row) => (
                    <tr key={row.line}>
                      <td>{row.line}</td>
                      <td>
                        <span
                          className={`row-status ${row.status}`}
                        >
                          {row.status}
                        </span>
                      </td>
                      <td>{formatRowValues(row.data)}</td>
                      <td>
                        {(row.errors ?? []).length > 0
                          ? row.errors.join(' ')
                          : '-'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {!hasInvalidRows && (
              <div className="transfer-confirmation">
                <label className="confirmation-check">
                  <input
                    type="checkbox"
                    checked={confirmed}
                    onChange={(event) =>
                      setConfirmed(event.target.checked)
                    }
                    disabled={busyAction !== ''}
                  />

                  <span>
                    I reviewed this preview and confirm these contacts
                    may be created or updated.
                  </span>
                </label>

                <button
                  type="button"
                  className="primary-button"
                  onClick={confirmImport}
                  disabled={!canConfirmImport}
                >
                  {busyAction === 'import'
                    ? 'Importing...'
                    : 'Confirm import'}
                </button>
              </div>
            )}
          </section>
        )}
      </section>
    </div>
  )
}

export default ContactTransferDialog