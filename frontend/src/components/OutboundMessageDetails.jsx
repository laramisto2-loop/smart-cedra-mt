function formatDateTime(value) {
  if (!value) {
    return 'Not recorded'
  }

  return new Intl.DateTimeFormat('en-LB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatLabel(value) {
  if (!value) {
    return 'Unknown'
  }

  return value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function OutboundMessageDetails({ message, onClose }) {
  const deliveryEvents = message.delivery_events ?? []

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card message-details-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="outbound-message-details-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Outbound message</p>
            <h3 id="outbound-message-details-title">
              {message.reference_code}
            </h3>
            <p className="page-description">
              {message.contact?.full_name ?? 'Unknown contact'}
            </p>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onClose}
            aria-label="Close message details"
          >
            ×
          </button>
        </div>

        <div className="message-detail-badges">
          <span
            className={`message-status ${message.status}`}
          >
            {formatLabel(message.status)}
          </span>

          <span className="message-channel">
            {message.channel?.toUpperCase()}
          </span>

          <span
            className={`consent-status ${message.consent_status}`}
          >
            Consent: {formatLabel(message.consent_status)}
          </span>
        </div>

        <section className="message-body-panel">
          <strong>Rendered message</strong>
          <p>{message.rendered_body}</p>
        </section>

        <dl className="message-details-grid">
          <div>
            <dt>Contact</dt>
            <dd>
              {message.contact?.full_name ?? 'Unknown'}
              <small>
                {message.contact?.reference_code ?? ''}
              </small>
            </dd>
          </div>

          <div>
            <dt>Recipient</dt>
            <dd>{message.recipient}</dd>
          </div>

          <div>
            <dt>Template</dt>
            <dd>
              {message.template?.name ?? message.template_code}
              <small>{message.template_code}</small>
            </dd>
          </div>

          <div>
            <dt>Sender</dt>
            <dd>{message.sender?.name ?? 'Unknown'}</dd>
          </div>

          <div>
            <dt>Source</dt>
            <dd>{formatLabel(message.source)}</dd>
          </div>

          <div>
            <dt>Provider</dt>
            <dd>{message.provider ?? 'Not assigned'}</dd>
          </div>

          <div>
            <dt>Created</dt>
            <dd>{formatDateTime(message.created_at)}</dd>
          </div>

          <div>
            <dt>Consent checked</dt>
            <dd>
              {formatDateTime(message.consent_checked_at)}
            </dd>
          </div>

          <div>
            <dt>Scheduled</dt>
            <dd>{formatDateTime(message.scheduled_at)}</dd>
          </div>

          <div>
            <dt>Sent</dt>
            <dd>{formatDateTime(message.sent_at)}</dd>
          </div>

          <div>
            <dt>Delivered</dt>
            <dd>{formatDateTime(message.delivered_at)}</dd>
          </div>

          <div>
            <dt>Read</dt>
            <dd>{formatDateTime(message.read_at)}</dd>
          </div>
        </dl>

        {message.suppression_reason && (
          <section className="message-notice-panel warning">
            <strong>Suppression reason</strong>
            <p>{message.suppression_reason}</p>
          </section>
        )}

        {(message.error_code || message.error_message) && (
          <section className="message-notice-panel error">
            <strong>
              Delivery error
              {message.error_code
                ? ` — ${message.error_code}`
                : ''}
            </strong>
            <p>
              {message.error_message ??
                'The provider reported a delivery error.'}
            </p>
          </section>
        )}

        {Object.keys(message.variables ?? {}).length > 0 && (
          <section className="message-variables">
            <h4>Resolved variables</h4>

            <dl>
              {Object.entries(message.variables).map(
                ([name, value]) => (
                  <div key={name}>
                    <dt>{name}</dt>
                    <dd>{value}</dd>
                  </div>
                ),
              )}
            </dl>
          </section>
        )}

        <section className="message-events">
          <div className="message-section-heading">
            <div>
              <h4>Delivery timeline</h4>
              <p>
                Provider and application events recorded for
                this message.
              </p>
            </div>
          </div>

          {deliveryEvents.length === 0 ? (
            <p className="state-message compact-state">
              No delivery events recorded.
            </p>
          ) : (
            <div className="message-event-list">
              {deliveryEvents.map((event) => (
                <article
                  className="message-event-item"
                  key={event.id}
                >
                  <span
                    className={`message-status ${event.status}`}
                  >
                    {formatLabel(
                      event.event_type ?? event.status,
                    )}
                  </span>

                  <div>
                    <strong>
                      {formatLabel(event.status)}
                    </strong>
                    <small>
                      {formatDateTime(event.occurred_at)} ·{' '}
                      {event.provider ?? 'Application'}
                    </small>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>

        <div className="modal-actions">
          <button
            type="button"
            className="secondary-button"
            onClick={onClose}
          >
            Close
          </button>
        </div>
      </section>
    </div>
  )
}

export default OutboundMessageDetails