// Day-only values (Laravel 'date' casts, e.g. planned_date, week_start_date)
// have no meaningful time component. Format in UTC explicitly -- running
// them through the browser's local timezone could roll the date back a day
// for anyone west of UTC.
export function formatDate(isoDate) {
  const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number)
  return new Date(Date.UTC(year, month - 1, day)).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    timeZone: 'UTC',
  })
}
