import { useEffect, useState } from 'react'
import './App.css'
import Dashboard from './components/Dashboard.jsx'
import LoginPage from './components/LoginPage.jsx'
import {
  getAuthenticatedUser,
  login,
  logout,
} from './services/auth.js'

function App() {
  const [user, setUser] = useState(null)
  const [checkingSession, setCheckingSession] = useState(true)

  useEffect(() => {
    let active = true

    getAuthenticatedUser()
      .then((authenticatedUser) => {
        if (active) {
          setUser(authenticatedUser)
        }
      })
      .catch(() => {
        if (active) {
          setUser(null)
        }
      })
      .finally(() => {
        if (active) {
          setCheckingSession(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  async function handleLogin(credentials) {
    const authenticatedUser = await login(credentials)

    setUser(authenticatedUser)
  }

  async function handleLogout() {
    try {
      await logout()
    } finally {
      setUser(null)
    }
  }

  if (checkingSession) {
    return (
      <main className="login-form-section">
        <p>Checking your session...</p>
      </main>
    )
  }

  if (!user) {
    return <LoginPage onLogin={handleLogin} />
  }

  return <Dashboard user={user} onLogout={handleLogout} />
}

export default App