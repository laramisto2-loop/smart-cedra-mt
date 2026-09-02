import { useState } from 'react'

function MailIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 6.75h16v10.5H4z" />
      <path d="m4.75 7.5 7.25 5 7.25-5" />
    </svg>
  )
}

function LockIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <rect x="5" y="10" width="14" height="10" rx="2" />
      <path d="M8.25 10V7.5a3.75 3.75 0 0 1 7.5 0V10" />
    </svg>
  )
}

function EyeIcon({ hidden }) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M3.5 12s3.2-5 8.5-5 8.5 5 8.5 5-3.2 5-8.5 5-8.5-5-8.5-5Z" />
      <circle cx="12" cy="12" r="2.25" />
      {hidden && <path d="m4 4 16 16" />}
    </svg>
  )
}

function CheckIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="m6.5 12.5 3.25 3.25L17.5 8" />
    </svg>
  )
}

function LoginPage({ onLogin }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
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
        <div className="login-ambient login-ambient-one" aria-hidden="true" />
        <div className="login-ambient login-ambient-two" aria-hidden="true" />

        <div className="login-brand">
          <span className="brand-logo">EF</span>

          <div>
            <strong>ElectoFlow</strong>
            <span>Campaign Operations</span>
          </div>
        </div>

        <div className="login-hero-content">
          <div className="login-message">
            <p className="login-eyebrow">
              <span /> Purpose-built campaign operations
            </p>
            <h1>
              Every operation.
              <span> One clear view.</span>
            </h1>
            <p>
              Coordinate teams, field activity, communications, and results
              from one secure workspace designed for decisive campaign days.
            </p>
          </div>

          <div className="login-operations-card" aria-hidden="true">
            <div className="login-operations-heading">
              <div>
                <span>Workspace overview</span>
                <strong>Operations center</strong>
              </div>
              <span className="login-live-indicator">
                <i /> Protected
              </span>
            </div>
          </div>

          <div className="login-trust-list">
            <span><CheckIcon /> Tenant isolated</span>
            <span><CheckIcon /> Offline ready</span>
            <span><CheckIcon /> Fully auditable</span>
          </div>
        </div>

        <p className="login-security">
          ElectoFlow <span>·</span> Campaign operations, intelligently connected
        </p>
      </section>

      <section className="login-form-section">
        <div className="login-form-wrap">
          <div className="login-mobile-brand">
            <span className="login-mobile-logo">EF</span>
            <div>
              <strong>ElectoFlow</strong>
              <span>Campaign Operations</span>
            </div>
          </div>

          <form className="login-form" onSubmit={handleSubmit}>
            <div className="login-form-heading">
              <span className="login-access-badge">
                <LockIcon /> Secure access
              </span>
              <h2>Welcome back</h2>
              <p>Sign in to continue to your campaign workspace.</p>
            </div>

            {error && (
              <div className="login-error" role="alert">
                {error}
              </div>
            )}

            <label className="form-field">
              <span>Email address</span>
              <span className="login-input-wrap">
                <span className="login-input-icon"><MailIcon /></span>
                <input
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  autoComplete="username"
                  placeholder="name@example.com"
                  required
                />
              </span>
            </label>

            <label className="form-field">
              <span>Password</span>
              <span className="login-input-wrap">
                <span className="login-input-icon"><LockIcon /></span>
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  autoComplete="current-password"
                  placeholder="Enter your password"
                  required
                />
                <button
                  type="button"
                  className="login-password-toggle"
                  onClick={() => setShowPassword((visible) => !visible)}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                  aria-pressed={showPassword}
                >
                  <EyeIcon hidden={showPassword} />
                </button>
              </span>
            </label>

            <div className="login-form-options">
              <label className="remember-option">
                <input
                  type="checkbox"
                  checked={remember}
                  onChange={(event) => setRemember(event.target.checked)}
                />
                <span>Keep me signed in</span>
              </label>
              <span className="login-session-label">Encrypted session</span>
            </div>

            <button
              type="submit"
              className="login-button"
              disabled={submitting}
            >
              <span>{submitting ? 'Signing in...' : 'Sign in to workspace'}</span>
              {!submitting && <span aria-hidden="true">→</span>}
            </button>

            <div className="login-help">
              <span className="login-help-icon"><LockIcon /></span>
              <p>
                Need access? Contact your campaign administrator for account
                assistance.
              </p>
            </div>
          </form>

          <p className="login-form-footer">
            Secure campaign management <span>·</span> ElectoFlow
          </p>
        </div>
      </section>
    </main>
  )
}

export default LoginPage
