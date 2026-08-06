import { useState } from 'react'
import DistrictsPage from './DistrictsPage.jsx'
import GovernoratesPage from './GovernoratesPage.jsx'

const geographyTabs = [
  {
    id: 'governorates',
    label: 'Governorates',
    enabled: true,
  },
  {
    id: 'districts',
    label: 'Districts',
    enabled: true,
  },
  {
    id: 'areas',
    label: 'Areas',
    enabled: false,
  },
  {
    id: 'polling-centers',
    label: 'Polling centers',
    enabled: false,
  },
  {
    id: 'polling-stations',
    label: 'Polling stations',
    enabled: false,
  },
]

function GeographyPage({ user }) {
  const [activeTab, setActiveTab] = useState('governorates')

  return (
    <div className="geography-workspace">
      <nav
        className="geography-tabs"
        aria-label="Geography hierarchy"
      >
        {geographyTabs.map((tab) => (
          <button
            type="button"
            key={tab.id}
            className={`geography-tab ${
              activeTab === tab.id ? 'active' : ''
            }`}
            onClick={() => setActiveTab(tab.id)}
            disabled={!tab.enabled}
            aria-current={
              activeTab === tab.id ? 'page' : undefined
            }
          >
            {tab.label}

            {!tab.enabled && (
              <span className="tab-status">Soon</span>
            )}
          </button>
        ))}
      </nav>

      {activeTab === 'governorates' && (
        <GovernoratesPage user={user} />
      )}

      {activeTab === 'districts' && (
        <DistrictsPage user={user} />
      )}
    </div>
  )
}

export default GeographyPage