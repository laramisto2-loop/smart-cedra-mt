import { useState } from 'react'
import MessageTemplatesPanel from './MessageTemplatesPanel.jsx'
import OutboundMessagesPanel from './OutboundMessagesPanel.jsx'

function MessagingPage({ user }) {
  const permissions = user.permissions ?? []

  const canViewTemplates = permissions.includes(
    'messages.templates.view',
  )
  const canViewMessages = permissions.includes(
    'messages.view',
  )

  const firstAvailableSection = canViewMessages
    ? 'messages'
    : 'templates'

  const [activeSection, setActiveSection] = useState(
    firstAvailableSection,
  )

  return (
    <section className="messaging-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">
            Campaign communications
          </p>
          <h2>Messaging</h2>
          <p className="page-description">
            Manage approved templates, verify contact consent,
            send messages, and inspect delivery outcomes.
          </p>
        </div>
      </div>

      <div
        className="messaging-tabs"
        role="tablist"
        aria-label="Messaging sections"
      >
        {canViewMessages && (
          <button
            type="button"
            role="tab"
            aria-selected={activeSection === 'messages'}
            className={
              activeSection === 'messages' ? 'active' : ''
            }
            onClick={() => setActiveSection('messages')}
          >
            Outbound messages
          </button>
        )}

        {canViewTemplates && (
          <button
            type="button"
            role="tab"
            aria-selected={activeSection === 'templates'}
            className={
              activeSection === 'templates' ? 'active' : ''
            }
            onClick={() => setActiveSection('templates')}
          >
            Message templates
          </button>
        )}
      </div>

      {activeSection === 'messages' &&
        canViewMessages && (
          <OutboundMessagesPanel user={user} />
        )}

      {activeSection === 'templates' &&
        canViewTemplates && (
          <MessageTemplatesPanel user={user} />
        )}

      {!canViewMessages && !canViewTemplates && (
        <article className="content-card">
          <div className="empty-state">
            <h3>Messaging is restricted</h3>
            <p>
              Your role does not have permission to access
              message templates or outbound message history.
            </p>
          </div>
        </article>
      )}
    </section>
  )
}

export default MessagingPage
//This creates the main Messaging screen with permission-aware tabs for outbound messages and templates