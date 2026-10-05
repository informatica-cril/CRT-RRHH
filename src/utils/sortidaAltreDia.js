// Jornada tancada un altre dia: s'entra el 01/10 i la sortida no es fitxa fins al 02/10 (o més tard).
// start_time/end_time/date arriben en hora de Madrid sense zona ('2026-10-02T08:20:00.000'), així que
// el dia es llegeix directament dels 10 primers caràcters, sense passar per Date (que hi aplicaria
// la zona del dispositiu).
export function sortidaAltreDia(log) {
  if (!log?.end_time || !log?.date) return null
  const diaJornada = String(log.date).slice(0, 10)
  const diaSortida = String(log.end_time).slice(0, 10)
  if (diaSortida <= diaJornada) return null
  const dies = Math.round((Date.parse(diaSortida) - Date.parse(diaJornada)) / 86400000)
  return { data: diaSortida.split('-').reverse().join('/'), dies }
}
