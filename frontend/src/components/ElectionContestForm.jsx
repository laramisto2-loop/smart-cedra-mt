import { useState } from 'react'
import {
  createElectionContest,
  updateElectionContest,
} from '../services/resultsIngestion.js'

function emptyOption(order) {
  return {
    code: '',
    name: '',
    optionType: 'candidate',
    ballotOrder: order,
    description: '',
  }
}

function contestFormValues(contest) {
  return {
    name: contest?.name ?? '',
    code: contest?.code ?? '',
    electionDate: contest?.election_date ?? '',
    description: contest?.description ?? '',
    options:
      contest?.options?.length > 0
        ? contest.options.map((option, index) => ({
            code: option.code ?? '',
            name: option.name ?? '',
            optionType: option.option_type ?? 'candidate',
            ballotOrder: option.ballot_order ?? index + 1,
            description: option.description ?? '',
          }))
        : [emptyOption(1)],
  }
}

export default function ElectionContestForm({
  contest,
  onCancel,
  onSaved,
}) {
  const [form, setForm] = useState(() => contestFormValues(contest))
  const [errors, setErrors] = useState({})
  const [submitError, setSubmitError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  const isEditing = Boolean(contest?.id)


  function updateField(field, value) {
    setForm((current) => ({
      ...current,
      [field]: value,
    }))
  }

  function updateOption(index, field, value) {
    setForm((current) => ({
      ...current,
      options: current.options.map((option, optionIndex) =>
        optionIndex === index
          ? {
              ...option,
              [field]: value,
            }
          : option,
      ),
    }))
  }

  function addOption() {
    setForm((current) => ({
      ...current,
      options: [...current.options, emptyOption(current.options.length + 1)],
    }))
  }

  function removeOption(index) {
    setForm((current) => ({
      ...current,
      options: current.options
        .filter((_, optionIndex) => optionIndex !== index)
        .map((option, optionIndex) => ({
          ...option,
          ballotOrder: optionIndex + 1,
        })),
    }))
  }

  async function handleSubmit(event) {
    event.preventDefault()

    setIsSaving(true)
    setErrors({})
    setSubmitError('')

    const payload = {
      name: form.name.trim(),
      code: form.code.trim(),
      election_date: form.electionDate,
      description: form.description.trim() || null,
      options: form.options
        .filter((option) => option.code.trim() || option.name.trim())
        .map((option, index) => ({
          code: option.code.trim(),
          name: option.name.trim(),
          option_type: option.optionType,
          ballot_order: Number(option.ballotOrder) || index + 1,
          description: option.description.trim() || null,
        })),
    }

    try {
      const savedContest = isEditing
        ? await updateElectionContest(contest.id, payload)
        : await createElectionContest(payload)

      onSaved(savedContest, isEditing ? 'updated' : 'created')
    } catch (error) {
      setErrors(error.response?.data?.errors ?? {})
      setSubmitError(
        error.response?.data?.message ??
          'The election contest could not be saved.',
      )
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div
        className="modal-backdrop results-modal-backdrop"
        role="presentation"
    >
      <section
        aria-modal="true"
        className="modal results-modal"
        role="dialog"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">RESULTS CONFIGURATION</p>
            <h2>{isEditing ? 'Edit election contest' : 'Create election contest'}</h2>
          </div>
          <button
            aria-label="Close election contest form"
            className="icon-button"
            onClick={onCancel}
            type="button"
          >
            ×
          </button>
        </div>

        <p className="modal-introduction">
          Configure the contest and its ballot options before tally sheets are
          entered. A contest must contain at least one active option before it
          can be activated.
        </p>

        {submitError && (
          <div className="form-error-banner">{submitError}</div>
        )}

        <form className="results-form" onSubmit={handleSubmit}>
          <div className="form-grid">
            <label className="form-field form-field-wide">
              <span>Contest name</span>
              <input
                onChange={(event) => updateField('name', event.target.value)}
                placeholder="Example: Beirut Municipal Election"
                value={form.name}
              />
              {errors.name && <small className="field-error">{errors.name[0]}</small>}
            </label>

            <label className="form-field">
              <span>Contest code</span>
              <input
                onChange={(event) => updateField('code', event.target.value)}
                placeholder="BEIRUT_MUNICIPAL_2026"
                value={form.code}
              />
              {errors.code && <small className="field-error">{errors.code[0]}</small>}
            </label>

            <label className="form-field">
              <span>Election date</span>
              <input
                onChange={(event) =>
                  updateField('electionDate', event.target.value)
                }
                type="date"
                value={form.electionDate}
              />
              {errors.election_date && (
                <small className="field-error">{errors.election_date[0]}</small>
              )}
            </label>

            <label className="form-field form-field-wide">
              <span>Description (optional)</span>
              <textarea
                onChange={(event) =>
                  updateField('description', event.target.value)
                }
                placeholder="Describe the election, referendum, or campaign result being recorded."
                rows="3"
                value={form.description}
              />
              {errors.description && (
                <small className="field-error">{errors.description[0]}</small>
              )}
            </label>
          </div>

          <div className="results-form-section">
            <div className="form-section-heading">
              <div>
                <h3>Ballot options</h3>
                <p>Add every candidate, list, response, or referendum choice.</p>
              </div>
              <button className="secondary-button" onClick={addOption} type="button">
                Add option
              </button>
            </div>

            {errors.options && (
              <small className="field-error">{errors.options[0]}</small>
            )}

            <div className="contest-options">
              {form.options.map((option, index) => (
                <article className="contest-option-card" key={`${index}-${option.code}`}>
                  <div className="contest-option-heading">
                    <strong>Option {index + 1}</strong>
                    {form.options.length > 1 && (
                      <button
                        className="danger-link"
                        onClick={() => removeOption(index)}
                        type="button"
                      >
                        Remove
                      </button>
                    )}
                  </div>

                  <div className="form-grid">
                    <label className="form-field">
                      <span>Option name</span>
                      <input
                        onChange={(event) =>
                          updateOption(index, 'name', event.target.value)
                        }
                        placeholder="Example: Candidate A"
                        value={option.name}
                      />
                    </label>

                    <label className="form-field">
                      <span>Option code</span>
                      <input
                        onChange={(event) =>
                          updateOption(index, 'code', event.target.value)
                        }
                        placeholder="CANDIDATE_A"
                        value={option.code}
                      />
                    </label>

                    <label className="form-field">
                      <span>Option type</span>
                      <select
                        onChange={(event) =>
                          updateOption(index, 'optionType', event.target.value)
                        }
                        value={option.optionType}
                      >
                        <option value="candidate">Candidate</option>
                        <option value="list">List</option>
                        <option value="referendum_choice">Referendum choice</option>
                        <option value="other">Other</option>
                      </select>
                    </label>

                    <label className="form-field">
                      <span>Ballot order</span>
                      <input
                        min="1"
                        onChange={(event) =>
                          updateOption(index, 'ballotOrder', event.target.value)
                        }
                        type="number"
                        value={option.ballotOrder}
                      />
                    </label>

                    <label className="form-field form-field-wide">
                      <span>Description (optional)</span>
                      <input
                        onChange={(event) =>
                          updateOption(index, 'description', event.target.value)
                        }
                        placeholder="Optional context for this ballot option"
                        value={option.description}
                      />
                    </label>
                  </div>
                </article>
              ))}
            </div>
          </div>

          <div className="modal-actions">
            <button
              className="secondary-button"
              disabled={isSaving}
              onClick={onCancel}
              type="button"
            >
              Cancel
            </button>
            <button className="primary-button" disabled={isSaving} type="submit">
              {isSaving
                ? 'Saving...'
                : isEditing
                  ? 'Save changes'
                  : 'Create contest'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}