// Llegeix les nòmines d'un bolcat .sql del portal antic (phpMyAdmin, taula `nominas`).
// Es fa al navegador perquè el fitxer (desenes de MB, amb dades personals) no passi
// sencer pel servidor ni pel repositori: només s'envien les nòmines, per tandes.
//
// Columnes de `nominas`: id, nombre, dni, documento_name, documento_pdf, fecha_nomina, id_usuario, ...

const ESCAPES = { n: '\n', r: '\r', t: '\t', 0: '\0', Z: '\x1a' }
const ESPECIAL = /['\\]/g

/** Retorna [{ nom, dni, data, pdf, nom_fitxer }] de totes les sentències INSERT de `nominas`. */
export function llegeixNominesPortalAntic(text) {
  const out = []
  const clau = 'INSERT INTO `nominas`'
  let pos = 0
  while (true) {
    const ini = text.indexOf(clau, pos)
    if (ini < 0) break
    let k = text.indexOf('VALUES', ini) + 6
    const n = text.length
    while (k < n) {
      const c = text[k]
      if (c === '(') {
        const [camps, fi] = llegeixTupla(text, k + 1)
        k = fi
        if (camps.length >= 6) {
          out.push({
            nom: camps[1] || '',
            dni: (camps[2] || '').trim(),
            nom_fitxer: camps[3] ? `${camps[3]}.pdf` : null,
            pdf: camps[4] || '',
            data: (camps[5] || '').slice(0, 10),
          })
        }
      } else if (c === ';') {
        break
      } else {
        k++
      }
    }
    pos = k
  }
  return out
}

function llegeixTupla(text, k) {
  const camps = []
  let cru = ''
  while (k < text.length) {
    const c = text[k]
    if (c === "'") {
      k++
      let buf = ''
      // Per trossos (els PDF fan centenars de KB): salta fins a la propera cometa o barra.
      while (k < text.length) {
        ESPECIAL.lastIndex = k
        const m = ESPECIAL.exec(text)
        if (!m) { buf += text.slice(k); k = text.length; break }
        const p = m.index
        buf += text.slice(k, p)
        if (m[0] === '\\') { buf += ESCAPES[text[p + 1]] ?? text[p + 1]; k = p + 2; continue }
        if (text[p + 1] === "'") { buf += "'"; k = p + 2; continue }
        k = p + 1
        break
      }
      camps.push(buf)
      cru = null
      continue
    }
    if (c === ',' || c === ')') {
      if (cru !== null) {
        const v = cru.trim()
        camps.push(v === 'NULL' ? null : v)
      }
      cru = ''
      k++
      if (c === ')') break
      continue
    }
    if (cru !== null) cru += c
    k++
  }
  return [camps, k]
}
