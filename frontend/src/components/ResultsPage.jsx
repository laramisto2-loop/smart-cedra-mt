import { useState } from 'react'
import ElectionContestsPanel from './ElectionContestsPanel.jsx'
import ResultsAnalyticsPanel from './ResultsAnalyticsPanel.jsx'
import TallySheetsPanel from './TallySheetsPanel.jsx'
import TurnoutPage from './TurnoutPage.jsx'

export default function ResultsPage({
  permissions = [],
  user,
}) {
  const canViewContests = permissions.includes(
    'results.contests.view',
  )
  const canViewTallies = permissions.includes(
    'results.tallies.view',
  )
  const canViewAnalytics = permissions.includes(
    'results.analytics.view',
  )
  const canViewTurnout = permissions.includes('turnout.view')

  const [activeTab, setActiveTab] = useState(
    canViewContests
      ? 'contests'
      : canViewTallies
        ? 'tallies'
        : canViewAnalytics
          ? 'analytics'
          : 'turnout',
  )

  const tabs = [
    canViewContests && {
      id: 'contests',
      label: 'Election contests',
    },
    canViewTallies && {
      id: 'tallies',
      label: 'Tally sheets',
    },
    canViewAnalytics && {
      id: 'analytics',
      label: 'Analytics',
    },
    canViewTurnout && {
      id: 'turnout',
      label: 'Turnout reporting',
    },
  ].filter(Boolean)

  return (
    <section className="results-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">ELECTION RESULTS</p>
          <h1>Results</h1>
          <p>
            Configure contests, enter verified tally sheets,
            approve reconciled results, and monitor election
            reporting.
          </p>
        </div>
      </div>

      {tabs.length > 0 ? (
        <>
          <nav
            aria-label="Results sections"
            className="messaging-tabs"
          >
            {tabs.map((tab) => (
              <button
                className={
                  activeTab === tab.id ? 'active' : ''
                }
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                type="button"
              >
                {tab.label}
              </button>
            ))}
          </nav>

          {activeTab === 'contests' && (
            <ElectionContestsPanel
              permissions={permissions}
            />
          )}

          {activeTab === 'tallies' && (
            <TallySheetsPanel
              permissions={permissions}
              user={user}
            />
          )}

          {activeTab === 'analytics' && (
            <ResultsAnalyticsPanel
              permissions={permissions}
            />
          )}

          {activeTab === 'turnout' && (
            <TurnoutPage user={user} />
          )}
        </>
      ) : (
        <section className="empty-state table-card">
          <h2>Results access is restricted</h2>
          <p>
            Your role does not currently include results or
            turnout access.
          </p>
        </section>
      )}
    </section>
  )
}
