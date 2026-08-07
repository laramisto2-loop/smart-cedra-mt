import { useState } from 'react'

function ConfirmDialog({
  title,
  message,
  confirmLabel = 'Delete',
  onConfirm,
  onCancel,
}) {
  const [isConfirming, setIsConfirming] = useState(false)
  const [error, setError] = useState('')

  async function handleConfirm() {
    setIsConfirming(true)
    setError('')

    try {
      await onConfirm()
    } catch (requestError) {
      const message =
        requestError.response?.status === 403
          ? 'You do not have permission to delete this record.'
          : 'The record could not be deleted. Please try again.'

      setError(message)
      setIsConfirming(false)
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card confirmation-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirmation-title"
        aria-describedby="confirmation-message"
      >
        <div className="confirmation-icon" aria-hidden="true">
          !
        </div>

        <h3 id="confirmation-title">{title}</h3>

        <p id="confirmation-message">{message}</p>

        {error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        <div className="modal-actions">
          <button
            type="button"
            className="secondary-button"
            onClick={onCancel}
            disabled={isConfirming}
          >
            Cancel
          </button>

          <button
            type="button"
            className="delete-button"
            onClick={handleConfirm}
            disabled={isConfirming}
          >
            {isConfirming ? 'Deleting...' : confirmLabel}
          </button>
        </div>
      </section>
    </div>
  )
}

export default ConfirmDialog