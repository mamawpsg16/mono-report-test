const REQUIRED_COLUMNS = ['customer_code', 'year', 'name']

const VALID_COLUMNS = [
  'customer_code', 'year', 'name', 'email',
  'phone', 'address', 'city', 'country'
]

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function validateCSV(headers, rows) {
  const errors = []

  const normalizedHeaders = headers.map((h) => h.trim().toLowerCase().replace(/\s+/g, '_'))
  const missingCols = REQUIRED_COLUMNS.filter((c) => !normalizedHeaders.includes(c))

  if (missingCols.length) {
    errors.push(`Missing required columns: ${missingCols.join(', ')}`)
    return errors
  }

  const unknownCols = normalizedHeaders.filter((h) => !VALID_COLUMNS.includes(h))
  if (unknownCols.length) {
    errors.push(`Unknown columns: ${unknownCols.join(', ')}`)
  }

  rows.forEach((row, i) => {
    const rowNum = i + 2

    REQUIRED_COLUMNS.forEach((col) => {
      const val = (row[col] || '').trim()
      if (!val) {
        errors.push(`Row ${rowNum}: '${col}' is empty`)
      }
    })

    const year = row.year ? row.year.trim() : ''
    if (year && (!/^\d{4}$/.test(year) || +year < 1900 || +year > 2100)) {
      errors.push(`Row ${rowNum}: 'year' must be a 4-digit year (got "${year}")`)
    }

    const email = row.email ? row.email.trim() : ''
    if (email && !EMAIL_REGEX.test(email)) {
      errors.push(`Row ${rowNum}: invalid email format ("${email}")`)
    }
  })

  return errors
}
