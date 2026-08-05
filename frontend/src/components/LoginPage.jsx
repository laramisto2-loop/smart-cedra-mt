import { useState } from 'react'

function LoginPage({ onLogin }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')
    setSubmitting(true)

    try {
      await onLogin({
        email,
        password,
        remember,
      })
    } catch (requestError) {
      setError(
        requestError.response?.data?.errors?.email?.[0] ??
          'Unable to sign in. Please try again.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="login-page">
      <section className="login-introduction">
        <div className="login-brand">
          <span className="brand-logo">EF</span>

          <div>
            <strong>ElectoFlow</strong>
            <span>Campaign Operations</span>
          </div>
        </div>

        <div className="login-message">
          <p className="login-eyebrow">Secure campaign management</p>
          <h1>Coordinate your campaign from one protected workspace.</h1>
          <p>
            Manage teams, geography, field operations, and campaign data while
            keeping every tenant securely isolated.
          </p>
        </div>

        <p className="login-security">
          Protected by tenant-aware authentication and authorization.
        </p>
      </section>

      <section className="login-form-section">
        <form className="login-form" onSubmit={handleSubmit}>
          <div className="login-form-heading">
            <span className="login-mobile-logo">EF</span>
            <p className="eyebrow">Welcome back</p>
            <h2>Sign in to ElectoFlow</h2>
            <p>Enter your campaign account details to continue.</p>
          </div>

          {error && (
            <div className="login-error" role="alert">
              {error}
            </div>
          )}

          <label className="form-field">
            <span>Email address</span>
            <input
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              autoComplete="username"
              placeholder="name@example.com"
              required
            />
          </label>

          <label className="form-field">
            <span>Password</span>
            <input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="current-password"
              placeholder="Enter your password"
              required
            />
          </label>

          <label className="remember-option">
            <input
              type="checkbox"
              checked={remember}
              onChange={(event) => setRemember(event.target.checked)}
            />
            <span>Keep me signed in</span>
          </label>

          <button
            type="submit"
            className="login-button"
            disabled={submitting}
          >
            {submitting ? 'Signing in...' : 'Sign in'}
          </button>

          <p className="login-help">
            Contact your campaign administrator if you cannot access your
            account.
          </p>
        </form>
      </section>
    </main>
  )
}

export default LoginPage