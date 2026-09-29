// ============================================================
// Generador de Calendari Laboral (PDF) — CRT
// ------------------------------------------------------------
// Genera al vol un PDF anual amb el calendari laboral del treballador
// a partir de: horari configurat (work_schedule), festius (calendari
// laboral) i dies efectivament treballats (fichatges).
// El PDF s'incrusta i NO és descarregable (es renderitza a canvas).
// ============================================================
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib'

const COL = {
  festiu: rgb(0.98, 0.82, 0.82),
  festiuTxt: rgb(0.72, 0.11, 0.11),
  laborable: rgb(0.90, 0.95, 1.0),
  laborableBorder: rgb(0.60, 0.75, 0.95),
  noLaborable: rgb(0.93, 0.94, 0.96),
  treballat: rgb(0.13, 0.70, 0.42),
  text: rgb(0.12, 0.14, 0.18),
  muted: rgb(0.45, 0.48, 0.52),
  navy: rgb(0.11, 0.16, 0.29),
}

const MONTHS = ['Gener', 'Febrer', 'Març', 'Abril', 'Maig', 'Juny', 'Juliol', 'Agost', 'Setembre', 'Octubre', 'Novembre', 'Desembre']
const WD = ['Dl', 'Dt', 'Dc', 'Dj', 'Dv', 'Ds', 'Dg']

// Hores programades d'un dia segons l'horari (days[].day: 0=Dg..6=Ds, com getDay())
// Un dia és laborable si té start/end i no està explícitament desactivat (alguns horaris no porten 'active').
function scheduledHours(schedule, jsDay) {
  const cfg = schedule?.days?.find(d => (d.day === jsDay || d.day_num === jsDay))
  if (!cfg || cfg.active === false || !cfg.start || !cfg.end) return 0
  const [sh, sm] = String(cfg.start).split(':').map(Number)
  const [eh, em] = String(cfg.end).split(':').map(Number)
  return Math.max(0, (eh * 60 + em - sh * 60 - sm) / 60)
}

/**
 * @param {object} opts
 * @param {string} opts.workerName
 * @param {string} opts.scheduleName
 * @param {object} opts.schedule  work_schedule amb days[]
 * @param {Set<string>} opts.holidaySet  dates 'YYYY-MM-DD' festives
 * @param {Set<string>} opts.workedDates  dates 'YYYY-MM-DD' amb fichatge
 * @param {number} opts.year
 * @returns {Promise<Uint8Array>}
 */
export async function generateLaborCalendarPdf({ workerName, scheduleName, schedule, holidaySet, workedDates, year }) {
  const pdf = await PDFDocument.create()
  const page = pdf.addPage([595.28, 841.89]) // A4 portrait
  const font = await pdf.embedFont(StandardFonts.Helvetica)
  const bold = await pdf.embedFont(StandardFonts.HelveticaBold)
  const W = page.getWidth(), H = page.getHeight()

  const holidays = holidaySet || new Set()
  const worked = workedDates || new Set()

  // ── Capçalera ──
  page.drawText('Calendari laboral ' + year, { x: 32, y: H - 46, size: 18, font: bold, color: COL.navy })
  page.drawText(workerName || '', { x: 32, y: H - 66, size: 11, font, color: COL.text })
  let weekly = 0
  for (let d = 0; d < 7; d++) weekly += scheduledHours(schedule, d)
  page.drawText(`Horari: ${scheduleName || '—'} · ${weekly.toFixed(1)}h/setmana`, { x: 32, y: H - 82, size: 9, font, color: COL.muted })

  // ── Llegenda ──
  const legend = [
    { c: COL.laborable, b: COL.laborableBorder, t: 'Laborable' },
    { c: COL.festiu, b: COL.festiuTxt, t: 'Festiu' },
    { c: COL.noLaborable, b: COL.noLaborable, t: 'No laborable' },
    { c: rgb(1, 1, 1), b: COL.treballat, t: 'Treballat', dot: true },
  ]
  let lx = 32
  const ly = H - 104
  for (const it of legend) {
    page.drawRectangle({ x: lx, y: ly, width: 12, height: 12, color: it.c, borderColor: it.b, borderWidth: 1 })
    if (it.dot) page.drawCircle({ x: lx + 6, y: ly + 6, size: 2.4, color: COL.treballat })
    page.drawText(it.t, { x: lx + 17, y: ly + 2, size: 8, font, color: COL.text })
    lx += 22 + font.widthOfTextAtSize(it.t, 8) + 16
  }

  // ── Graella de 12 mesos (3 columnes × 4 files) ──
  const marginX = 32, marginTop = H - 130
  const cols = 3, gapX = 14, gapY = 16
  const blockW = (W - marginX * 2 - gapX * (cols - 1)) / cols
  const cell = blockW / 7
  const blockH = 14 /*títol*/ + 12 /*capçalera dies*/ + 6 * (cell * 0.72) + 8

  for (let mo = 0; mo < 12; mo++) {
    const col = mo % cols
    const row = Math.floor(mo / cols)
    const bx = marginX + col * (blockW + gapX)
    const byTop = marginTop - row * (blockH + gapY)

    page.drawText(MONTHS[mo], { x: bx, y: byTop, size: 9.5, font: bold, color: COL.navy })

    // Capçalera dies de la setmana
    const headerY = byTop - 12
    for (let i = 0; i < 7; i++) {
      page.drawText(WD[i], { x: bx + i * cell + cell / 2 - 4, y: headerY, size: 6, font, color: COL.muted })
    }

    const dch = cell * 0.72 // alçada de cel·la de dia
    const firstDay = new Date(year, mo, 1)
    const startCol = (firstDay.getDay() + 6) % 7 // Dilluns = 0
    const daysInMonth = new Date(year, mo + 1, 0).getDate()

    for (let d = 1; d <= daysInMonth; d++) {
      const idx = startCol + (d - 1)
      const cRow = Math.floor(idx / 7)
      const cCol = idx % 7
      const cx = bx + cCol * cell
      const cy = headerY - 4 - (cRow + 1) * dch
      const dateStr = `${year}-${String(mo + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
      const jsDay = new Date(year, mo, d).getDay()
      const isHoliday = holidays.has(dateStr)
      const isScheduled = scheduledHours(schedule, jsDay) > 0 && !isHoliday
      const isWorked = worked.has(dateStr)

      const fill = isHoliday ? COL.festiu : (isScheduled ? COL.laborable : COL.noLaborable)
      const border = isHoliday ? COL.festiuTxt : (isScheduled ? COL.laborableBorder : COL.noLaborable)
      page.drawRectangle({ x: cx + 0.5, y: cy, width: cell - 1, height: dch - 1, color: fill, borderColor: border, borderWidth: 0.5 })
      page.drawText(String(d), { x: cx + 2, y: cy + dch - 8, size: 5.5, font, color: isHoliday ? COL.festiuTxt : COL.text })
      if (isWorked) page.drawCircle({ x: cx + cell - 4, y: cy + 3.5, size: 1.7, color: COL.treballat })
    }
  }

  // ── Peu ──
  page.drawText('CRT · Document informatiu del calendari laboral · No descarregable', { x: 32, y: 24, size: 7, font, color: COL.muted })
  page.drawText('Generat: ' + new Date().toLocaleDateString('ca-ES'), { x: W - 130, y: 24, size: 7, font, color: COL.muted })

  return await pdf.save()
}
