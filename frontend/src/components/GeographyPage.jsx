import { useState } from 'react'
import AreasPage from './AreasPage.jsx'
import DistrictsPage from './DistrictsPage.jsx'
import GovernoratesPage from './GovernoratesPage.jsx'
import PollingCentersPage from './PollingCentersPage.jsx'
import PollingStationsPage from './PollingStationsPage.jsx'
import GeographyTransferPage from './GeographyTransferPage.jsx'

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
    enabled: true,
  },
  {
    id: 'polling-centers',
    label: 'Polling centers',
    enabled: true,
  },
  {
    id: 'polling-stations',
    label: 'Polling stations',
    enabled: true,
  },
    {
    id: 'data-transfer',
    label: 'Data transfer',
    enabled: true,
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

      {activeTab === 'areas' && <AreasPage user={user} />}
      {activeTab === 'polling-centers' && (
        <PollingCentersPage user={user} />
      )}
      {activeTab === 'polling-stations' && (
        <PollingStationsPage user={user} />
      )}
      {activeTab === 'data-transfer' && (
        <GeographyTransferPage user={user} />
      )}
    </div>
  )
}

export default GeographyPage