import { useEffect, useState } from 'react'
import './App.css'
import Dashboard from './components/Dashboard.jsx'
import LoginPage from './components/LoginPage.jsx'
import PlatformDashboard from './components/PlatformDashboard.jsx'
import {
  getAuthenticatedUser,
  login,
  logout,
} from './services/auth.js'

const CACHED_USER_KEY = 'electoflow.authenticated-user'

function readCachedUser() {
  try {
    const value = window.localStorage.getItem(
      CACHED_USER_KEY,
    )

    return value ? JSON.parse(value) : null
  } catch {
    window.localStorage.removeItem(CACHED_USER_KEY)

    return null
  }
}

function cacheUser(user) {
  try {
    window.localStorage.setItem(
      CACHED_USER_KEY,
      JSON.stringify(user),
    )
  } catch {
    // The application can still operate online when
    // browser storage is unavailable.
  }
}

function clearCachedUser() {
  window.localStorage.removeItem(CACHED_USER_KEY)
}

function App() {
  const [user, setUser] = useState(null)
  const [checkingSession, setCheckingSession] =
    useState(true)

  useEffect(() => {
    let active = true

    getAuthenticatedUser()
      .then((authenticatedUser) => {
        if (!active) return

        setUser(authenticatedUser)
        cacheUser(authenticatedUser)
      })
      .catch((requestError) => {
        if (!active) return

        if (!requestError.response) {
          setUser(readCachedUser())
          return
        }

        clearCachedUser()
        setUser(null)
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
    cacheUser(authenticatedUser)
  }

  async function handleLogout() {
    try {
      await logout()
    } finally {
      clearCachedUser()
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

  if (user.is_platform_admin) {
    return (
      <PlatformDashboard
        user={user}
        onLogout={handleLogout}
      />
    )
  }

  return (
    <Dashboard
      user={user}
      onLogout={handleLogout}
    />
  )
}

export default App